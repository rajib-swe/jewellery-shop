import { computed } from 'vue'
import { useLocaleStore } from '../stores/locale'
import { useSettingsStore } from '../stores/settings'
import { gramsToAna, gramsToPoint, gramsToRoti, gramsToVori } from './weight'

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

        switch (weightUnit.value) {
            case 'vori':
                return `${gramsToVori(value).toFixed(4)} ${weightSuffix()}`
            case 'ana':
                return `${gramsToAna(value).toFixed(2)} ${weightSuffix()}`
            case 'roti':
                return `${gramsToRoti(value).toFixed(3)} ${weightSuffix()}`
            case 'point':
                return `${gramsToPoint(value).toFixed(1)} ${weightSuffix()}`
            default:
                return `${value.toFixed(3)} ${localeStore.t('units.gram')}`
        }
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
