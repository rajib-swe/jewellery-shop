import client from './client'

export async function listGoldRates(params = {}) {
    const { data } = await client.get('/gold-rates', { params })

    return data
}

export async function getLatestGoldRates() {
    const { data } = await client.get('/gold-rates/latest')

    return data.data
}

export async function createGoldRate(payload) {
    const { data } = await client.post('/gold-rates', payload)

    return data.data
}

export async function updateGoldRate(goldRateId, payload) {
    const { data } = await client.put(`/gold-rates/${goldRateId}`, payload)

    return data.data
}

export async function deleteGoldRate(goldRateId) {
    await client.delete(`/gold-rates/${goldRateId}`)
}
