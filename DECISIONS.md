# Decisions

## Step 1

- Sanctum uses its first-party cookie session for the same-origin Vue SPA; bearer-token clients can use externally provisioned tokens, and logout revokes the active token or session.
- The SPA shell and login/logout endpoints are authentication entry points and cannot require the application permission. Protected business API routes such as `/api/v1/me` require `access api`.
- Weight helpers use the plan's exact unit ratios and retain twelve decimal places through conversions, avoiding floating-point display drift. The existing three-decimal database precision remains the storage boundary.
- Local admin seed credentials come from `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`; non-local environments reject missing, default, or shorter-than-12-character passwords, and rerunning the seeder rotates an existing admin password when the configured value changes.
- JavaScript helper tests use Node's built-in test runner, so no additional frontend test dependency is introduced.
