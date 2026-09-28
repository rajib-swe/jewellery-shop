import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    addSupplierPayment,
    createSupplier,
    deleteSupplier,
    getSupplier,
    getSupplierLedger,
    listSuppliers,
    updateSupplier,
} from '../api/suppliers'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useSuppliersStore = defineStore('suppliers', () => {
    const items = ref([])
    const current = ref(null)
    const ledger = ref({
        supplier: null,
        balance: '0.00',
        total_purchases: '0.00',
        total_payments: '0.00',
        transactions: [],
    })
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loading = ref(false)
    const loadingCurrent = ref(false)
    const loadingLedger = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let listRequest = null
    let listRequestKey = null
    let currentRequest = null
    let ledgerRequest = null

    async function fetchList(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && listRequest && listRequestKey === requestKey) {
            return listRequest
        }

        loading.value = true
        const request = listSuppliers(params)
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
                error.value = errorMessage(requestError, 'Unable to load suppliers.')
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

    async function fetchSupplier(supplierId, force = false) {
        if (!force && currentRequest) {
            return currentRequest
        }

        loadingCurrent.value = true
        const request = getSupplier(supplierId)
        currentRequest = request

        try {
            const supplier = await request

            if (currentRequest === request) {
                current.value = supplier
            }

            return supplier
        } catch (requestError) {
            if (currentRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the supplier.')
            }

            throw requestError
        } finally {
            if (currentRequest === request) {
                currentRequest = null
                loadingCurrent.value = false
            }
        }
    }

    async function fetchLedger(supplierId, force = false) {
        if (!force && ledgerRequest) {
            return ledgerRequest
        }

        loadingLedger.value = true
        const request = getSupplierLedger(supplierId)
        ledgerRequest = request

        try {
            const supplierLedger = await request

            if (ledgerRequest === request) {
                ledger.value = supplierLedger
            }

            return supplierLedger
        } catch (requestError) {
            if (ledgerRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the supplier ledger.')
            }

            throw requestError
        } finally {
            if (ledgerRequest === request) {
                ledgerRequest = null
                loadingLedger.value = false
            }
        }
    }

    async function saveSupplier(payload, supplierId = null) {
        saving.value = true
        error.value = null

        try {
            const supplier = supplierId === null
                ? await createSupplier(payload)
                : await updateSupplier(supplierId, payload)

            if (supplierId !== null) {
                current.value = supplier
            }

            return supplier
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the supplier.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function removeSupplier(supplierId) {
        saving.value = true
        error.value = null

        try {
            await deleteSupplier(supplierId)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the supplier.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function recordPayment(supplierId, payload) {
        saving.value = true
        error.value = null

        try {
            const supplier = await addSupplierPayment(supplierId, payload)

            current.value = supplier

            return supplier
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the payment.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearCurrent() {
        current.value = null
        ledger.value = {
            supplier: null,
            balance: '0.00',
            total_purchases: '0.00',
            total_payments: '0.00',
            transactions: [],
        }
        error.value = null
    }

    function clearError() {
        error.value = null
    }

    return {
        items,
        current,
        ledger,
        meta,
        loading,
        loadingCurrent,
        loadingLedger,
        saving,
        error,
        fetchList,
        fetchSupplier,
        fetchLedger,
        saveSupplier,
        removeSupplier,
        recordPayment,
        clearCurrent,
        clearError,
    }
})
