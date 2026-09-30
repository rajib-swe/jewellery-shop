import { createPinia } from 'pinia'
import { createApp } from 'vue'
import { createVuetify } from 'vuetify'
import '../css/mdi-icons.css'
import 'vuetify/styles'
import App from './App.vue'
import CustomerPicker from './components/CustomerPicker.vue'
import router from './router'
import { setUnauthorizedHandler } from './api/client'
import { useAuthStore } from './stores/auth'
import { useLocaleStore } from './stores/locale'
import { usePwaStore } from './stores/pwa'

const vuetify = createVuetify({
    icons: {
        defaultSet: 'mdi',
    },
    theme: {
        defaultTheme: 'jewelleryLight',
        themes: {
            jewelleryLight: {
                dark: false,
                colors: {
                    background: '#f8f5ef',
                    surface: '#ffffff',
                    primary: '#8a6a32',
                    secondary: '#4f5d75',
                    accent: '#c49a54',
                    error: '#b3261e',
                    info: '#2f6f9f',
                    success: '#2e7d5b',
                    warning: '#a66a00',
                },
            },
        },
    },
})

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.component('CustomerPicker', CustomerPicker)
app.use(vuetify)
app.use(router)

const localeStore = useLocaleStore(pinia)

app.config.globalProperties.$t = localeStore.t

const pwaStore = usePwaStore(pinia)

pwaStore.initialise()

setUnauthorizedHandler(() => {
    const authStore = useAuthStore(pinia)

    if (!authStore.isAuthenticated) {
        return
    }

    authStore.clearSession({ expired: true })

    if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' })
    }
})

app.mount('#app')
