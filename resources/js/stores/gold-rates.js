import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    createGoldRate,
    deleteGoldRate,
    getLatestGoldRates,
    listGoldRates,
    updateGoldRate,
} from '../api/gold-rates'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useGoldRatesStore = defineStore('gold-rates', () => {
    const latest = ref([])
    const history = ref([])
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loadingLatest = ref(false)
    const loadingHistory = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let latestRequest = null
    let historyRequest = null
    let historyRequestKey = null

    async function fetchLatest(force = false) {
        if (latestRequest && !force) {
            return latestRequest
        }

        loadingLatest.value = true
        const request = getLatestGoldRates()
        latestRequest = request

        try {
            const response = await request

            if (latestRequest === request) {
                latest.value = response
            }

            return response
        } catch (requestError) {
            if (latestRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load current gold rates.')
            }

            throw requestError
        } finally {
            if (latestRequest === request) {
                latestRequest = null
                loadingLatest.value = false
            }
        }
    }

    async function fetchHistory(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && historyRequest && historyRequestKey === requestKey) {
            return historyRequest
        }

        loadingHistory.value = true
        const request = listGoldRates(params)
        historyRequest = request
        historyRequestKey = requestKey

        try {
            const response = await request

            if (historyRequest === request) {
                history.value = response.data
                Object.assign(meta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (historyRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load gold-rate history.')
            }

            throw requestError
        } finally {
            if (historyRequest === request) {
                historyRequest = null
                historyRequestKey = null
                loadingHistory.value = false
            }
        }
    }

    async function saveRate(payload, goldRateId = null) {
        saving.value = true
        error.value = null
        let response

        try {
            response = goldRateId === null
                ? await createGoldRate(payload)
                : await updateGoldRate(goldRateId, payload)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the gold rate.')
            throw requestError
        } finally {
            saving.value = false
        }

        try {
            await fetchLatest(true)
        } catch (refreshError) {
            error.value = errorMessage(refreshError, 'The rate was saved, but the current-rate display could not be refreshed.')
        }

        return response
    }

    async function removeRate(goldRateId) {
        saving.value = true
        error.value = null

        try {
            await deleteGoldRate(goldRateId)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the gold rate.')
            throw requestError
        } finally {
            saving.value = false
        }

        const refreshResults = await Promise.allSettled([
            fetchLatest(true),
            fetchHistory({}, true),
        ])

        if (refreshResults.some((result) => result.status === 'rejected')) {
            error.value = 'The rate was deleted, but the list could not be fully refreshed.'
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        latest,
        history,
        meta,
        loadingLatest,
        loadingHistory,
        saving,
        error,
        fetchLatest,
        fetchHistory,
        saveRate,
        removeRate,
        clearError,
    }
})
