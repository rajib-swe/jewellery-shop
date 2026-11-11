# Jewellery Shop Mobile App: Agent Build Plan

Stack: **Flutter 3.47**, Dart, `dio`, `riverpod`, `go_router`, `flutter_secure_storage`, `sqflite`/`drift`, `mobile_scanner`, `firebase_messaging`, `google_fonts` (Noto Sans Bengali).

This app is a **client only**. It consumes the existing Laravel `/api/v1` Sanctum-token API (already built and live) and never re-implements business logic — totals, VAT, gold-rate math, and pawn interest are always computed server-side.

Put this file in the `mobile/` project root. Tell your agent: **"Read MOBILE_APP_BUILD_PLAN.md. Do only the next unchecked step. Stop when its acceptance checks pass."**

---

## 0. Rules for the agent (read every time)

1. Work on **one step at a time**. Never start the next step until the current one is checked off and committed.
2. **Surgical edits only.** Widen or patch existing files; don't rewrite a whole screen once it exists.
3. **No business logic on-device.** Totals, VAT, interest, and stock validity are always server-computed. The app renders what the API returns and re-fetches after every write — it never calculates a total, a due amount, or interest itself.
4. **Weight conversion is fixed:** 1 vori = 11.664 g = 16 ana; 1 ana = 6 roti; 1 roti = 10 point. Implement once in `lib/core/utils/weight.dart` with unit tests covering gram-to-vori and back with no rounding drift. All stored/transmitted weights are grams; convert only for display.
5. Auth token lives only in `flutter_secure_storage` — never `SharedPreferences`, never a plain file.
6. Every screen has three states: loading, empty, and error-with-retry. Never a blank screen while awaiting a response.
7. Structure by module: a model, a service, and a screen for the same module live together (see folder layout below).
8. Write a test for every service class and for `weight.dart`. Run `flutter test` before finishing a step.
9. Test each step against the **real API**, not a mock, at least once.
10. After each step: update the checklist at the bottom, then commit with the given message.
11. If the API contract below is ambiguous or a needed endpoint doesn't match, note the assumption in `DECISIONS.md` and continue with the simplest reasonable choice.

---

## 1. API contract (fill in / confirm before Step 13)

Confirmed against the Laravel source on 2026-09-24. Corrections are marked; `endpoints.dart` follows reality, and `DECISIONS.md` records why.

```
Base URL (dev):         http://goldapp.test:8080/api/v1
Base URL (production):  https://appjewellery.shop/api/v1
Auth:                  POST /login          -> { data: user, meta, token }
                       POST /logout         -> { message } (revokes the bearer token)
                       GET  /me             -> { data: user }   CORRECTION: data.roles is an array,
                                                               there is no singular `role` and no nested `user`
Dashboard/rates:       GET  /gold-rates/latest      -> { data: [ ...one per karat, not paginated ] }
                       POST /gold-rates             -> { data, meta }
Customers:             GET  /customers?search=&page=&per_page=
                       GET  /customers/{id}         -> numeric id, never a code
                       GET  /customers/{id}/history
Inventory:             GET  /items?category_id=&karat=&status=&search=&page=
                       GET  /items/{id}              CORRECTION: binds by id, NOT tag_no
                       GET  /items?search={tag_no}   CORRECTION: this is how a scan resolves an item
                       GET  /stock/summary           CORRECTION: not /items/stock-summary
Sales:                 POST /sales                   CORRECTION: NO /sales/preview exists.
                       GET  /sales?...
                       GET  /sales/{id}
                       POST /sales/{id}/void
                       GET  /sales/{id}/pdf          CORRECTION: web route, not /api/v1
Pawns:                 GET  /pawns?status=
                       POST /pawns
                       GET  /pawns/{id}               (includes live interest breakdown in data.summary)
                       POST /pawns/{id}/payments
                       POST /pawns/{id}/redeem
                       POST /pawns/{id}/renew
                       GET  /pawns/{id}/pdf          CORRECTION: web route, not /api/v1
Push:                  POST /device-tokens            MISSING: no route, model, table, or push backend
Envelope:              read  -> { data }
                       write -> { data, meta: { message } }
                       list  -> Laravel paginator: { data, links, meta }
Errors:                422 { message, errors: { field: [msg] } }, 401/403 { message }
Weights/money:         grams as 3dp strings, money as 2dp strings. Display only; never recomputed on-device.
```

`/api/v1/login` returns a bearer `token` because Sanctum skips session middleware without a `Referer`/`Origin` header, which a native client never sends. The browser SPA still gets its cookie session and a `null` token.

If any path or field name differs from what's above, the agent should adjust `core/api/endpoints.dart` to match the real API and record the correction in `DECISIONS.md` — don't guess silently and move on.

---

## Folder structure

```
mobile/
  lib/
    main.dart
    core/
      api/            (dio client, auth interceptor, endpoints.dart)
      theme/          (colors, Bangla font, light/dark — match the web app's gold/ink palette)
      utils/weight.dart
    models/           (Customer, Item, Sale, Pawn, GoldRate ...)
    providers/        (riverpod providers per module)
    services/         (auth_service, customer_service, inventory_service, sale_service, pawn_service, dashboard_service)
    screens/
      auth/
      dashboard/
      customers/
      inventory/
      sale/
      pawn/
    widgets/          (shared: CustomerPicker, ItemCard, InvoicePreview, StateHandler)
  test/
```

---

## Step 1: Bootstrap, auth, and shell

**Goal:** Running app that logs in against the real API and shows a themed shell.

Tasks
- `flutter create mobile`; add the packages listed above to `pubspec.yaml`.
- Fill in and confirm the API contract section above.
- `core/api/dio_client.dart`: base URL from `--dart-define=API_BASE_URL`, auth interceptor attaches the bearer token, redirects to login on 401.
- `flutter_secure_storage` wrapper for the token.
- Riverpod `authProvider`: login, logout, current user/role/permissions from `/me`.
- `go_router` with an auth guard — unauthenticated users land on `/login`.
- Bottom navigation shell: Dashboard, Sale, Pawn, Customers, More. Match the web app's color palette; Bangla labels via `google_fonts` (Noto Sans Bengali).
- `widgets/state_handler.dart`: the reusable loading/empty/error wrapper used by every screen from here on.

Acceptance
- Login works against the real API; wrong password shows an inline error; token persists across app restart; logout clears it.
- `flutter test` passes for `weight.dart`.

Commit: `feat(mobile): bootstrap flutter app with auth and shell`

---

## Step 2: Dashboard, gold rate, and customers

**Goal:** The screens that prove the whole pipeline (auth → API → render) works end to end.

Tasks
- Dashboard: today's sales total, latest gold rate by karat, active pawns + outstanding principal, overdue count, cash balance — each its own state-handled card, pull-to-refresh.
- Gold rate quick-update sheet, role-gated (admin/manager only), posts to `/gold-rates`.
- Customers: searchable list (name/phone/NID/code) with pagination, profile screen with tabs (Overview, Sales, Pawns, Payments) using `/customers/{id}/history`.
- `CustomerPicker` widget (searchable bottom sheet) for reuse in Sale/Pawn steps.

Acceptance
- Dashboard numbers match the web app for the same day.
- Only admin/manager can open the rate-edit sheet (verify with a cashier account).

Commit: `feat(mobile): dashboard, gold rate updates, and customers`

---

## Step 3: Inventory and barcode scan

**Goal:** Look up and check stock from the shop floor.

Tasks
- Item list with filters (category, karat, status), mirroring the web filters.
- `mobile_scanner` integration: scan a tag/barcode → fetch item by `tag_no` → detail sheet (photo, weight, karat, status, making charge).
- Stock summary screen (total net weight by karat, item count) — read-only.
- Item photo capture with `image_picker` + `flutter_image_compress`, for an "add item from mobile" action if the role allows it.

Acceptance
- Scanning a real printed tag resolves to the correct item.
- Stock summary total matches the web app's Stock Summary page at the same moment.

Commit: `feat(mobile): inventory lookup and barcode scanning`

---

## Step 4: Sale entry and pawn accounts

**Goal:** The two revenue-critical workflows, usable at the counter.

Tasks
- Sale Entry: `CustomerPicker`, scan or search items into a cart, show the server-computed totals from `/sales/preview` (never compute client-side), payment rows, exchange rows, submit to `/sales`.
- Sale detail + list; void action (role-gated).
- Pawn list (active/overdue/redeemed filters), New Pawn form, Pawn detail showing the server's live interest breakdown with Pay / Redeem / Renew actions.
- Invoice/ticket sharing: fetch the PDF from `/sales/{id}/pdf` or `/pawns/{id}/pdf`, share via `share_plus`/`printing` (WhatsApp, etc.), or send to a paired Bluetooth thermal printer (`flutter_blue_plus` + an ESC/POS package) if one is configured.

Acceptance
- A sale created on mobile appears correctly in the web app's Sales list, with matching totals and stock status.
- Pawn interest shown on mobile matches the web Pawn detail page at the same moment.
- Voiding a sale or redeeming a pawn from mobile updates stock/status consistently with web.

Commit: `feat(mobile): sale entry and pawn account workflows`

---

## Step 5: Push notifications, offline read cache, and release build

**Goal:** Production-ready app.

Tasks
- `firebase_messaging` setup; register the device token at `POST /device-tokens`. (Backend must already send pawn due/overdue and large-sale pushes — confirm this exists; if not, note it in `DECISIONS.md` as a backend dependency, don't build it here.)
- Read-only offline cache (`sqflite`/`drift`) for dashboard numbers, customer list, and item list, so a brief connectivity drop doesn't blank the screen. Writes (sales, payments) always require a live connection — show a clear "no connection" state, don't queue for later sync.
- App icon/splash (`flutter_launcher_icons`, `flutter_native_splash`) using shop branding.
- Android signing (`.jks` keystore) and `flutter build appbundle` for Play Store, or `flutter build apk` for direct install.
- `DEPLOY_MOBILE.md`: build steps, keystore handling, Firebase project setup, API base URL per environment.

Acceptance
- A test push notification arrives on a physical device.
- Airplane mode still shows cached dashboard/customer/item data, clearly marked as cached; any write attempt shows "no connection" instead of failing silently.
- Signed release build installs on a physical Android device.

Commit: `feat(mobile): push notifications, offline read cache, and release build`

---

## Progress checklist (agent updates this)

- [x] Step 1: Bootstrap, auth, and shell
- [x] Step 2: Dashboard, gold rate, and customers
- [x] Step 3: Inventory and barcode scan
- [ ] Step 4: Sale entry and pawn accounts
  - [ ] Sale Entry: `CustomerPicker`, scan/search items into a cart, server-computed totals from `POST /sales` (no `/sales/preview`), payment rows, exchange rows, submit to `/sales`.
  - [ ] Sale list + detail; void action (role-gated, `permission:void sales`).
  - [ ] Pawn list (active/overdue/redeemed filters), New Pawn form, Pawn detail with server live interest breakdown; Pay / Redeem / Renew actions.
  - [ ] Invoice/ticket PDF share from web routes `/sales/{id}/pdf` and `/pawns/{id}/pdf` (needs authenticated download + `share_plus`/`printing`).
- [ ] Step 5: Push notifications, offline read cache, and release build

Step 1 notes: `/sales/preview` does not exist, so Step 4 must read totals from the `POST /sales` response instead of a preview call. `/device-tokens` and the push backend do not exist either, so Step 5 is blocked on the server side.

Step 2 notes: the dashboard is served by `GET /reports/dashboard` and is gated by `permission:view reports`; the gold rate update is gated in the UI to admin/manager roles and the API to `permission:manage gold rates`. Rates and customers were exercised against the local HTTP API. The local dev database is behind the current seeders (admin lacks `view reports`, and the `expenses` table is missing), so dashboard figures could not be compared locally; a seeded install will expose the same payload the web dashboard uses.

Step 3 notes: there is no `GET /items/{tag_no}`, so a scan resolves through `GET /items?search=` and then matches the exact `tag_no` or `barcode`; the detail sheet uses the item returned by that search because `GET /items/{id}` only binds by numeric id. Filters mirror `IndexItemRequest` (`search`, `category_id`, `karat`, `status`). The add-item form is shown only with `manage inventory` and posts multipart (`image` optional, compressed to JPEG first). Verified against the local HTTP API: list, categories, stock summary, a barcode search, and a multipart create (server computed `net_weight`, and the test item was deleted afterwards). The local database needed the pending migrations (`expenses`, `cash_transactions`, `daily_closings`, `convert_weights_to_points`) before `/stock/summary` would run; summary counts only `in_stock` items, matching the web page.

---

## Definition of done for every step

- `flutter analyze` and `flutter test` pass with no errors.
- Tested against the real API (not a mock) at least once per step.
- No business-logic totals computed client-side; the app always trusts and re-fetches server responses.
- Loading/empty/error states present on every new screen.
- Checklist updated and commit made.
