import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { registerSW } from 'virtual:pwa-register'

const INSTALL_DISMISS_KEY = 'jewellery-shop.install-dismissed'
const UPDATE_INTERVAL_MS = 60 * 60 * 1000

function readDismissed() {
    try {
        return window.localStorage.getItem(INSTALL_DISMISS_KEY) === '1'
    } catch {
        return false
    }
}

function writeDismissed(value) {
    try {
        window.localStorage.setItem(INSTALL_DISMISS_KEY, value ? '1' : '0')
    } catch {
        // Storage is unavailable in private browsing; the prompt simply returns later.
    }
}

function detectStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.navigator.standalone === true
}

function detectIosSafari() {
    const userAgent = window.navigator.userAgent
    const isIosDevice = /iPad|iPhone|iPod/.test(userAgent)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1)
    const isIosBrowser = /Safari/.test(userAgent) && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(userAgent)

    return isIosDevice && isIosBrowser
}

function detectTouchPrimary() {
    return window.matchMedia('(pointer: coarse)').matches
}

export const usePwaStore = defineStore('pwa', () => {
    const online = ref(window.navigator.onLine)
    const installed = ref(detectStandalone())
    const standalone = ref(installed.value)
    const isIos = ref(detectIosSafari())
    const isTouch = ref(detectTouchPrimary())
    const installAvailable = ref(false)
    const installDismissed = ref(readDismissed())
    const updateReady = ref(false)
    const offlineReady = ref(false)

    let deferredPrompt = null
    let updateServiceWorker = null
    let updateTimer = null

    const canInstall = computed(() => !standalone.value && !installDismissed.value && (
        installAvailable.value || (isIos.value && isTouch.value)
    ))

    function setOnline(value) {
        online.value = value
    }

    function handleInstallPrompt(event) {
        event.preventDefault()
        deferredPrompt = event
        installAvailable.value = true
    }

    function handleInstalled() {
        standalone.value = true
        installed.value = true
        installAvailable.value = false
        deferredPrompt = null
    }

    async function promptInstall() {
        if (!deferredPrompt) {
            return false
        }

        deferredPrompt.prompt()

        const { outcome } = await deferredPrompt.userChoice

        deferredPrompt = null
        installAvailable.value = false

        if (outcome === 'dismissed') {
            dismissInstall()
        }

        return outcome === 'accepted'
    }

    function dismissInstall() {
        installDismissed.value = true
        writeDismissed(true)
    }

    async function applyUpdate() {
        if (typeof updateServiceWorker !== 'function') {
            window.location.reload()

            return
        }

        await updateServiceWorker(true)
    }

    function initialise() {
        window.addEventListener('online', () => setOnline(true))
        window.addEventListener('offline', () => setOnline(false))
        window.addEventListener('beforeinstallprompt', handleInstallPrompt)
        window.addEventListener('appinstalled', handleInstalled)

        updateServiceWorker = registerSW({
            immediate: true,
            onNeedRefresh() {
                updateReady.value = true
            },
            onOfflineReady() {
                offlineReady.value = true
            },
            onRegisterError(error) {
                console.error('Service worker registration failed.', error)
            },
            onRegisteredSW(_url, registration) {
                if (!registration) {
                    return
                }

                window.clearInterval(updateTimer)
                updateTimer = window.setInterval(() => registration.update(), UPDATE_INTERVAL_MS)
            },
        })
    }

    function teardown() {
        window.clearInterval(updateTimer)
    }

    return {
        online,
        installed,
        standalone,
        isIos,
        isTouch,
        installAvailable,
        installDismissed,
        updateReady,
        offlineReady,
        canInstall,
        promptInstall,
        dismissInstall,
        applyUpdate,
        initialise,
        teardown,
    }
})
