import axios from 'axios'

const defaultHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
}

export const webClient = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: defaultHeaders,
})

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: defaultHeaders,
})

export default apiClient
