import client from './client'

export async function listUsers(params = {}) {
    const { data } = await client.get('/users', { params })

    return data
}

export async function createUser(payload) {
    const { data } = await client.post('/users', payload)

    return data.data
}

export async function updateUser(userId, payload) {
    const { data } = await client.put(`/users/${userId}`, payload)

    return data.data
}

export async function deleteUser(userId) {
    await client.delete(`/users/${userId}`)
}
