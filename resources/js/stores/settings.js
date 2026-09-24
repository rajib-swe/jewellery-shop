import { ref } from 'vue'
import { defineStore } from 'pinia'
import { getSettings, saveSettings } from '../api/settings'

const defaultSettings = {
    shop_name: 'Jewellery Shop',
    shop_address: '',
    shop_phone: '',
    shop_logo: null,
    shop_logo_url: null,
    vat_percentage: '0.00',
    currency_symbol: '৳',
    weight_unit: 'gram',
    default_pawn_interest_rate: '0.00',
    invoice_footer: '',
}

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useSettingsStore = defineStore('settings', () => {
    const settings = ref({ ...defaultSettings })
    const loaded = ref(false)
    const loading = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let request = null

    async function fetchSettings(force = false) {
        if (loaded.value && !force) {
            return settings.value
        }

        if (request) {
            return request
        }

        loading.value = true
        error.value = null
        request = (async () => {
            const response = await getSettings()
            settings.value = { ...defaultSettings, ...response }
            loaded.value = true

            return settings.value
        })()

        try {
            return await request
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to load settings.')
            throw requestError
        } finally {
            request = null
            loading.value = false
        }
    }

    async function update(payload) {
        saving.value = true
        error.value = null

        try {
            const response = await saveSettings(payload)
            settings.value = { ...defaultSettings, ...response }
            loaded.value = true

            return settings.value
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save settings.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearError() {
        error.value = null
    }

    return {
        settings,
        loaded,
        loading,
        saving,
        error,
        fetchSettings,
        update,
        clearError,
    }
})
