/**
 * The permission matrix, as data rather than as template logic.
 *
 * Each entry is one area of the shop: the permission that opens it, the
 * permission that changes it, and anything extra that area needs. A new
 * permission belongs here as well as in the seeder, so the matrix can never
 * quietly omit a capability the backend enforces.
 */
export const PERMISSION_GROUPS = [
    { name: 'settings', permissions: ['view settings', 'manage settings'] },
    { name: 'goldRates', permissions: ['view gold rates', 'manage gold rates'] },
    { name: 'customers', permissions: ['view customers', 'manage customers'] },
    { name: 'inventory', permissions: ['view inventory', 'manage inventory'] },
    { name: 'sales', permissions: ['view sales', 'manage sales', 'void sales'] },
    { name: 'pawns', permissions: ['view pawns', 'manage pawns', 'forfeit pawns'] },
    { name: 'suppliers', permissions: ['view suppliers', 'manage suppliers'] },
    { name: 'purchases', permissions: ['view purchases', 'manage purchases'] },
    {
        name: 'accounts',
        permissions: ['view accounts', 'manage accounts', 'close accounts'],
    },
    { name: 'reports', permissions: ['view reports'] },
    { name: 'users', permissions: ['view users', 'manage users'] },
    { name: 'roles', permissions: ['view roles', 'manage roles'] },
    { name: 'activityLog', permissions: ['view activity log'] },
    { name: 'backups', permissions: ['manage backups'] },
]

/** Permission names in the order the API returns them, for a stable table. */
export const ALL_PERMISSIONS = PERMISSION_GROUPS.flatMap((group) => group.permissions)
