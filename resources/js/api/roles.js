import client from './client'

export async function listRoles() {
    const { data } = await client.get('/roles')

    return data.data
}

export async function listPermissions() {
    const { data } = await client.get('/permissions')

    return data.data
}

export async function updateRole(roleName, permissions) {
    const { data } = await client.put(`/roles/${roleName}`, { permissions })

    return data.data
}
