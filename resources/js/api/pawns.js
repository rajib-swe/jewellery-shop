import client from './client'

export async function listPawns(params = {}) {
    const { data } = await client.get('/pawns', { params })

    return data
}

export async function getPawn(pawnId) {
    const { data } = await client.get(`/pawns/${pawnId}`)

    return data.data
}

export async function createPawn(payload) {
    const { data } = await client.post('/pawns', payload)

    return data.data
}

export async function addPawnPayment(pawnId, payload) {
    const { data } = await client.post(`/pawns/${pawnId}/payments`, payload)

    return data.data
}

export async function redeemPawn(pawnId, payload = {}) {
    const { data } = await client.post(`/pawns/${pawnId}/redeem`, payload)

    return data.data
}

export async function renewPawn(pawnId, payload = {}) {
    const { data } = await client.post(`/pawns/${pawnId}/renew`, payload)

    return data.data
}

export async function forfeitPawn(pawnId, payload = {}) {
    const { data } = await client.post(`/pawns/${pawnId}/forfeit`, payload)

    return data.data
}
