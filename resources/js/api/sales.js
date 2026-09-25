import client from './client'

export async function listSales(params = {}) {
    const { data } = await client.get('/sales', { params })

    return data
}

export async function getSale(saleId) {
    const { data } = await client.get(`/sales/${saleId}`)

    return data.data
}

export async function createSale(payload) {
    const { data } = await client.post('/sales', payload)

    return data.data
}

export async function addSalePayment(saleId, payload) {
    const { data } = await client.post(`/sales/${saleId}/payments`, payload)

    return data.data
}

export async function voidSale(saleId, payload = {}) {
    const { data } = await client.post(`/sales/${saleId}/void`, payload)

    return data.data
}
