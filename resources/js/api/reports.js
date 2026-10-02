import { webClient } from './client'

const base = '/api/v1/reports'

async function unwrap(request) {
    const { data } = await request

    return data.data
}

export function getDashboard() {
    return unwrap(webClient.get(`${base}/dashboard`))
}

export function getSalesTrend(days = 30) {
    return unwrap(webClient.get(`${base}/sales-trend`, { params: { days } }))
}

export function getSalesReport(params = {}) {
    return unwrap(webClient.get(`${base}/sales`, { params }))
}

export function getStockReport() {
    return unwrap(webClient.get(`${base}/stock`))
}

export function getPawnOutstandingReport() {
    return unwrap(webClient.get(`${base}/pawn-outstanding`))
}

export function getOverduePawnsReport() {
    return unwrap(webClient.get(`${base}/overdue-pawns`))
}

export function getInterestEarnedReport(params = {}) {
    return unwrap(webClient.get(`${base}/interest-earned`, { params }))
}

export function getCustomerLedgerReport(customerId, params = {}) {
    return unwrap(webClient.get(`${base}/customers/${customerId}/ledger`, { params }))
}

export function getSupplierLedgerReport(supplierId) {
    return unwrap(webClient.get(`${base}/suppliers/${supplierId}/ledger`))
}

export function getProfitReport(params = {}) {
    return unwrap(webClient.get(`${base}/profit`, { params }))
}

/**
 * The absolute URL for an Excel download or a printout.
 *
 * Both are plain browser GETs against a session-authenticated endpoint, so they
 * go to the server rather than through the API client; the caller opens the
 * returned URL in a new tab.
 */
export function reportExportUrl(report, params = {}) {
    return exportUrl(`${base}/${report}/export`, params)
}

export function reportPrintUrl(report, params = {}) {
    const query = new URLSearchParams({ ...params, print: '1' })

    return `/reports/${report}/print?${query.toString()}`
}

function exportUrl(path, params) {
    const query = new URLSearchParams()

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, value)
        }
    })

    const search = query.toString()

    return search === '' ? path : `${path}?${search}`
}
