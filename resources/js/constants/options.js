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
        title: localeStore.t(`options.making${capitalize(value)}`),
        value,
    })))

    const itemStatusOptions = computed(() => ITEM_STATUSES.map((value) => ({
        title: localeStore.t(`options.${value}`),
        value,
    })))

    const stockMovementOptions = computed(() => STOCK_MOVEMENT_TYPES.map((value) => ({
        title: localeStore.t(`options.movement${capitalize(value)}`),
        value,
    })))

    const paymentMethodOptions = computed(() => PAYMENT_METHODS.map((value) => ({
        title: localeStore.t(`options.method${capitalize(value)}`),
        value,
    })))

    const saleStatusOptions = computed(() => SALE_STATUSES.map((value) => ({
        title: localeStore.t(`options.${value}`),
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

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1)
}
