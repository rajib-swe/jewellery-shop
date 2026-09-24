import apiClient, { webClient } from './client'

export async function getCsrfCookie() {
    await webClient.get('/sanctum/csrf-cookie')
}

export async function authenticate(credentials) {
    await getCsrfCookie()

    const { data } = await apiClient.post('/login', credentials)

    return data.data
}

export async function fetchCurrentUser() {
    const { data } = await apiClient.get('/me')

    return data.data
}

export async function logoutCurrentUser() {
    await apiClient.post('/logout')
}
