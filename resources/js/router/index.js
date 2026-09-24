import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '../layouts/AppLayout.vue'
import AccessDenied from '../pages/AccessDenied.vue'
import Dashboard from '../pages/Dashboard.vue'
import GoldRates from '../pages/gold-rates/GoldRates.vue'
import CustomerForm from '../pages/customers/CustomerForm.vue'
import CustomerShow from '../pages/customers/CustomerShow.vue'
import Customers from '../pages/customers/Customers.vue'
import Login from '../pages/Login.vue'
import PublicHome from '../pages/PublicHome.vue'
import Settings from '../pages/settings/Settings.vue'
import { useAuthStore } from '../stores/auth'

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
