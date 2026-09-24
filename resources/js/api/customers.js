import client from './client'

export async function listCustomers(params = {}) {
    const { data } = await client.get('/customers', { params })

    return data
}

export async function getCustomer(customerId) {
    const { data } = await client.get(`/customers/${customerId}`)

    return data.data
}

export async function createCustomer(payload) {
    const { data } = await client.post('/customers', payload)

    return data.data
}

export async function updateCustomer(customerId, payload) {
    const { data } = await client.put(`/customers/${customerId}`, payload)

    return data.data
}

export async function deleteCustomer(customerId) {
    await client.delete(`/customers/${customerId}`)
}

export async function getCustomerHistory(customerId) {
    const { data } = await client.get(`/customers/${customerId}/history`)

    return data.data
}
