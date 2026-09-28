import { computed } from 'vue'
import { useLocaleStore } from '../stores/locale'
import { ITEM_STATUSES, KARAT_VALUES, MAKING_TYPES, STOCK_MOVEMENT_TYPES } from './inventory'
import { PAYMENT_METHODS, SALE_STATUSES } from './sales'
import { GOLD_KARAT_VALUES } from './gold-rates'
import { SUPPLIER_TYPES } from './suppliers'
import { CASH_DIRECTIONS, CASH_SOURCE_TYPES, EXPENSE_CATEGORIES } from './accounts'
import {
    PAWN_INTEREST_TYPES,
    PAWN_PARTIAL_MONTH_RULES,
    PAWN_PAYMENT_TYPES,
    PAWN_STATUSES,
} from './pawns'

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

    const pawnStatusOptions = computed(() => PAWN_STATUSES.map((value) => ({
        title: localeStore.t(`options.${toCamelCase(value)}`),
        value,
    })))

    const pawnPaymentTypeOptions = computed(() => PAWN_PAYMENT_TYPES.map((value) => ({
        title: localeStore.t(`options.pawnType${toPascalCase(value)}`),
        value,
    })))

    const pawnInterestTypeOptions = computed(() => PAWN_INTEREST_TYPES.map((value) => ({
        title: localeStore.t(`options.pawnInterest${toPascalCase(value)}`),
        value,
    })))

    const pawnPartialMonthRuleOptions = computed(() => PAWN_PARTIAL_MONTH_RULES.map((value) => ({
        title: localeStore.t(`options.partialMonth${toPascalCase(value)}`),
        value,
    })))

    const supplierTypeOptions = computed(() => SUPPLIER_TYPES.map((value) => ({
        title: localeStore.t(`options.${toCamelCase(value)}`),
        value,
    })))

    const expenseCategoryOptions = computed(() => EXPENSE_CATEGORIES.map((value) => ({
        title: localeStore.t(`options.expense${toPascalCase(value)}`),
        value,
    })))

    const cashDirectionOptions = computed(() => CASH_DIRECTIONS.map((value) => ({
        title: localeStore.t(`options.cash${toPascalCase(value)}`),
        value,
    })))

    const cashSourceTypeOptions = computed(() => CASH_SOURCE_TYPES.map((value) => ({
        title: localeStore.t(`options.cashSource${toPascalCase(value)}`),
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
        pawnStatusOptions,
        pawnPaymentTypeOptions,
        pawnInterestTypeOptions,
        pawnPartialMonthRuleOptions,
        supplierTypeOptions,
        expenseCategoryOptions,
        cashDirectionOptions,
        cashSourceTypeOptions,
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
