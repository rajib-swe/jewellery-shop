<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CustomerPicker from '../../components/CustomerPicker.vue'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useGoldRatesStore } from '../../stores/gold-rates'
import { useInventoryStore } from '../../stores/inventory'
import { useLocaleStore } from '../../stores/locale'
import { useSalesStore } from '../../stores/sales'
import { useSettingsStore } from '../../stores/settings'
import { useCurrency, useWeightFormatter } from '../../utils/format'

const router = useRouter()
const authStore = useAuthStore()
const goldRateStore = useGoldRatesStore()
const inventoryStore = useInventoryStore()
const localeStore = useLocaleStore()
const salesStore = useSalesStore()
const settingsStore = useSettingsStore()
const { karatOptions: karatItems, paymentMethodOptions: paymentItems, makingTypeOptions: makingTypeItems } = useOptionLabels()
const { formatWeight: formatItemWeight } = useWeightFormatter()
const currencySymbol = useCurrency()
const canOverrideRate = computed(() => authStore.can('manage gold rates'))
const customer = ref(null)
const date = ref(new Date().toISOString().slice(0, 10))
const discount = ref('0.00')
const notes = ref('')
const itemSearch = ref('')
const itemResults = ref([])
const itemSearching = ref(false)
const selectedItem = ref(null)
const lines = ref([])
const payments = ref([{ method: 'cash', amount: '', reference: '' }])
const exchanges = ref([])
const errorMessage = ref('')
const fieldErrors = reactive({})
const loadingInitial = ref(false)
const manualDialog = ref(false)
const manualEditingIndex = ref(null)
const manualForm = reactive({
    name: '',
    karat: 22,
    weight: '',
    rate: '',
    making_type: 'fixed',
    making_value: '0.00',
    stone_price: '0.00',
})

const vatPercentage = computed(() => Number(settingsStore.settings.vat_percentage || 0))
const categoryItems = computed(() => inventoryStore.categories.map((category) => ({
    title: category.name,
    value: category.id,
})))

function rateFor(karat) {
    return goldRateStore.latest.find((rate) => rate.karat === karat)?.rate_per_gram ?? null
}

function round2(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100
}

function lineAmounts(line) {
    const weight = Number(line.weight || 0)
    const rate = line.rate === '' || line.rate === null ? Number(line.shop_rate || 0) : Number(line.rate)
    const goldValue = round2(weight * rate)
    const makingValue = Number(line.making_value || 0)
    const making = line.making_type === 'per_gram'
        ? round2(makingValue * weight)
        : line.making_type === 'percent'
            ? round2(goldValue * (makingValue / 100))
            : round2(makingValue)
    const stonePrice = round2(line.stone_price || 0)

    return {
        goldValue,
        making,
        lineTotal: round2(goldValue + making + stonePrice),
    }
}

const lineTotals = computed(() => lines.value.map((line) => lineAmounts(line)))
const subtotal = computed(() => round2(lineTotals.value.reduce((sum, amounts) => sum + amounts.lineTotal, 0)))
const discountAmount = computed(() => Math.max(round2(discount.value || 0), 0))
const exchangeTotal = computed(() => round2(exchanges.value.reduce((sum, exchange) => {
    const rate = exchange.rate === '' || exchange.rate === null ? Number(exchange.shop_rate || 0) : Number(exchange.rate)

    return sum + round2(Number(exchange.weight || 0) * rate)
}, 0)))
const vat = computed(() => round2((subtotal.value - discountAmount.value) * (vatPercentage.value / 100)))
const total = computed(() => round2(subtotal.value - discountAmount.value + vat.value - exchangeTotal.value))
const paid = computed(() => round2(payments.value.reduce((sum, payment) => sum + Number(payment.amount || 0), 0)))
const due = computed(() => round2(total.value - paid.value))

const itemItems = computed(() => itemResults.value.map((item) => ({
    ...item,
    title: `${item.name} · ${item.tag_no}`,
    subtitle: `${item.karat}K · ${formatItemWeight(item.net_weight)}`,
})))

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function clearFieldErrors() {
    Object.keys(fieldErrors).forEach((field) => delete fieldErrors[field])
}

function setValidationErrors(errors = {}) {
    Object.entries(errors).forEach(([field, messages]) => {
        if (Array.isArray(messages) && messages.length) {
            fieldErrors[field] = messages[0]
        }
    })
}

async function load() {
    errorMessage.value = ''
    loadingInitial.value = true

    try {
        await Promise.all([
            settingsStore.fetchSettings(),
            goldRateStore.fetchLatest(),
            inventoryStore.fetchCategories(),
        ])
    } catch {
        errorMessage.value = localeStore.t('sales.loadFailed')
    } finally {
        loadingInitial.value = false
    }
}

async function searchItems(value) {
    itemSearch.value = value
    const term = value.trim()

    if (term.length < 2) {
        itemResults.value = []
        return
    }

    itemSearching.value = true

    try {
        const response = await inventoryStore.fetchItems({
            search: term,
            status: 'in_stock',
            per_page: 10,
        }, true)
        itemResults.value = response.data
    } catch {
        itemResults.value = []
    } finally {
        itemSearching.value = false
    }
}

function addItem(item) {
    errorMessage.value = ''

    if (lines.value.some((line) => line.item_id === item.id)) {
        errorMessage.value = localeStore.t('sales.alreadyAdded', { name: item.name })
        return
    }

    const shopRate = rateFor(item.karat)

    if (shopRate === null) {
        errorMessage.value = localeStore.t('sales.noRate', { karat: `${item.karat}K` })
        return
    }

    lines.value.push({
        item_id: item.id,
        tag_no: item.tag_no,
        name: item.name,
        karat: item.karat,
        weight: item.net_weight,
        manual: false,
        making_type: item.making_type,
        making_value: item.making_value,
        stone_price: item.stone_price,
        shop_rate: shopRate,
        rate: '',
    })
    selectedItem.value = null
    itemSearch.value = ''
    itemResults.value = []
}

function openManualLine() {
    errorMessage.value = ''
    manualEditingIndex.value = null
    Object.assign(manualForm, {
        name: '',
        karat: 22,
        weight: '',
        rate: '',
        making_type: 'fixed',
        making_value: '0.00',
        stone_price: '0.00',
    })
    manualDialog.value = true
}

function editManualLine(index) {
    const line = lines.value[index]

    if (!line?.manual) {
        return
    }

    errorMessage.value = ''
    manualEditingIndex.value = index
    Object.assign(manualForm, {
        name: line.name,
        karat: line.karat,
        weight: line.weight,
        rate: line.rate,
        making_type: line.making_type,
        making_value: line.making_value,
        stone_price: line.stone_price,
    })
    manualDialog.value = true
}

function saveManualLine() {
    errorMessage.value = ''

    if (!manualForm.name.trim()) {
        errorMessage.value = localeStore.t('sales.nameRequired')
        return
    }

    if (!(Number(manualForm.weight) > 0)) {
        errorMessage.value = localeStore.t('sales.weightRequired')
        return
    }

    const manualLine = {
        item_id: null,
        tag_no: '',
        name: manualForm.name.trim(),
        karat: Number(manualForm.karat),
        weight: Number(manualForm.weight).toFixed(3),
        manual: true,
        making_type: manualForm.making_type,
        making_value: String(manualForm.making_value || 0),
        stone_price: String(manualForm.stone_price || 0),
        shop_rate: rateFor(Number(manualForm.karat)),
        rate: manualForm.rate === '' ? '' : String(manualForm.rate),
    }

    if (manualEditingIndex.value === null) {
        lines.value.push(manualLine)
    } else {
        lines.value.splice(manualEditingIndex.value, 1, manualLine)
    }

    manualDialog.value = false
}

function removeLine(index) {
    lines.value.splice(index, 1)
}

function addPaymentRow() {
    payments.value.push({ method: 'cash', amount: '', reference: '' })
}

function removePaymentRow(index) {
    payments.value.splice(index, 1)

    if (!payments.value.length) {
        payments.value.push({ method: 'cash', amount: '', reference: '' })
    }
}

function addExchangeRow() {
    exchanges.value.push({
        description: '',
        karat: 22,
        weight: '',
        rate: '',
        shop_rate: rateFor(22),
        category_id: null,
    })
}

function removeExchangeRow(index) {
    exchanges.value.splice(index, 1)
}

function onExchangeKaratChange(exchange) {
    exchange.shop_rate = rateFor(exchange.karat)
    exchange.rate = ''
}

function buildPayload() {
    return {
        customer_id: customer.value?.id ?? null,
        date: date.value,
        discount: discountAmount.value.toFixed(2),
        notes: notes.value.trim() || null,
        items: lines.value.map((line) => (line.manual
            ? {
                item_id: null,
                name: line.name,
                karat: Number(line.karat),
                weight: line.weight,
                rate: line.rate === '' ? null : Number(line.rate).toFixed(2),
                making_type: line.making_type,
                making_value: Number(line.making_value || 0).toFixed(2),
                stone_price: Number(line.stone_price || 0).toFixed(2),
            }
            : {
                item_id: line.item_id,
                rate: line.rate === '' ? null : Number(line.rate).toFixed(2),
            })),
        payments: payments.value
            .filter((payment) => Number(payment.amount || 0) > 0)
            .map((payment) => ({
                method: payment.method,
                amount: Number(payment.amount).toFixed(2),
                reference: payment.reference.trim() || null,
            })),
        exchanges: exchanges.value.map((exchange) => ({
            description: exchange.description.trim(),
            karat: Number(exchange.karat),
            weight: Number(exchange.weight).toFixed(3),
            rate: exchange.rate === '' ? null : Number(exchange.rate).toFixed(2),
            category_id: exchange.category_id ?? null,
        })),
    }
}

function validate() {
    clearFieldErrors()

    if (!lines.value.length) {
        fieldErrors.items = localeStore.t('sales.needOneItem')
    }

    if (discountAmount.value > subtotal.value) {
        fieldErrors.discount = localeStore.t('sales.discountTooHigh')
    }

    if (total.value < 0) {
        fieldErrors.exchanges = localeStore.t('sales.exchangeTooHigh')
    }

    if (paid.value > total.value) {
        fieldErrors.payments = localeStore.t('sales.paidTooHigh')
    }

    exchanges.value.forEach((exchange, index) => {
        if (!exchange.description.trim()) {
            fieldErrors[`exchanges.${index}.description`] = localeStore.t('sales.descriptionRequired')
        }

        if (!(Number(exchange.weight) > 0)) {
            fieldErrors[`exchanges.${index}.weight`] = localeStore.t('sales.weightPositive')
        }
    })

    return Object.keys(fieldErrors).length === 0
}

async function submit() {
    errorMessage.value = ''

    if (!validate()) {
        return
    }

    try {
        const sale = await salesStore.saveSale(buildPayload())
        router.push({ name: 'sale-profile', params: { id: sale.id } })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? salesStore.error ?? localeStore.t('sales.saveFailed')
        setValidationErrors(error.response?.data?.errors ?? {})
    }
}

function reset() {
    customer.value = null
    discount.value = '0.00'
    notes.value = ''
    lines.value = []
    payments.value = [{ method: 'cash', amount: '', reference: '' }]
    exchanges.value = []
    manualDialog.value = false
    clearFieldErrors()
    errorMessage.value = ''
}

watch(
    () => goldRateStore.latest,
    () => {
        lines.value.forEach((line) => {
            line.shop_rate = rateFor(line.karat)
        })
    },
)

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('sales.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">{{ $t('sales.newTitle') }}</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('sales.newIntro') }}
                        </v-card-text>
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn variant="outlined" @click="reset">{{ $t('common.clear') }}</v-btn>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-content-save-outline"
                            :loading="salesStore.saving"
                            @click="submit"
                        >
                            {{ $t('sales.saveSale') }}
                        </v-btn>
                    </div>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                    closable
                    @click:close="errorMessage = ''"
                >
                    {{ errorMessage }}
                </v-alert>

                <v-progress-linear v-if="loadingInitial" indeterminate />
            </v-col>

            <v-col cols="12" lg="8">
                <v-card class="mb-6" elevation="2">
                    <v-card-text>
                        <v-row dense>
                            <v-col cols="12" md="6">
                                <CustomerPicker
                                    v-model="customer"
                                    :label="$t('sales.walkInCustomer')"
                                />
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="date"
                                    density="comfortable"
                                    :label="$t('sales.saleDate')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="discount"
                                    :error-messages="fieldErrors.discount"
                                    :label="$t('common.discount')"
                                    min="0"
                                    step="0.01"
                                    :suffix="currencySymbol"
                                    type="number"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <v-card class="mb-6" elevation="2">
                    <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('sales.items') }}</v-card-title>
                    <v-card-text>
                        <v-alert
                            v-if="fieldErrors.items"
                            class="mb-4"
                            color="error"
                            density="compact"
                            type="error"
                            variant="tonal"
                        >
                            {{ fieldErrors.items }}
                        </v-alert>

                        <div class="d-flex align-start ga-2">
                            <v-autocomplete
                                v-model="selectedItem"
                                class="flex-grow-1"
                                :items="itemItems"
                                :loading="itemSearching"
                                hide-no-data
                                item-title="title"
                                item-subtitle="subtitle"
                                item-value="id"
                                :label="$t('sales.addItemHint')"
                                :no-data-text="$t('sales.noItemData')"
                                return-object
                                variant="outlined"
                                @update:search="searchItems"
                                @update:model-value="addItem"
                            >
                                <template #prepend-inner>
                                    <v-icon icon="mdi-barcode-scan" size="20" />
                                </template>
                            </v-autocomplete>
                            <v-btn
                                color="primary"
                                prepend-icon="mdi-pencil-outline"
                                variant="tonal"
                                @click="openManualLine"
                            >
                                {{ $t('sales.handwrittenItem') }}
                            </v-btn>
                        </div>
                    </v-card-text>

                    <v-divider />

                    <v-table density="comfortable">
                        <thead>
                            <tr>
                                <th>{{ $t('sales.items') }}</th>
                                <th class="text-end">{{ $t('inventory.netWeight') }}</th>
                                <th class="text-end">{{ $t('sales.ratePerGram') }}</th>
                                <th class="text-end">{{ $t('sales.making') }}</th>
                                <th class="text-end">{{ $t('sales.lineTotal') }}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in lines" :key="line.item_id ?? `manual-${index}`">
                                <td>
                                    <div class="font-weight-medium">{{ line.name }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ line.manual ? $t('sales.handwrittenTag') : line.tag_no }} · {{ line.karat }}K
                                    </div>
                                </td>
                                <td class="text-end">
                                    {{ formatItemWeight(line.weight) }}
                                </td>
                                <td class="text-end">
                                    <v-text-field
                                        v-model="line.rate"
                                        :hint="$t('sales.shopRate', { rate: money(line.shop_rate) })"
                                        persistent-hint
                                        :placeholder="Number(line.shop_rate || 0).toFixed(2)"
                                        :readonly="!canOverrideRate"
                                        :suffix="currencySymbol"
                                        type="number"
                                        variant="outlined"
                                        density="compact"
                                    />
                                </td>
                                <td class="text-end">
                                    {{ money(lineTotals[index]?.making) }}
                                </td>
                                <td class="text-end font-weight-medium">
                                    {{ money(lineTotals[index]?.lineTotal) }}
                                </td>
                                <td class="text-end">
                                    <v-btn
                                        v-if="line.manual"
                                        :aria-label="$t('sales.handwrittenEdit')"
                                        icon="mdi-pencil-outline"
                                        size="small"
                                        variant="text"
                                        @click="editManualLine(index)"
                                    />
                                    <v-btn
                                        :aria-label="$t('sales.removeItem')"
                                        icon="mdi-delete-outline"
                                        size="small"
                                        variant="text"
                                        @click="removeLine(index)"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!lines.length">
                                <td class="text-center text-medium-emphasis" colspan="6">
                                    {{ $t('sales.noLines') }}
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>

                <v-card class="mb-6" elevation="2">
                    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                        {{ $t('sales.exchangeSection') }}
                        <v-btn
                            prepend-icon="mdi-plus"
                            size="small"
                            variant="tonal"
                            @click="addExchangeRow"
                        >
                            {{ $t('sales.addExchange') }}
                        </v-btn>
                    </v-card-title>
                    <v-card-text>
                        <v-alert
                            v-if="fieldErrors.exchanges"
                            class="mb-4"
                            color="error"
                            density="compact"
                            type="error"
                            variant="tonal"
                        >
                            {{ fieldErrors.exchanges }}
                        </v-alert>

                        <v-row v-for="(exchange, index) in exchanges" :key="index" dense class="mb-2">
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="exchange.description"
                                    :error-messages="fieldErrors[`exchanges.${index}.description`]"
                                    :label="$t('sales.description')"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-select
                                    v-model="exchange.karat"
                                    :items="karatItems"
                                    :label="$t('inventory.karat')"
                                    variant="outlined"
                                    density="compact"
                                    @update:model-value="onExchangeKaratChange(exchange)"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="exchange.weight"
                                    :error-messages="fieldErrors[`exchanges.${index}.weight`]"
                                    :label="$t('inventory.netWeight')"
                                    min="0.001"
                                    step="0.001"
                                    :suffix="$t('units.gram')"
                                    type="number"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="exchange.rate"
                                    :hint="$t('sales.shopRate', { rate: money(exchange.shop_rate) })"
                                    persistent-hint
                                    :placeholder="Number(exchange.shop_rate || 0).toFixed(2)"
                                    :readonly="!canOverrideRate"
                                    :suffix="currencySymbol"
                                    type="number"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-select
                                    v-model="exchange.category_id"
                                    clearable
                                    :items="categoryItems"
                                    :label="$t('sales.storeAsScrap')"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="12" md="1" class="d-flex align-center justify-end">
                                <v-btn
                                    :aria-label="$t('sales.removeExchange')"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="removeExchangeRow(index)"
                                />
                            </v-col>
                        </v-row>

                        <div v-if="!exchanges.length" class="text-medium-emphasis">
                            {{ $t('sales.noExchanges') }}
                        </div>
                    </v-card-text>
                </v-card>

                <v-card elevation="2">
                    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                        {{ $t('sales.payments') }}
                        <v-btn
                            prepend-icon="mdi-plus"
                            size="small"
                            variant="tonal"
                            @click="addPaymentRow"
                        >
                            {{ $t('sales.addPayment') }}
                        </v-btn>
                    </v-card-title>
                    <v-card-text>
                        <v-alert
                            v-if="fieldErrors.payments"
                            class="mb-4"
                            color="error"
                            density="compact"
                            type="error"
                            variant="tonal"
                        >
                            {{ fieldErrors.payments }}
                        </v-alert>

                        <v-row v-for="(payment, index) in payments" :key="index" dense class="mb-2">
                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="payment.method"
                                    :items="paymentItems"
                                    :label="$t('common.method')"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-text-field
                                    v-model="payment.amount"
                                    :label="$t('common.amount')"
                                    min="0.01"
                                    step="0.01"
                                    :suffix="currencySymbol"
                                    type="number"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="4">
                                <v-text-field
                                    v-model="payment.reference"
                                    :label="$t('common.reference')"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="12" md="2" class="d-flex align-center justify-end">
                                <v-btn
                                    :aria-label="$t('sales.removePayment')"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="removePaymentRow(index)"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" lg="4">
                <v-card elevation="2" position="sticky" style="top: 88px;">
                    <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('sales.preview') }}</v-card-title>
                    <v-card-text>
                        <v-list density="compact" class="bg-transparent">
                            <v-list-item>
                                <v-list-item-title>{{ $t('common.subtotal') }}</v-list-item-title>
                                <template #append>{{ money(subtotal) }}</template>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title>{{ $t('common.discount') }}</v-list-item-title>
                                <template #append>- {{ money(discountAmount) }}</template>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title>{{ $t('common.vat') }} ({{ vatPercentage }}%)</v-list-item-title>
                                <template #append>{{ money(vat) }}</template>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title>{{ $t('sales.oldGoldExchange') }}</v-list-item-title>
                                <template #append>- {{ money(exchangeTotal) }}</template>
                            </v-list-item>
                        </v-list>

                        <v-divider class="my-3" />

                        <div class="d-flex justify-space-between text-subtitle-1 font-weight-bold">
                            <span>{{ $t('common.total') }}</span>
                            <span>{{ money(total) }}</span>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mt-2">
                            <span>{{ $t('common.paid') }}</span>
                            <span>{{ money(paid) }}</span>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mt-1">
                            <span>{{ $t('common.due') }}</span>
                            <span :class="{ 'text-error font-weight-medium': due > 0 }">{{ money(due) }}</span>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="manualDialog" max-width="560">
            <v-card>
                <v-card-title>
                    {{ manualEditingIndex === null ? $t('sales.handwrittenTitle') : $t('sales.handwrittenEdit') }}
                </v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        {{ $t('sales.handwrittenBody') }}
                    </v-alert>
                    <v-text-field
                        v-model="manualForm.name"
                        autofocus
                        :label="$t('sales.handwrittenName')"
                        prepend-inner-icon="mdi-pencil-outline"
                        required
                    />
                    <v-row dense>
                        <v-col cols="6">
                            <v-select
                                v-model="manualForm.karat"
                                :items="karatItems"
                                :label="$t('sales.handwrittenKarat')"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="manualForm.weight"
                                :label="$t('sales.handwrittenWeight')"
                                min="0.001"
                                step="0.001"
                                :suffix="$t('units.gram')"
                                type="number"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="manualForm.rate"
                                :hint="$t('sales.shopRate', { rate: money(rateFor(manualForm.karat) ?? 0) })"
                                persistent-hint
                                :placeholder="Number(rateFor(manualForm.karat) ?? 0).toFixed(2)"
                                :readonly="!canOverrideRate"
                                :suffix="currencySymbol"
                                type="number"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="manualForm.stone_price"
                                :label="$t('sales.handwrittenStonePrice')"
                                min="0"
                                step="0.01"
                                :suffix="currencySymbol"
                                type="number"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-select
                                v-model="manualForm.making_type"
                                :items="makingTypeItems"
                                :label="$t('sales.handwrittenMakingType')"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="manualForm.making_value"
                                :label="$t('sales.handwrittenMakingValue')"
                                min="0"
                                step="0.01"
                                :suffix="currencySymbol"
                                type="number"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="manualDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" @click="saveManualLine">
                        {{ manualEditingIndex === null ? $t('common.add') : $t('common.save') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
