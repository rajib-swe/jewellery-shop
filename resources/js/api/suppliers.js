import client from './client'

export async function listSuppliers(params = {}) {
    const { data } = await client.get('/suppliers', { params })

    return data
}

export async function getSupplier(supplierId) {
    const { data } = await client.get(`/suppliers/${supplierId}`)

    return data.data
}

export async function createSupplier(payload) {
    const { data } = await client.post('/suppliers', payload)

    return data.data
}

export async function updateSupplier(supplierId, payload) {
    const { data } = await client.put(`/suppliers/${supplierId}`, payload)

    return data.data
}

export async function deleteSupplier(supplierId) {
    await client.delete(`/suppliers/${supplierId}`)
}

export async function getSupplierLedger(supplierId) {
    const { data } = await client.get(`/suppliers/${supplierId}/ledger`)

    return data.data
}

export async function addSupplierPayment(supplierId, payload) {
    const { data } = await client.post(`/suppliers/${supplierId}/payments`, payload)

    return data.data
}
