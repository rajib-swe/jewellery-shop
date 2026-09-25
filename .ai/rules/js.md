---
paths:
  - 'resources/js/**'
---

# Js

## All UI text goes through the locale store
Every user-visible string must be a translation key, never a hardcoded literal. Add keys to BOTH resources/js/lang/bn.js and resources/js/lang/en.js; Bangla (bn) is the default locale and en is the fallback for missing keys. Read keys with `$t('...')` in templates or `localeStore.t('...')` in script. Shared option lists (karat, status, making type, payment method, sale status) are built in resources/js/constants/options.js as computed values, not static arrays. Weight/currency formatting goes through resources/js/utils/format.js so the weight unit setting applies everywhere.
