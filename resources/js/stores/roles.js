import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import { listPermissions, listRoles, updateRole } from '../api/roles'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useRolesStore = defineStore('roles', () => {
    const roles = ref([])
    const permissions = ref([])
    // The matrix is edited locally and only written when the user saves, so a
    // stray click never silently changes what a cashier can do.
    const draft = reactive({})
    const loading = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let listRequest = null

    async function fetchAll(force = false) {
        if (!force && listRequest) {
            return listRequest
        }

        loading.value = true
        const request = Promise.all([listRoles(), listPermissions()])
        listRequest = request

        try {
            const [loadedRoles, loadedPermissions] = await request

            if (listRequest === request) {
                roles.value = loadedRoles
                permissions.value = loadedPermissions
                resetDraft()
            }

            return loadedRoles
        } catch (requestError) {
            if (listRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the roles.')
            }

            throw requestError
        } finally {
            if (listRequest === request) {
                listRequest = null
                loading.value = false
            }
        }
    }

    function resetDraft() {
        Object.keys(draft).forEach((roleName) => delete draft[roleName])

        roles.value.forEach((role) => {
            draft[role.name] = [...role.permissions]
        })
    }

    function togglePermission(roleName, permission) {
        const current = draft[roleName] ?? []

        draft[roleName] = current.includes(permission)
            ? current.filter((name) => name !== permission)
            : [...current, permission]
    }

    function isSelected(roleName, permission) {
        return (draft[roleName] ?? []).includes(permission)
    }

    function isDirty(roleName) {
        const role = roles.value.find((item) => item.name === roleName)

        if (!role) {
            return false
        }

        const current = [...(draft[roleName] ?? [])].sort()
        const saved = [...role.permissions].sort()

        return current.join('|') !== saved.join('|')
    }

    async function saveRole(roleName) {
        saving.value = true
        error.value = null

        try {
            const role = await updateRole(roleName, draft[roleName] ?? [])
            const index = roles.value.findIndex((item) => item.name === roleName)

            if (index !== -1) {
                roles.value.splice(index, 1, role)
            }

            draft[roleName] = [...role.permissions]

            return role
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the role.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        roles,
        permissions,
        draft,
        loading,
        saving,
        error,
        fetchAll,
        resetDraft,
        togglePermission,
        isSelected,
        isDirty,
        saveRole,
        clearError,
    }
})
