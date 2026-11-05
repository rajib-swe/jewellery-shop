import { ref } from 'vue'
import { defineStore } from 'pinia'
import { createBackup, deleteBackup, listBackups } from '../api/backups'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useBackupsStore = defineStore('backups', () => {
    const items = ref([])
    const keepDays = ref(0)
    const loading = ref(false)
    const running = ref(false)
    const error = ref(null)
    let listRequest = null

    async function fetchList(force = false) {
        if (!force && listRequest) {
            return listRequest
        }

        loading.value = true
        const request = listBackups()
        listRequest = request

        try {
            const response = await request

            if (listRequest === request) {
                items.value = response.data ?? []
                keepDays.value = response.meta?.keep_days ?? 0
            }

            return response
        } catch (requestError) {
            if (listRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the backups.')
            }

            throw requestError
        } finally {
            if (listRequest === request) {
                listRequest = null
                loading.value = false
            }
        }
    }

    async function run() {
        running.value = true
        error.value = null

        try {
            return await createBackup()
        } catch (requestError) {
            error.value = errorMessage(requestError, 'The backup could not be taken.')
            throw requestError
        } finally {
            running.value = false
        }
    }

    async function remove(name) {
        error.value = null

        try {
            await deleteBackup(name)
            items.value = items.value.filter((backup) => backup.name !== name)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the backup.')
            throw requestError
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        items,
        keepDays,
        loading,
        running,
        error,
        fetchList,
        run,
        remove,
        clearError,
    }
})
