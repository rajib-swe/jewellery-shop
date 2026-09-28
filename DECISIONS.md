# Decisions

## Step 1

- Sanctum uses its first-party cookie session for the same-origin Vue SPA; bearer-token clients can use externally provisioned tokens, and logout revokes the active token or session.
- The SPA shell and login/logout endpoints are authentication entry points and cannot require the application permission. Protected business API routes such as `/api/v1/me` require `access api`.
- Weight helpers use the plan's exact unit ratios and retain twelve decimal places through conversions, avoiding floating-point display drift. The existing three-decimal database precision remains the storage boundary.
- Local admin seed credentials come from `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`; non-local environments reject missing, default, or shorter-than-12-character passwords, and rerunning the seeder rotates an existing admin password when the configured value changes.
- JavaScript helper tests use Node's built-in test runner, so no additional frontend test dependency is introduced.

## Step 2

- Settings use fixed keys: `shop_name`, `shop_address`, `shop_phone`, `shop_logo`, `vat_percentage`, `currency_symbol`, `weight_unit`, `default_pawn_interest_rate`, and `invoice_footer`; arbitrary keys are rejected.
- Default settings are seeded without overwriting administrator changes. Gold prices are not invented by the seeder; optional initial rates may be supplied through `GOLD_RATE_18`, `GOLD_RATE_21`, `GOLD_RATE_22`, and `GOLD_RATE_24`.
- `admin` and `manager` can view and manage settings and gold rates. `cashier` can read settings and gold rates but cannot mutate either resource.
- Gold rates may be backdated but cannot be future-dated. The latest endpoint returns the newest effective rate on or before today for each karat, ordered by karat; both browser and server use the application’s UTC date until a shop timezone is explicitly configured.
- The top bar displays the latest 22K rate, with empty and loading states when no rate exists.
- Logos are validated images stored on the `public` disk under generated filenames; the existing logo is removed only after a successful database update.

## Step 3

- Customer codes are server-generated as `CUS-` plus a ULID and are never accepted from clients.
- Customer phones are normalized to a canonical digit string (`+880`, `00880`, and local `0` forms converge), required, and unique; NIDs remain optional and searchable.
- Customer photos use generated filenames on the `public` disk, with replacement cleanup after a successful database write.
- A nonnegative opening balance is treated as the customer due balance until sales and payments exist; history keeps stable empty arrays for future sales, pawns, and payments.
- `admin` and `manager` can view and manage customers; `cashier` can view customers but cannot mutate them.

## Step 4

- Item tags use server-generated `ITM-` ULIDs, and net weight is always recalculated as gross weight minus stone weight with three-decimal storage precision.
- `StockService` is the only application service that creates stock movements or changes item status; item creation records an inbound movement, and weight edits record adjustments.
- Inventory images use the `public` disk under generated filenames, while categories with existing items cannot be deleted.
- `admin` and `manager` can view and manage inventory; `cashier` can view inventory but cannot mutate items or record adjustments.

## Step 5

- Sale line weight is always the item's stored net weight and the line rate is always the newest gold rate effective on or before the sale date; a client may send `weight`, `subtotal`, `total`, `due`, and `line_total`, but they are ignored. Only a `rate` override is honoured, and only for users holding `manage gold rates`.
- Money is accumulated in integer cents inside `SaleService` so subtotal, VAT, exchange, and due never drift through repeated float rounding. `discount` is a flat amount, not a percentage. VAT is `vat_percentage` applied to `subtotal - discount`, and `total = subtotal - discount + vat - exchange_amount`, which must not go negative.
- `customer_id` is nullable so counter sales can be recorded as walk-in invoices; the customer picker stays optional on the sale form.
- Old gold exchanges use the same per-gram rate table as sales, which keeps a single rate source. An exchange row always records the movement of value; when a `category_id` is supplied, `StockService::createInbound()` additionally creates a `scrap` item with an `in` movement. `createInbound()` gained optional `$status`, `$referenceType`, and `$referenceId` parameters so `StockService` remains the only class that writes movements or changes item status.
- Concurrency safety comes from `lockForUpdate` on the sold items plus the `in_stock` re-check inside the transaction, so a second request for the same item is rejected with a 422 rather than producing a second invoice. The feature test asserts the second attempt fails and only one outbound stock movement exists.
- `InvoiceNumberService` locks a `document_counters` row per scope and year, creating the row on demand and tolerating the unique-constraint race, so invoice numbers are gapless per year (`INV-2026-000001`).
- Voiding a sale is a full reversal: items go back to `in_stock` with an `in` movement, exchange scrap items are deleted, the payment rows are removed, `paid` and `due` are zeroed, and the sale is kept for audit with `voided_by`, `voided_at`, and `void_reason`. A voided sale refuses further payments.
- `admin` and `manager` can view sales, record sales, and record due payments; `void sales` is the separate permission that guards voiding. `cashier` can view sales only.
- `CustomerService::history()` now returns the customer's real sales and payments, and `due_balance` is the opening balance plus the due of every non-void sale. The customer profile Sales and Payments tabs are still wired in Step 8.

## Step 7

- Simple monthly interest is charged against a **30 day month**, the convention the counter staff are used to, and it accrues continuously from the pawn's disbursement date. Interest is computed by replaying the ledger, never from a stored running total, so a renewal, a partial principal payment, or a corrected entry can never drift from the money actually collected.
- The part-month rule is a shop setting, `pawn_partial_month_rule`, with `daily_proration` (days ÷ 30) and `round_up_full_month` (any started month counts as a full month). Each constant-principal run between ledger events is one segment, and the period breakdown is returned so the detail page and the ticket can show the maths.
- **Renewal settles interest, it does not reset the accrual clock.** `renew()` writes an `interest` ledger row for the interest due on the renewal date and moves `due_date` forward by a fresh term. Resetting the accrual start would have erased interest that was already owed, so the rate simply keeps running from the original date.
- `addPayment()` allocates a collection to the interest due first and the remainder to the principal, writing up to two ledger rows so each row is a single honest type. A `redeem` payment is one row that settles interest and principal together and closes the account; a redeemed or forfeited pawn refuses further transactions and stops accruing at its close timestamp.
- Paying principal lowers future interest but does not touch interest already accrued: the accrued arrears are always collected in full before any principal is reduced.
- `pawn_no` uses the same locked per-year counter as invoices, under the new `pawn` scope, giving `PWN-2026-000001`. The counter helper is reused rather than duplicated.
- Pledged items are free-form descriptions with weights and an estimated value, like sale exchange lines, because a pawn is taken against goods that are not necessarily in the catalogue. A pawn item keeps an optional `category_id` so `forfeit()` can book the goods into inventory through `StockService` as the only class allowed to write movements.
- The loan-to-value cap is a shop setting, `pawn_max_ltv_percentage` (default 75%). A principal above that share of the total pledged value is rejected. The term (`pawn_term_days`, default 30) and the grace period before forfeiture (`pawn_grace_days`, default 30) are settings too, so the rules live with the rest of the shop configuration.
- Forfeiting is blocked while the account is still inside its grace period, and requires the separate `forfeit pawns` permission, so a manager's role is what authorises writing a customer off.
- `pawns:mark-overdue` runs daily at 06:00, reports every active pawn past its due date with what is owed, and stamps `overdue_flagged_at` once per day so a repeated run does not double-report. `Schedule::command()->withoutOverlapping()` guards against a slow run overlapping the next one.
- `admin` and `manager` can view and manage pawns; `cashier` can view pawns but cannot record, renew, or forfeit them. `forfeit pawns` is kept separate from `manage pawns`.
- **The build plan's interest-engine feature tests were skipped at the user's request** in exchange for seeded demo data. The engine's figures were instead verified by hand against the seeded accounts and a throwaway service-level probe, which was deleted afterwards. This deviates from rule 9 of the plan and is the reason the acceptance checks are not automated.

## Step 8

- Pawn documents reuse the Step 6 printing stack: `DocumentService` builds a view payload and returns a `PdfDocument`, the mPDF wrapper is the only place paper size is decided, and the controllers stay thin. `mpdfReceiptConfig()` became size-only and `mpdfPawnConfig()` sizes a thermal ticket to its content, so a pawn ticket with more items does not clip.
- There are three pawn papers, not two: the **pawn ticket**, the **payment receipt** for an `interest` or `principal` row, and the **redemption receipt** for a `redeem` row. The redemption gets its own layout because it is the document that releases the pledged goods, so it lists every item returned. `PawnReceiptController` picks the view from the payment type, so one endpoint serves all of them.
- `DocumentService` gained a private `documentShell()` for the shop header, labels, and printed-at stamp so the three pawn papers share one presentation, plus `customerData()` and `summaryData()` helpers. `DocumentLabels` gained the pawn labels, including `printA4` and `printThermal` which the Step 6 toolbar already referenced but never defined.
- The interest breakdown is stripped from the document payload: a printed ticket shows the totals and the part-month rule, not the period table.
- Pawn item photos are served through `Storage::disk('public')->url()` rather than a raw path, because a URL is what an `<img>` tag can load. The shop logo keeps its absolute path since mPDF reads it from disk.
- Document routes are guarded by `view pawns`, matching the `view sales` guard on invoices, and are registered twice per document like the sale routes so `?download=1` and the in-browser print view share one controller.
- `CustomerService::history()` now returns real pawns and folds each pawn's `total_payable` into the customer `due_balance`, so the customer profile Pawns tab shows live figures and the Sales and Payments tabs render their actual rows instead of an empty state.

## Step 9

- The karat rate is resolved through one place, `GoldRateService::rateFor()`. `SaleService::shopRateFor()` was reduced to a call to it rather than keeping a second copy of the same query, so a purchase, a sale, a pawn estimate and a printout can never disagree about "the rate effective on this date". Purchases are priced exactly like sales, discount once on the invoice, and a karat with no rate on or before the date is rejected instead of guessed.
- A rate override on a purchase needs the same `manage gold rates` permission a sale needs. It is defence in depth, because the default matrix gives `manage purchases` and `manage gold rates` to the same roles; the feature test grants a buyer the purchase permission alone to prove the guard is real.
- **Each purchase line creates a new catalogue item** through `StockService::createInbound()` as an `in` movement, rather than adding weight to an existing row. A supplier or karigor drop is a new physical piece, and `StockService` stays the only class allowed to write movements or change item status. `purchase_items.item_id` records the link back.
- A purchase item carries a `making_value` on top of `net_weight × rate`, because a karigor routinely charges a making charge and the shop's purchase cost should be the whole amount. That charge is purchase cost only, so the catalogue item is created with a fixed making charge of zero: the item's own making charge is a selling price, not what we paid.
- The supplier ledger is deliberately plain: **balance = purchases − payments**. There is no opening balance column, so the acceptance rule stays literally true, and an unpaid purchase and an advance both show as one number the shop owes. A payment with `purchase_id` settles that purchase and is capped at its due; without one it is an advance that only moves the balance.
- `purchases.paid` and `purchases.due` are maintained alongside the ledger, but the ledger is the authority. A purchase's due is never allowed to go negative, and a payment cannot settle a purchase that already has nothing due.
- The supplier list shows the balance, which would cost two extra queries per row if it replayed each ledger. It is a `COALESCE(SUM(...))` subselect instead, added **before** `withCount()`: `select()` replaces the query's column list, so calling it afterwards silently drops the count subselects. This was a real bug the feature test caught.
- There is no void or delete for a purchase. Voiding one would have to reverse the stock it created and the payments against it, which is its own piece of work; until then a purchase is an append-only record and a supplier with history cannot be deleted either, so the audit trail cannot be orphaned.
- `GET /purchases/rates?date=` returns the rates a purchase dated that day would be priced at, so the form can show a line cost for a backdated purchase without guessing from today's rates.
- `admin` and `manager` can view and manage suppliers and purchases. A **cashier can view suppliers but not purchases**: buying stock and paying a karigor are management decisions, and a cashier already cannot manage inventory, so seeing the purchase cost is not something the counter needs. This is a judgement call recorded here in case the shop wants it opened up.
- Unlike Steps 7 and 8, this step has a feature test (`SupplierPurchaseApiTest`, 17 cases). The previous two steps were built without tests at the user's request and that is how a 500 on the pawn list shipped; the test immediately caught three real bugs here (two wrong `Response` return types and the `select()`/`withCount()` clobber), so the endpoint group is covered.

## Interface language

- The shop interface is bilingual with **Bangla as the default**; English is selectable from the language toggle in the top bar. The choice persists in `localStorage` under `jewellery-shop.locale` and sets `<html lang>`.
- Translations live in `resources/js/lang/bn.js` and `resources/js/lang/en.js` as nested key objects. `useLocaleStore()` resolves dotted keys, interpolates `{name}` placeholders, and falls back to Bangla (not the raw key) when a translation is missing in English.
- No `vue-i18n` dependency was added; `$t` is registered as a global property in `main.js` and a `localeStore.t` is available in `<script setup>` for reactive values such as data-table headers and navigation arrays.
- Option lists (karat, item status, making type, stock movement, payment method, sale status) are now value-only constants plus a `useOptionLabels()` composable in `resources/js/constants/options.js` that builds translated labels reactively. Static option arrays would freeze the language at module load.
- Weight and money formatting is centralised in `resources/js/utils/format.js` (`useWeightFormatter`, `useCurrency`) so the shop's `weight_unit` setting and `units.gram` / `units.vori` labels apply identically on every screen.
- Backend validation messages are still returned in English and surface as-is; the client only translates its own strings. Server message localisation is a separate piece of work.
