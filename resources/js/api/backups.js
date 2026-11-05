import client from './client'

export async function listBackups() {
    const { data } = await client.get('/backups')

    return data
}

export async function createBackup() {
    const { data } = await client.post('/backups')

    return data
}

export async function deleteBackup(name) {
    await client.delete(`/backups/${encodeURIComponent(name)}`)
}

// Backups are a plain file download rather than a JSON payload, so it goes
// through the web client and its own axios instance with the session cookie.
export function backupDownloadUrl(name) {
    return `/api/v1/backups/${encodeURIComponent(name)}/download`
}
