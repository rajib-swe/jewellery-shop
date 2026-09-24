import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    createCustomer,
    deleteCustomer,
    getCustomer,
    getCustomerHistory,
    listCustomers,
    updateCustomer,
} from '../api/customers'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useCustomersStore = defineStore('customers', () => {
    const items = ref([])
    const current = ref(null)
    const history = ref({
        sales: [],
        pawns: [],
        payments: [],
        due_balance: '0.00',
    })
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loading = ref(false)
    const loadingCurrent = ref(false)
    const loadingHistory = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let listRequest = null
    let listRequestKey = null
    let currentRequest = null
    let historyRequest = null

    async function fetchList(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && listRequest && listRequestKey === requestKey) {
            return listRequest
        }

        loading.value = true
        const request = listCustomers(params)
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
                error.value = errorMessage(requestError, 'Unable to load customers.')
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

    async function fetchCustomer(customerId, force = false) {
        if (!force && currentRequest) {
            return currentRequest
        }

        loadingCurrent.value = true
        const request = getCustomer(customerId)
        currentRequest = request

        try {
            const customer = await request

            if (currentRequest === request) {
                current.value = customer
            }

            return customer
        } catch (requestError) {
            if (currentRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the customer.')
            }

            throw requestError
        } finally {
            if (currentRequest === request) {
                currentRequest = null
                loadingCurrent.value = false
            }
        }
    }

    async function fetchHistory(customerId, force = false) {
        if (!force && historyRequest) {
            return historyRequest
        }

        loadingHistory.value = true
        const request = getCustomerHistory(customerId)
        historyRequest = request

        try {
            const customerHistory = await request

            if (historyRequest === request) {
                history.value = customerHistory
            }

            return customerHistory
        } catch (requestError) {
            if (historyRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load customer history.')
            }

            throw requestError
        } finally {
            if (historyRequest === request) {
                historyRequest = null
                loadingHistory.value = false
            }
        }
    }

    async function saveCustomer(payload, customerId = null) {
        saving.value = true
        error.value = null

        try {
            const customer = customerId === null
                ? await createCustomer(payload)
                : await updateCustomer(customerId, payload)

            if (customerId !== null) {
                current.value = customer
            }

            return customer
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the customer.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function removeCustomer(customerId) {
        saving.value = true
        error.value = null

        try {
            await deleteCustomer(customerId)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the customer.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearCurrent() {
        current.value = null
        history.value = {
            sales: [],
            pawns: [],
            payments: [],
            due_balance: '0.00',
        }
        error.value = null
    }

    function clearError() {
        error.value = null
    }

    return {
        items,
        current,
        history,
        meta,
        loading,
        loadingCurrent,
        loadingHistory,
        saving,
        error,
        fetchList,
        fetchCustomer,
        fetchHistory,
        saveCustomer,
        removeCustomer,
        clearCurrent,
        clearError,
    }
})
