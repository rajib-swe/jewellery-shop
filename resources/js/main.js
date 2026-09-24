import { createPinia } from 'pinia'
import { createApp } from 'vue'
import { createVuetify } from 'vuetify'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import App from './App.vue'
import router from './router'

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

app.use(createPinia())
app.use(vuetify)
app.use(router)
app.mount('#app')
