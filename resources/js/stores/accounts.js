import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    closeDay,
    createCashTransaction,
    createExpense,
    deleteExpense,
    getCashBookSummary,
    getDaySummary,
    listCashTransactions,
    listDailyClosings,
    listExpenses,
    reopenDay,
    updateExpense,
} from '../api/accounts'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useAccountsStore = defineStore('accounts', () => {
    const expenses = ref([])
    const transactions = ref([])
    const closings = ref([])
    const summary = ref(null)
    const expenseTotals = ref([])
    const expensesMeta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const transactionsMeta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loadingExpenses = ref(false)
    const loadingTransactions = ref(false)
    const loadingSummary = ref(false)
    const loadingClosings = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let listRequest = null
    let listRequestKey = null
    let transactionRequest = null
    let transactionRequestKey = null
    let summaryRequest = null
    let summaryRequestKey = null

    async function fetchExpenses(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && listRequest && listRequestKey === requestKey) {
            return listRequest
        }

        loadingExpenses.value = true
        const request = listExpenses(params)
        listRequest = request
        listRequestKey = requestKey

        try {
            const response = await request

            if (listRequest === request) {
                expenses.value = response.data
                Object.assign(expensesMeta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (listRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the expenses.')
            }

            throw requestError
        } finally {
            if (listRequest === request) {
                listRequest = null
                listRequestKey = null
                loadingExpenses.value = false
            }
        }
    }

    async function fetchTransactions(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && transactionRequest && transactionRequestKey === requestKey) {
            return transactionRequest
        }

        loadingTransactions.value = true
        const request = listCashTransactions(params)
        transactionRequest = request
        transactionRequestKey = requestKey

        try {
            const response = await request

            if (transactionRequest === request) {
                transactions.value = response.data
                Object.assign(transactionsMeta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (transactionRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the cash book.')
            }

            throw requestError
        } finally {
            if (transactionRequest === request) {
                transactionRequest = null
                transactionRequestKey = null
                loadingTransactions.value = false
            }
        }
    }

    /**
     * Opening, in, out and closing for one day, plus the per-method split.
     */
    async function fetchSummary(date = null, force = false) {
        const requestKey = String(date ?? '')

        if (!force && summaryRequest && summaryRequestKey === requestKey) {
            return summaryRequest
        }

        loadingSummary.value = true
        const request = getCashBookSummary(date)
        summaryRequest = request
        summaryRequestKey = requestKey

        try {
            const data = await request

            if (summaryRequest === request) {
                summary.value = data
            }

            return data
        } catch (requestError) {
            if (summaryRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the cash book summary.')
            }

            throw requestError
        } finally {
            if (summaryRequest === request) {
                summaryRequest = null
                summaryRequestKey = null
                loadingSummary.value = false
            }
        }
    }

    async function fetchClosings(force = false) {
        if (!force && loadingClosings.value) {
            return null
        }

        loadingClosings.value = true

        try {
            const response = await listDailyClosings()

            closings.value = response.data

            return response
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to load the daily closings.')
            throw requestError
        } finally {
            loadingClosings.value = false
        }
    }

    async function saveExpense(payload, expenseId = null) {
        saving.value = true
        error.value = null

        try {
            return expenseId === null
                ? await createExpense(payload)
                : await updateExpense(expenseId, payload)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the expense.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function removeExpense(expenseId) {
        saving.value = true
        error.value = null

        try {
            await deleteExpense(expenseId)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the expense.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    /**
     * Money that moves with no source document, such as a bank withdrawal.
     */
    async function recordCashEntry(payload) {
        saving.value = true
        error.value = null

        try {
            return await createCashTransaction(payload)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the cash entry.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function closeTheDay(payload) {
        saving.value = true
        error.value = null

        try {
            return await closeDay(payload)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to close the day.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function reopenTheDay(closingId) {
        saving.value = true
        error.value = null

        try {
            return await reopenDay(closingId)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to reopen the day.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        expenses,
        transactions,
        closings,
        summary,
        expenseTotals,
        expensesMeta,
        transactionsMeta,
        loadingExpenses,
        loadingTransactions,
        loadingSummary,
        loadingClosings,
        saving,
        error,
        fetchExpenses,
        fetchTransactions,
        fetchSummary,
        fetchClosings,
        saveExpense,
        removeExpense,
        recordCashEntry,
        closeTheDay,
        reopenTheDay,
        clearError,
    }
})
