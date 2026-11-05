import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    createUser,
    deleteUser,
    listUsers,
    updateUser,
} from '../api/users'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useUsersStore = defineStore('users', () => {
    const items = ref([])
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loading = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let listRequest = null
    let listRequestKey = null

    async function fetchList(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && listRequest && listRequestKey === requestKey) {
            return listRequest
        }

        loading.value = true
        const request = listUsers(params)
        listRequest = request
        listRequestKey = requestKey

        try {
            const response = await request

            if (listRequest === request) {
                items.value = response.data
                Object.assign(meta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (listRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load users.')
            }

            throw requestError
        } finally {
            if (listRequest === request) {
                listRequest = null
                listRequestKey = null
                loading.value = false
            }
        }
    }

    async function saveUser(payload, userId = null) {
        saving.value = true
        error.value = null

        try {
            const user = userId === null
                ? await createUser(payload)
                : await updateUser(userId, payload)

            return user
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the user.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function removeUser(userId) {
        saving.value = true
        error.value = null

        try {
            await deleteUser(userId)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the user.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        items,
        meta,
        loading,
        saving,
        error,
        fetchList,
        saveUser,
        removeUser,
        clearError,
    }
})
