import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    addSalePayment,
    createSale,
    getSale,
    listSales,
    voidSale,
} from '../api/sales'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useSalesStore = defineStore('sales', () => {
    const sales = ref([])
    const currentSale = ref(null)
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loadingSales = ref(false)
    const loadingCurrent = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let salesRequest = null
    let salesRequestKey = null

    async function fetchSales(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && salesRequest && salesRequestKey === requestKey) {
            return salesRequest
        }

        loadingSales.value = true
        const request = listSales(params)
        salesRequest = request
        salesRequestKey = requestKey

        try {
            const response = await request

            if (salesRequest === request) {
                sales.value = response.data
                Object.assign(meta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (salesRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load sales.')
            }

            throw requestError
        } finally {
            if (salesRequest === request) {
                salesRequest = null
                salesRequestKey = null
                loadingSales.value = false
            }
        }
    }

    async function fetchSale(saleId, force = false) {
        if (!force && loadingCurrent.value) {
            return currentSale.value
        }

        loadingCurrent.value = true

        try {
            currentSale.value = await getSale(saleId)

            return currentSale.value
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to load the sale.')
            throw requestError
        } finally {
            loadingCurrent.value = false
        }
    }

    async function saveSale(payload) {
        saving.value = true
        error.value = null

        try {
            const sale = await createSale(payload)

            currentSale.value = sale

            return sale
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the sale.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function recordPayment(saleId, payload) {
        saving.value = true
        error.value = null

        try {
            const sale = await addSalePayment(saleId, payload)

            currentSale.value = sale

            return sale
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the payment.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function voidCurrentSale(saleId, reason = null) {
        saving.value = true
        error.value = null

        try {
            const sale = await voidSale(saleId, { reason })

            currentSale.value = sale

            return sale
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to void the sale.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearCurrent() {
        currentSale.value = null
    }

    function clearError() {
        error.value = null
    }

    return {
        sales,
        currentSale,
        meta,
        loadingSales,
        loadingCurrent,
        saving,
        error,
        fetchSales,
        fetchSale,
        saveSale,
        recordPayment,
        voidCurrentSale,
        clearCurrent,
        clearError,
    }
})
