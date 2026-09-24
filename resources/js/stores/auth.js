import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authenticate, fetchCurrentUser, logoutCurrentUser } from '../api/auth'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const initialized = ref(false)
    const loading = ref(false)
    const submitting = ref(false)

    const isAuthenticated = computed(() => user.value !== null)
    const roles = computed(() => user.value?.roles ?? [])
    const primaryRole = computed(() => roles.value[0] ?? '')

    function can(permission) {
        return user.value?.permissions?.includes(permission) ?? false
    }

    async function fetchUser() {
        if (initialized.value || loading.value) {
            return
        }

        loading.value = true

        try {
            user.value = await fetchCurrentUser()
        } catch (error) {
            if (![401, 403].includes(error.response?.status)) {
                throw error
            }

            user.value = null
        } finally {
            initialized.value = true
            loading.value = false
        }
    }

    async function login(credentials) {
        submitting.value = true

        try {
            const authenticatedUser = await authenticate(credentials)

            if (!authenticatedUser.permissions.includes('access api')) {
                await logoutCurrentUser().catch(() => {})
                throw new Error('Your account does not have application access.')
            }

            user.value = authenticatedUser
            initialized.value = true
        } finally {
            submitting.value = false
        }
    }

    async function logout() {
        await logoutCurrentUser()

        user.value = null
        initialized.value = true
    }

    return {
        user,
        initialized,
        loading,
        submitting,
        isAuthenticated,
        roles,
        primaryRole,
        can,
        fetchUser,
        login,
        logout,
    }
})
