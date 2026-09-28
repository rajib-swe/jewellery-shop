import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import { createPurchase, getPurchase, getPurchaseRates, listPurchases } from '../api/purchases'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const usePurchasesStore = defineStore('purchases', () => {
    const purchases = ref([])
    const currentPurchase = ref(null)
    const rates = ref({})
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loadingPurchases = ref(false)
    const loadingCurrent = ref(false)
    const loadingRates = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let listRequest = null
    let listRequestKey = null
    let currentRequest = null
    let ratesRequest = null
    let ratesRequestKey = null

    async function fetchPurchases(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && listRequest && listRequestKey === requestKey) {
            return listRequest
        }

        loadingPurchases.value = true
        const request = listPurchases(params)
        listRequest = request
        listRequestKey = requestKey

        try {
            const response = await request

            if (listRequest === request) {
                purchases.value = response.data
                Object.assign(meta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (listRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load purchases.')
            }

            throw requestError
        } finally {
            if (listRequest === request) {
                listRequest = null
                listRequestKey = null
                loadingPurchases.value = false
            }
        }
    }

    async function fetchPurchase(purchaseId, force = false) {
        if (!force && currentRequest) {
            return currentRequest
        }

        loadingCurrent.value = true
        const request = getPurchase(purchaseId)
        currentRequest = request

        try {
            const purchase = await request

            if (currentRequest === request) {
                currentPurchase.value = purchase
            }

            return purchase
        } catch (requestError) {
            if (currentRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the purchase.')
            }

            throw requestError
        } finally {
            if (currentRequest === request) {
                currentRequest = null
                loadingCurrent.value = false
            }
        }
    }

    /**
     * The karat rates a purchase dated on `date` is priced at, so the form can
     * show a line cost before it is saved.
     */
    async function fetchRates(date = null, force = false) {
        const requestKey = String(date ?? '')

        if (!force && ratesRequest && ratesRequestKey === requestKey) {
            return ratesRequest
        }

        loadingRates.value = true
        const request = getPurchaseRates(date)
        ratesRequest = request
        ratesRequestKey = requestKey

        try {
            const response = await request

            if (ratesRequest === request) {
                rates.value = response
            }

            return response
        } catch (requestError) {
            if (ratesRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the purchase rates.')
            }

            throw requestError
        } finally {
            if (ratesRequest === request) {
                ratesRequest = null
                ratesRequestKey = null
                loadingRates.value = false
            }
        }
    }

    async function savePurchase(payload) {
        saving.value = true
        error.value = null

        try {
            const purchase = await createPurchase(payload)

            currentPurchase.value = purchase

            return purchase
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the purchase.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearCurrent() {
        currentPurchase.value = null
        error.value = null
    }

    function clearError() {
        error.value = null
    }

    return {
        purchases,
        currentPurchase,
        rates,
        meta,
        loadingPurchases,
        loadingCurrent,
        loadingRates,
        saving,
        error,
        fetchPurchases,
        fetchPurchase,
        fetchRates,
        savePurchase,
        clearCurrent,
        clearError,
    }
})
