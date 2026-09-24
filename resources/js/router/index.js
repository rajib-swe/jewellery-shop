import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '../layouts/AppLayout.vue'
import AccessDenied from '../pages/AccessDenied.vue'
import Dashboard from '../pages/Dashboard.vue'
import GoldRates from '../pages/gold-rates/GoldRates.vue'
import Login from '../pages/Login.vue'
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
                    path: 'settings',
                    name: 'settings',
                    component: Settings,
                    meta: { permission: 'view settings' },
                },
            ],
        },
        {
            path: '/access-denied',
            name: 'access-denied',
            component: AccessDenied,
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'dashboard' },
        },
    ],
})

router.beforeEach(async (to) => {
    const authStore = useAuthStore()

    if (!authStore.initialized) {
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
