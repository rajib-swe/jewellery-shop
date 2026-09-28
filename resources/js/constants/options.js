import { computed } from 'vue'
import { useLocaleStore } from '../stores/locale'
import { ITEM_STATUSES, KARAT_VALUES, MAKING_TYPES, STOCK_MOVEMENT_TYPES } from './inventory'
import { PAYMENT_METHODS, SALE_STATUSES } from './sales'
import { GOLD_KARAT_VALUES } from './gold-rates'

export function useOptionLabels() {
    const localeStore = useLocaleStore()

    const karatOptions = computed(() => KARAT_VALUES.map((value) => ({
        title: `${value}K`,
        value,
    })))

    const goldKaratOptions = computed(() => GOLD_KARAT_VALUES.map((value) => ({
        title: `${value}K`,
        value,
    })))

    const makingTypeOptions = computed(() => MAKING_TYPES.map((value) => ({
        title: localeStore.t(`options.making${toPascalCase(value)}`),
        value,
    })))

    const itemStatusOptions = computed(() => ITEM_STATUSES.map((value) => ({
        title: localeStore.t(`options.${toCamelCase(value)}`),
        value,
    })))

    const stockMovementOptions = computed(() => STOCK_MOVEMENT_TYPES.map((value) => ({
        title: localeStore.t(`options.movement${toPascalCase(value)}`),
        value,
    })))

    const paymentMethodOptions = computed(() => PAYMENT_METHODS.map((value) => ({
        title: localeStore.t(`options.method${toPascalCase(value)}`),
        value,
    })))

    const saleStatusOptions = computed(() => SALE_STATUSES.map((value) => ({
        title: localeStore.t(`options.${toCamelCase(value)}`),
        value,
    })))

    return {
        karatOptions,
        goldKaratOptions,
        makingTypeOptions,
        itemStatusOptions,
        stockMovementOptions,
        paymentMethodOptions,
        saleStatusOptions,
    }
}

function toPascalCase(value) {
    return String(value)
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('')
}

function toCamelCase(value) {
    const pascal = toPascalCase(value)
    return pascal.charAt(0).toLowerCase() + pascal.slice(1)
}
