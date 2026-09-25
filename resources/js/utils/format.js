import { computed } from 'vue'
import { useLocaleStore } from '../stores/locale'
import { useSettingsStore } from '../stores/settings'
import { gramsToTraditional } from './weight'

export function useCurrency() {
    const settingsStore = useSettingsStore()

    return computed(() => settingsStore.settings.currency_symbol || '৳')
}

export function formatAmount(value, currencySymbol) {
    const amount = Number(value || 0)

    return `${currencySymbol}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

export function useWeightFormatter() {
    const localeStore = useLocaleStore()
    const settingsStore = useSettingsStore()
    const currencySymbol = useCurrency()

    const weightUnit = computed(() => settingsStore.settings.weight_unit || 'gram')

    function weightSuffix() {
        return localeStore.t(`units.${weightUnit.value}`)
    }

    function formatWeight(grams) {
        const value = Number(grams || 0)

        if (weightUnit.value === 'vori') {
            return `${gramsToTraditional(value).vori.toFixed(4)} ${weightSuffix()}`
        }

        return `${value.toFixed(3)} ${weightSuffix()}`
    }

    function formatMoney(value) {
        return formatAmount(value, currencySymbol.value)
    }

    return {
        currencySymbol,
        weightUnit,
        formatWeight,
        formatMoney,
    }
}
