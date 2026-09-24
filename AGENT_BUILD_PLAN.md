# Jewellery Shop App: Agent Build Plan

Stack: Laravel (API) + MySQL + Vue 3 + Vuetify 3 + Pinia + Vue Router + Vite.
Put this file in the project root. Tell your agent: **"Read AGENT_BUILD_PLAN.md. Do only the next unchecked step. Stop when its acceptance checks pass."**

---

## 0. Rules for the agent (read every time)

1. Work on **one step at a time**. Never start the next step until the current one is checked off and committed.
2. **Surgical edits only.** Change the smallest region of an existing file. Do not rewrite whole files unless the step says "create".
3. Before creating anything, list existing files and reuse what is there.
4. Laravel conventions: FormRequest for validation, API Resources for output, Service classes for business logic, thin controllers, `DB::transaction` for any multi-table write.
5. **All money and totals are computed on the server.** Never trust totals sent from Vue.
6. **All weights are stored in grams** as `decimal(12,3)`. Money is `decimal(14,2)`. Convert units only in the UI, via one shared helper.
7. Every state change to stock goes through the `stock_movements` table.
8. Every model that matters uses `spatie/laravel-activitylog`. Every route is guarded by a `spatie/laravel-permission` permission.
9. Write at least one Feature test per service or endpoint group. Run `php artisan test` before finishing a step.
10. After each step: update the checklist at the bottom, then commit with the given message.
11. If something is ambiguous, pick the simplest option, write the assumption in `DECISIONS.md`, and continue.

---

## 1. Conventions

**API:** prefix `/api/v1`, Sanctum token auth, JSON only, paginated lists (`?page=&per_page=&search=`).

**Folders**
```
app/Http/Controllers/Api/V1/
app/Http/Requests/
app/Http/Resources/
app/Services/          (SaleService, PawnService, StockService, InvoiceNumberService, PawnInterestService)
app/Support/Weight.php (gram <-> vori/ana/roti/point)
resources/js/
  api/        (axios instance + per-module clients)
  stores/     (Pinia)
  router/
  layouts/
  pages/<module>/
  components/
  utils/weight.js
resources/views/pdf/   (invoice, pawn ticket, receipts)
```

**Weight units:** 1 vori = 11.664 g = 16 ana; 1 ana = 6 roti; 1 roti = 10 point. Implement once in `Weight.php` and `weight.js` with identical logic and tests. A shop setting `weight_unit` (`gram` or `vori`) controls display.

**Vuetify pages:** each module has `Index` (v-data-table-server with search), `Form` (create/edit), and `Show` (details) where relevant.

---

## Step 1: Project bootstrap

**Goal:** Working Laravel + Vue + Vuetify skeleton with auth.

Tasks
- Install Laravel, configure MySQL `.env`.
- Install `laravel/sanctum`, `spatie/laravel-permission`, `spatie/laravel-activitylog`, `barryvdh/laravel-dompdf`, `maatwebsite/excel`.
- Install `vue`, `vuetify`, `pinia`, `vue-router`, `axios`, `@vitejs/plugin-vue`, `vite-plugin-vuetify`, `@mdi/font`.
- Create the SPA shell: `app.blade.php` with `#app`, catch-all route to it, `resources/js/main.js`.
- Layout: `AppLayout.vue` with navigation drawer, top bar, user menu.
- Auth: login endpoint, logout, `GET /me`, Pinia `auth` store, router guard.
- Seed roles: `admin`, `manager`, `cashier`. Seed one admin user.
- Create `Weight.php`, `weight.js` and their unit tests.

Acceptance
- Login works, refresh keeps the session, unauthenticated users go to `/login`.
- Weight helper tests pass (grams to vori/ana/roti/point and back, no rounding drift).

Commit: `feat: bootstrap laravel + vue + vuetify with auth and roles`

---

## Step 2: Settings and Gold Rates

**Goal:** Shop settings and daily gold rates.

Tasks
- `settings` table (key/value): shop name, address, phone, logo, VAT %, currency symbol, `weight_unit`, default pawn interest rate, invoice footer.
- `gold_rates` table: `karat`, `rate_per_gram`, `effective_date`, `created_by`. Unique on (`karat`, `effective_date`).
- API: settings get/update; gold rates CRUD plus `GET /gold-rates/latest`.
- Vue: Settings page, Gold Rate page (today's rates for 18/21/22/24 and history table).
- Show the latest rate in the top bar.

Acceptance
- Rate saved for today appears as latest; history lists older rates.
- Only `admin`/`manager` can change settings.

Commit: `feat: settings and gold rates`

---

## Step 3: Customers

**Goal:** Customer store with full history.

Tasks
- `customers`: `code` (auto), `name`, `phone`, `nid`, `address`, `photo`, `opening_balance`, `notes`.
- CRUD API with search by name, phone, NID, code. Photo upload to `storage/public`.
- `GET /customers/{id}/history` returning tabs data: sales, pawns, payments, and computed **due balance**. Leave sales and pawn parts returning empty arrays until those steps exist.
- Vue: Customer list, form (with photo preview), profile page with tabs (Overview, Sales, Pawns, Payments).
- Reusable `CustomerPicker.vue` (autocomplete + quick add dialog) for later steps.

Acceptance
- Create, edit, search, and view a customer. Duplicate phone shows a validation error.

Commit: `feat: customers module`

---

## Step 4: Gold Inventory

**Goal:** Item catalog and stock tracking.

Tasks
- `categories` (ring, necklace, bangle, etc.).
- `items`: `tag_no` (unique, auto), `category_id`, `name`, `karat`, `gross_weight`, `stone_weight`, `net_weight` (= gross − stone, computed server-side), `making_type` (`per_gram|fixed|percent`), `making_value`, `stone_price`, `status` (`in_stock|sold|pawned|scrap`), `image`, `barcode`.
- `stock_movements`: `item_id`, `type` (`in|out|adjust`), `ref_type`, `ref_id`, `weight`, `note`, `user_id`.
- `StockService`: the only class allowed to change `items.status`; writes a movement each time.
- API: categories CRUD; items CRUD, filter by category, karat, status; stock summary (total net weight by karat, item count); stock adjustment endpoint.
- Vue: Category page, Item list with filters, Item form, Stock Summary page.
- Tag/barcode print page (small label layout).

Acceptance
- Creating an item writes an `in` movement. Stock summary matches the sum of in-stock items.
- Net weight is always gross minus stone, even if the client sends a different value.

Commit: `feat: gold inventory and stock movements`

---

## Step 5: Sale Entry

**Goal:** Sell items with correct server-side totals.

Tasks
- Tables: `sales` (`invoice_no`, `customer_id`, `date`, `subtotal`, `discount`, `vat`, `exchange_amount`, `total`, `paid`, `due`, `status`), `sale_items` (`sale_id`, `item_id`, `weight`, `rate`, `gold_value`, `making`, `stone_price`, `line_total`), `sale_payments` (`sale_id`, `method[cash|bkash|nagad|card|bank]`, `amount`, `reference`), `sale_exchanges` (old gold: `description`, `karat`, `weight`, `rate`, `amount`).
- `InvoiceNumberService`: `INV-YYYY-000001`, generated inside the transaction with a row lock on a counter table.
- `SaleService::create()` in one transaction:
  1. Load items (must be `in_stock`), lock them.
  2. Line total = `net_weight × rate + making + stone_price`, where the rate is the latest gold rate for that karat unless an authorized override is given.
  3. Apply discount, VAT, subtract old-gold exchange.
  4. Validate `paid ≤ total`; `due = total − paid`.
  5. Mark items `sold` through `StockService`.
  6. Exchange items create stock-in movements (as scrap or a new item).
- Void/return endpoint: restores items to stock and reverses payments (manager only).
- Add due payments: `POST /sales/{id}/payments`.
- Vue: Sale Entry page (customer picker, item scan/search, live totals preview, payment rows, exchange rows), Sales list, Sale detail.

Acceptance
- Totals from the server match the preview. A sold item cannot be sold twice (test with two concurrent requests).
- Voiding restores stock and the customer's due.

Commit: `feat: sale entry with exchange and payments`

---

## Step 6: Invoice Printing

**Goal:** Printable documents.

Tasks
- Blade PDF views under `resources/views/pdf/`: `sale-invoice`, `payment-receipt`.
- Two layouts each: **A4** and **thermal 80mm**. Query param `?size=a4|thermal`.
- Header from settings (shop name, address, phone, logo); items table with tag, karat, weight, rate, making, total; totals block; payment lines; footer note; optional QR with invoice number.
- Endpoints: `GET /sales/{id}/pdf`, `GET /payments/{id}/receipt`.
- Vue: Print/Download buttons on Sale detail and after Sale save (open PDF in a new tab).
- Support Bangla text: bundle a Unicode font (e.g. Noto Sans Bengali) and configure DomPDF for it.

Acceptance
- Both sizes render correctly, including Bangla names, with no clipped columns.

Commit: `feat: invoice and receipt printing`

---

## Step 7: Pawn Account

**Goal:** Full pawn lifecycle with interest engine.

Tasks
- Tables: `pawns` (`pawn_no`, `customer_id`, `date`, `principal`, `interest_rate` (% per month), `interest_type[simple]`, `due_date`, `status[active|redeemed|forfeited]`, `notes`), `pawn_items` (`pawn_id`, `description`, `karat`, `gross_weight`, `net_weight`, `estimated_value`, `photo`), `pawn_payments` (`pawn_id`, `type[interest|principal|redeem]`, `amount`, `date`, `note`).
- `PawnInterestService::calculate($pawn, $asOfDate)`:
  - Compute from ledger, not a stored running total.
  - Simple monthly interest on the outstanding principal for each period between principal changes.
  - Partial month rule set in settings: `daily_proration` or `round_up_full_month`.
  - Return: `outstanding_principal`, `interest_accrued`, `interest_paid`, `interest_due`, `total_payable`.
- `PawnService`:
  - `create()`: ticket number `PWN-YYYY-000001`, items saved, principal limited by settings (max loan-to-value % of estimated value).
  - `addPayment()`: allocate to interest due first, then principal.
  - `redeem()`: requires `total_payable` cleared; closes pawn and releases items.
  - `renew()`: settle interest, restart the term.
  - `forfeit()`: manager only, after grace period; can move items into inventory via `StockService`.
- Scheduled command `pawns:mark-overdue` daily.
- Vue: Pawn list (filters: active, overdue, redeemed), New Pawn form, Pawn detail (item list, ledger, live interest breakdown, Pay / Redeem / Renew buttons).
- Tests for the interest engine: no payments, interest-only payment, partial principal payment, full redemption, both proration modes.

Acceptance
- Interest figures in tests match hand-calculated examples.
- Paying principal lowers future interest but not interest already accrued.
- Redeemed pawn cannot receive further payments.

Commit: `feat: pawn accounts with interest engine`

---

## Step 8: Pawn Printing

**Goal:** Pawn documents.

Tasks
- PDFs: **Pawn ticket** (customer, items, principal, rate, due date, terms), **Pawn payment receipt**, **Redemption receipt** (A4 and thermal).
- Endpoints and Vue buttons on the Pawn detail page.
- Customer profile "Pawns" tab now shows real data; wire the Sales tab too.

Acceptance
- Ticket prints on both sizes; terms text is editable in settings.

Commit: `feat: pawn ticket and receipts`

---

## Step 9: Suppliers, Purchases, Karigor

**Goal:** Stock coming in from suppliers/artisans.

Tasks
- `suppliers` (type: `supplier|karigor`), `purchases`, `purchase_items`, `supplier_payments`.
- Purchase creates items (or adds weight) via `StockService` as `in` movements.
- Supplier ledger: purchases minus payments, with balance.
- Vue: Supplier CRUD, Purchase form, Supplier ledger page.

Acceptance
- Ledger balance equals purchases minus payments. Stock rises after purchase.

Commit: `feat: suppliers and purchases`

---

## Step 10: Accounts and Cash Book

**Goal:** Daily money tracking.

Tasks
- `expenses` (category, amount, date, note), `cash_transactions` (unified ledger: source type/id, `in|out`, amount, method, date).
- Every sale payment, due payment, pawn payment, pawn disbursement, supplier payment, and expense writes a cash transaction (via a single `CashBookService`).
- Daily closing: opening balance, totals in/out, closing balance, locked after closing (manager can reopen).
- Vue: Expenses page, Cash Book (date filter, method filter), Daily Closing page.

Acceptance
- Cash book closing balance equals opening + in − out for the day, and matches reports.

Commit: `feat: accounts and cash book`

---

## Step 11: Reports and Dashboard

**Goal:** Management visibility.

Reports (each with date filters, table, Excel export, PDF print)
- Daily / monthly sales
- Stock by karat and category
- Pawn outstanding (principal and interest due)
- Overdue pawns
- Interest earned
- Customer ledger
- Supplier ledger
- Profit summary (sales − purchase cost − expenses)

Dashboard cards: today's sales, gold rate, active pawns and outstanding principal, overdue count, stock weight, cash balance, plus a 30-day sales chart.

Acceptance
- Report totals reconcile with the cash book and stock summary.

Commit: `feat: reports and dashboard`

---

## Step 12: Admin, Audit, Backup, Hardening

Tasks
- Users CRUD, role/permission matrix UI.
- Activity log viewer with filters (user, model, date).
- Nightly DB backup command with retention; a manual "Backup now" button.
- Optional: SMS reminders for pawns due in 7 days and overdue ones (queue job; provider configurable).
- Rate limiting on login, HTTPS-only cookies, `APP_DEBUG=false`, `php artisan optimize`, queue and scheduler cron documented in `DEPLOY.md`.

Acceptance
- A cashier cannot open settings, void sales, or forfeit pawns. All money changes appear in the activity log.

Commit: `feat: admin, audit log, backup and hardening`

---

## Progress checklist (agent updates this)

- [x] Step 1: Bootstrap and auth
- [x] Step 2: Settings and gold rates
- [x] Step 3: Customers
- [x] Step 4: Gold inventory
- [ ] Step 5: Sale entry
- [ ] Step 6: Invoice printing
- [ ] Step 7: Pawn account
- [ ] Step 8: Pawn printing
- [ ] Step 9: Suppliers and purchases
- [ ] Step 10: Accounts and cash book
- [ ] Step 11: Reports and dashboard
- [ ] Step 12: Admin, audit, backup, hardening

---

## Definition of done for every step

- Migrations run fresh (`php artisan migrate:fresh --seed`) without errors.
- Tests pass (`php artisan test`).
- Vue build passes (`npm run build`) with no console errors on the new pages.
- Permissions applied to every new route and menu item.
- Checklist updated and commit made.
