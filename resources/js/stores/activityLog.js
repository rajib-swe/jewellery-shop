import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import { listActivityEntries, listActivityFilters } from '../api/activityLog'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useActivityLogStore = defineStore('activityLog', () => {
    const items = ref([])
    const meta = reactive({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    })
    // Only values that actually return rows are offered as filters, so the
    // dropdowns are built from the trail rather than from a hardcoded list.
    const filterOptions = ref({ users: [], log_names: [], events: [] })
    const loading = ref(false)
    const loadingFilters = ref(false)
    const error = ref(null)
    let listRequest = null
    let listRequestKey = null
    let filtersRequest = null

    async function fetchList(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && listRequest && listRequestKey === requestKey) {
            return listRequest
        }

        loading.value = true
        const request = listActivityEntries(params)
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
                error.value = errorMessage(requestError, 'Unable to load the activity log.')
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

    async function fetchFilters(force = false) {
        if (!force && filtersRequest) {
            return filtersRequest
        }

        loadingFilters.value = true
        const request = listActivityFilters()
        filtersRequest = request

        try {
            const options = await request

            if (filtersRequest === request) {
                filterOptions.value = options
            }

            return options
        } catch (requestError) {
            if (filtersRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the log filters.')
            }

            throw requestError
        } finally {
            if (filtersRequest === request) {
                filtersRequest = null
                loadingFilters.value = false
            }
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        items,
        meta,
        filterOptions,
        loading,
        loadingFilters,
        error,
        fetchList,
        fetchFilters,
        clearError,
    }
})
