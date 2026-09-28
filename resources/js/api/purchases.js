import client from './client'

export async function listPurchases(params = {}) {
    const { data } = await client.get('/purchases', { params })

    return data
}

export async function getPurchase(purchaseId) {
    const { data } = await client.get(`/purchases/${purchaseId}`)

    return data.data
}

export async function createPurchase(payload) {
    const { data } = await client.post('/purchases', payload)

    return data.data
}

export async function getPurchaseRates(date) {
    const { data } = await client.get('/purchases/rates', {
        params: { date: date || undefined },
    })

    return data.data
}
