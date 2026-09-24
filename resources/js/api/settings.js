import client from './client'

export async function getSettings() {
    const { data } = await client.get('/settings')

    return data.data
}

export async function saveSettings(payload) {
    const { data } = await client.put('/settings', payload)

    return data.data
}
