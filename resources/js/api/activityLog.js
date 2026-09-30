import client from './client'

export async function listActivityEntries(params = {}) {
    const { data } = await client.get('/activity-log', { params })

    return data
}

export async function listActivityFilters() {
    const { data } = await client.get('/activity-log/filters')

    return data.data
}
