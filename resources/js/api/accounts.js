import client from './client'

export async function listExpenses(params = {}) {
    const { data } = await client.get('/expenses', { params })

    return data
}

export async function createExpense(payload) {
    const { data } = await client.post('/expenses', payload)

    return data.data
}

export async function updateExpense(expenseId, payload) {
    const { data } = await client.put(`/expenses/${expenseId}`, payload)

    return data.data
}

export async function deleteExpense(expenseId) {
    await client.delete(`/expenses/${expenseId}`)
}

export async function listCashTransactions(params = {}) {
    const { data } = await client.get('/cash-book', { params })

    return data
}

export async function getCashBookSummary(date = null) {
    const { data } = await client.get('/cash-book/summary', {
        params: { date: date || undefined },
    })

    return data.data
}

export async function createCashTransaction(payload) {
    const { data } = await client.post('/cash-book', payload)

    return data.data
}

export async function listDailyClosings(params = {}) {
    const { data } = await client.get('/daily-closings', { params })

    return data
}

export async function getDaySummary(date) {
    const { data } = await client.get('/daily-closings/show', {
        params: { date },
    })

    return data.data
}

export async function closeDay(payload) {
    const { data } = await client.post('/daily-closings', payload)

    return data.data
}

export async function reopenDay(closingId) {
    const { data } = await client.post(`/daily-closings/${closingId}/reopen`)

    return data.data
}
