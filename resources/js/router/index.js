import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '../layouts/AppLayout.vue'
import AccessDenied from '../pages/AccessDenied.vue'
import Login from '../pages/Login.vue'
import PublicHome from '../pages/PublicHome.vue'
import { useAuthStore } from '../stores/auth'

// Only the public entry points and the layout are eager. Every screen behind the
// login loads on demand, so signing in does not download the whole back office.
const Dashboard = () => import('../pages/Dashboard.vue')
const GoldRates = () => import('../pages/gold-rates/GoldRates.vue')
const Categories = () => import('../pages/inventory/Categories.vue')
const ItemForm = () => import('../pages/inventory/ItemForm.vue')
const ItemLabels = () => import('../pages/inventory/ItemLabels.vue')
const Items = () => import('../pages/inventory/Items.vue')
const StockSummary = () => import('../pages/inventory/StockSummary.vue')
const CustomerForm = () => import('../pages/customers/CustomerForm.vue')
const CustomerShow = () => import('../pages/customers/CustomerShow.vue')
const Customers = () => import('../pages/customers/Customers.vue')
const PurchaseForm = () => import('../pages/purchases/PurchaseForm.vue')
const PurchaseShow = () => import('../pages/purchases/PurchaseShow.vue')
const Purchases = () => import('../pages/purchases/Purchases.vue')
const SupplierForm = () => import('../pages/suppliers/SupplierForm.vue')
const SupplierShow = () => import('../pages/suppliers/SupplierShow.vue')
const Suppliers = () => import('../pages/suppliers/Suppliers.vue')
const PawnForm = () => import('../pages/pawns/PawnForm.vue')
const PawnShow = () => import('../pages/pawns/PawnShow.vue')
const Pawns = () => import('../pages/pawns/Pawns.vue')
const SaleEntry = () => import('../pages/sales/SaleEntry.vue')
const Sales = () => import('../pages/sales/Sales.vue')
const SaleShow = () => import('../pages/sales/SaleShow.vue')
const Settings = () => import('../pages/settings/Settings.vue')

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: Login,
            meta: { guestOnly: true },
        },
        {
            path: '/',
            name: 'home',
            component: PublicHome,
            meta: { public: true },
        },
        {
            path: '/app',
            component: AppLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: '',
                    redirect: { name: 'dashboard' },
                },
                {
                    path: 'dashboard',
                    name: 'dashboard',
                    component: Dashboard,
                    meta: { permission: 'access api' },
                },
                {
                    path: 'gold-rates',
                    name: 'gold-rates',
                    component: GoldRates,
                    meta: { permission: 'view gold rates' },
                },
                {
                    path: 'inventory/items',
                    name: 'items',
                    component: Items,
                    meta: { permission: 'view inventory' },
                },
                {
                    path: 'inventory/items/new',
                    name: 'item-create',
                    component: ItemForm,
                    meta: { permission: 'manage inventory' },
                },
                {
                    path: 'inventory/items/:id/edit',
                    name: 'item-edit',
                    component: ItemForm,
                    meta: { permission: 'manage inventory' },
                },
                {
                    path: 'inventory/summary',
                    name: 'stock-summary',
                    component: StockSummary,
                    meta: { permission: 'view inventory' },
                },
                {
                    path: 'inventory/labels',
                    name: 'item-labels',
                    component: ItemLabels,
                    meta: { permission: 'view inventory' },
                },
                {
                    path: 'inventory/categories',
                    name: 'categories',
                    component: Categories,
                    meta: { permission: 'view inventory' },
                },
                {
                    path: 'customers',
                    name: 'customers',
                    component: Customers,
                    meta: { permission: 'view customers' },
                },
                {
                    path: 'customers/new',
                    name: 'customer-create',
                    component: CustomerForm,
                    meta: { permission: 'manage customers' },
                },
                {
                    path: 'customers/:id/edit',
                    name: 'customer-edit',
                    component: CustomerForm,
                    meta: { permission: 'manage customers' },
                },
                {
                    path: 'customers/:id',
                    name: 'customer-profile',
                    component: CustomerShow,
                    meta: { permission: 'view customers' },
                },
                {
                    path: 'sales',
                    name: 'sales',
                    component: Sales,
                    meta: { permission: 'view sales' },
                },
                {
                    path: 'sales/new',
                    name: 'sale-create',
                    component: SaleEntry,
                    meta: { permission: 'manage sales' },
                },
                {
                    path: 'sales/:id',
                    name: 'sale-profile',
                    component: SaleShow,
                    meta: { permission: 'view sales' },
                },
                {
                    path: 'pawns',
                    name: 'pawns',
                    component: Pawns,
                    meta: { permission: 'view pawns' },
                },
                {
                    path: 'pawns/new',
                    name: 'pawn-create',
                    component: PawnForm,
                    meta: { permission: 'manage pawns' },
                },
                {
                    path: 'pawns/:id',
                    name: 'pawn-profile',
                    component: PawnShow,
                    meta: { permission: 'view pawns' },
                },
                {
                    path: 'purchases',
                    name: 'purchases',
                    component: Purchases,
                    meta: { permission: 'view purchases' },
                },
                {
                    path: 'purchases/new',
                    name: 'purchase-create',
                    component: PurchaseForm,
                    meta: { permission: 'manage purchases' },
                },
                {
                    path: 'purchases/:id',
                    name: 'purchase-profile',
                    component: PurchaseShow,
                    meta: { permission: 'view purchases' },
                },
                {
                    path: 'suppliers',
                    name: 'suppliers',
                    component: Suppliers,
                    meta: { permission: 'view suppliers' },
                },
                {
                    path: 'suppliers/new',
                    name: 'supplier-create',
                    component: SupplierForm,
                    meta: { permission: 'manage suppliers' },
                },
                {
                    path: 'suppliers/:id/edit',
                    name: 'supplier-edit',
                    component: SupplierForm,
                    meta: { permission: 'manage suppliers' },
                },
                {
                    path: 'suppliers/:id',
                    name: 'supplier-profile',
                    component: SupplierShow,
                    meta: { permission: 'view suppliers' },
                },
                {
                    path: 'settings',
                    name: 'settings',
                    component: Settings,
                    meta: { permission: 'view settings' },
                },
            ],
        },
        {
            path: '/dashboard',
            redirect: { name: 'dashboard' },
        },
        {
            path: '/gold-rates',
            redirect: { name: 'gold-rates' },
        },
        {
            path: '/sales',
            redirect: { name: 'sales' },
        },
        {
            path: '/pawns',
            redirect: { name: 'pawns' },
        },
        {
            path: '/purchases',
            redirect: { name: 'purchases' },
        },
        {
            path: '/suppliers',
            redirect: { name: 'suppliers' },
        },
        {
            path: '/settings',
            redirect: { name: 'settings' },
        },
        {
            path: '/access-denied',
            name: 'access-denied',
            component: AccessDenied,
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'home' },
        },
    ],
})

router.beforeEach(async (to) => {
    const authStore = useAuthStore()

    if (!authStore.initialized && (to.meta.requiresAuth || to.meta.guestOnly)) {
        try {
            await authStore.fetchUser()
        } catch {
            return { name: 'login' }
        }
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return {
            name: 'login',
            query: { redirect: to.fullPath },
        }
    }

    if (to.meta.permission && !authStore.can(to.meta.permission)) {
        return { name: 'access-denied' }
    }

    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return { name: 'dashboard' }
    }
})

export default router
