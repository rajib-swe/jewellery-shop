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
