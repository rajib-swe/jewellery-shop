import client from './client'

export async function listItems(params = {}) {
    const { data } = await client.get('/items', { params })

    return data
}

export async function getItem(itemId) {
    const { data } = await client.get(`/items/${itemId}`)

    return data.data
}

export async function createItem(payload) {
    const { data } = await client.post('/items', payload)

    return data.data
}

export async function updateItem(itemId, payload) {
    const { data } = await client.put(`/items/${itemId}`, payload)

    return data.data
}

export async function deleteItem(itemId) {
    await client.delete(`/items/${itemId}`)
}

export async function adjustStock(itemId, payload) {
    const { data } = await client.post(`/items/${itemId}/stock-adjustments`, payload)

    return data.data
}

export async function getStockSummary() {
    const { data } = await client.get('/stock/summary')

    return data.data
}
