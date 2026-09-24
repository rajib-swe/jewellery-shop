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
