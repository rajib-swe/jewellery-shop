import axios from 'axios'

const LOCALE_STORAGE_KEY = 'jewellery-shop.locale'

function currentLocale() {
    try {
        const stored = window.localStorage.getItem(LOCALE_STORAGE_KEY)

        return stored === 'en' ? 'en' : 'bn'
    } catch {
        return 'bn'
    }
}

function withLocaleHeader(config) {
    config.headers = config.headers ?? {}
    config.headers['Accept-Language'] = currentLocale()

    return config
}

const defaultHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
}

// Endpoints where a 401 is an expected answer rather than an expired session.
const SILENT_AUTH_PATHS = ['/login', '/me']

let unauthorizedHandler = null

export function setUnauthorizedHandler(handler) {
    unauthorizedHandler = handler
}

function isSessionExpiry(url, status) {
    if (![401, 419].includes(status)) {
        return false
    }

    const path = new URL(url, window.location.origin).pathname

    return !SILENT_AUTH_PATHS.some((prefix) => path.endsWith(prefix))
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

// An installed app can stay open for days, so the session cookie expires long
// before the tab does. Surface it once instead of letting every call fail quietly.
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status

        if (status && isSessionExpiry(error.config?.url ?? '', status)) {
            unauthorizedHandler?.()
        }

        return Promise.reject(error)
    },
)

for (const client of [apiClient, webClient]) {
    client.interceptors.request.use(withLocaleHeader)
}

export default apiClient
