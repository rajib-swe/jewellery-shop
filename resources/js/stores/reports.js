import { ref } from 'vue'
import { defineStore } from 'pinia'
import {
    getCustomerLedgerReport,
    getDashboard,
    getInterestEarnedReport,
    getOverduePawnsReport,
    getPawnOutstandingReport,
    getProfitReport,
    getSalesReport,
    getStockReport,
    getSupplierLedgerReport,
} from '../api/reports'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useReportsStore = defineStore('reports', () => {
    const dashboard = ref(null)
    const sales = ref(null)
    const stock = ref(null)
    const pawnOutstanding = ref(null)
    const overduePawns = ref(null)
    const interestEarned = ref(null)
    const profit = ref(null)
    const customerLedger = ref(null)
    const supplierLedger = ref(null)
    const loadingDashboard = ref(false)
    const loading = ref(false)
    const error = ref(null)

    async function fetchDashboard() {
        loadingDashboard.value = true
        error.value = null

        try {
            dashboard.value = await getDashboard()

            return dashboard.value
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to load the dashboard.')

            throw requestError
        } finally {
            loadingDashboard.value = false
        }
    }

    async function fetchSales(params) {
        return load((current) => { sales.value = current }, () => getSalesReport(params), 'Unable to load the sales report.')
    }

    async function fetchStock() {
        return load((current) => { stock.value = current }, getStockReport, 'Unable to load the stock report.')
    }

    async function fetchPawnOutstanding() {
        return load(
            (current) => { pawnOutstanding.value = current },
            getPawnOutstandingReport,
            'Unable to load the pawn report.',
        )
    }

    async function fetchOverduePawns() {
        return load(
            (current) => { overduePawns.value = current },
            getOverduePawnsReport,
            'Unable to load the overdue pawn report.',
        )
    }

    async function fetchInterestEarned(params) {
        return load(
            (current) => { interestEarned.value = current },
            () => getInterestEarnedReport(params),
            'Unable to load the interest report.',
        )
    }

    async function fetchProfit(params) {
        return load((current) => { profit.value = current }, () => getProfitReport(params), 'Unable to load the profit report.')
    }

    async function fetchCustomerLedger(customerId, params) {
        return load(
            (current) => { customerLedger.value = current },
            () => getCustomerLedgerReport(customerId, params),
            'Unable to load the customer ledger.',
        )
    }

    async function fetchSupplierLedger(supplierId) {
        return load(
            (current) => { supplierLedger.value = current },
            () => getSupplierLedgerReport(supplierId),
            'Unable to load the supplier ledger.',
        )
    }

    /**
     * Shared fetch so every report clears the previous error, shows the loading
     * state and stores the result the same way.
     */
    async function load(assign, request, fallback) {
        loading.value = true
        error.value = null

        try {
            const result = await request()

            assign(result)

            return result
        } catch (requestError) {
            error.value = errorMessage(requestError, fallback)

            throw requestError
        } finally {
            loading.value = false
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        dashboard,
        sales,
        stock,
        pawnOutstanding,
        overduePawns,
        interestEarned,
        profit,
        customerLedger,
        supplierLedger,
        loadingDashboard,
        loading,
        error,
        fetchDashboard,
        fetchSales,
        fetchStock,
        fetchPawnOutstanding,
        fetchOverduePawns,
        fetchInterestEarned,
        fetchProfit,
        fetchCustomerLedger,
        fetchSupplierLedger,
        clearError,
    }
})
