<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CustomerPicker from '../../components/CustomerPicker.vue'
import { PAYMENT_METHOD_OPTIONS } from '../../constants/sales'
import { KARAT_OPTIONS } from '../../constants/inventory'
import { useAuthStore } from '../../stores/auth'
import { useGoldRatesStore } from '../../stores/gold-rates'
import { useInventoryStore } from '../../stores/inventory'
import { useSalesStore } from '../../stores/sales'
import { useSettingsStore } from '../../stores/settings'
import { gramsToTraditional } from '../../utils/weight'

const router = useRouter()
const authStore = useAuthStore()
const goldRateStore = useGoldRatesStore()
const inventoryStore = useInventoryStore()
const salesStore = useSalesStore()
const settingsStore = useSettingsStore()
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

const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')
const weightUnit = computed(() => settingsStore.settings.weight_unit || 'gram')
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
    const weight = Number(line.net_weight || 0)
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

const itemItems = computed(() => itemResults.value.map((item) => ({    ...item,
    title: `${item.name} · ${item.tag_no}`,
    subtitle: `${item.karat}K · ${Number(item.net_weight).toFixed(3)} g`,
})))

const karatItems = KARAT_OPTIONS

function money(value) {    return `${currencySymbol.value}${Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function formatWeight(grams) {
    const value = Number(grams || 0)

    if (weightUnit.value === 'vori') {
        const traditional = gramsToTraditional(value)

        return `${traditional.vori.toFixed(4)} vori`
    }

    return `${value.toFixed(3)} g`
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
        errorMessage.value = 'Unable to load the sale prerequisites.'
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
        errorMessage.value = `${item.name} is already on this invoice.`
        return
    }

    const shopRate = rateFor(item.karat)

    if (shopRate === null) {
        errorMessage.value = `No gold rate is configured for ${item.karat}K.`
        return
    }

    lines.value.push({
        item_id: item.id,
        tag_no: item.tag_no,
        name: item.name,
        karat: item.karat,
        net_weight: item.net_weight,
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
        items: lines.value.map((line) => ({
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
        fieldErrors.items = 'Add at least one item to the sale.'
    }

    if (discountAmount.value > subtotal.value) {
        fieldErrors.discount = 'The discount cannot be greater than the subtotal.'
    }

    if (total.value < 0) {
        fieldErrors.exchanges = 'The old gold exchange cannot exceed the invoice total.'
    }

    if (paid.value > total.value) {
        fieldErrors.payments = 'The paid amount cannot be greater than the invoice total.'
    }

    exchanges.value.forEach((exchange, index) => {
        if (!exchange.description.trim()) {
            fieldErrors[`exchanges.${index}.description`] = 'Description is required.'
        }

        if (!(Number(exchange.weight) > 0)) {
            fieldErrors[`exchanges.${index}.weight`] = 'Enter a weight greater than zero.'
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
        errorMessage.value = error.response?.data?.message ?? salesStore.error ?? 'Unable to record the sale.'
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
                        <v-card-subtitle>Sales</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">New sale</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            Every total is recalculated on the server before the invoice is stored.
                        </v-card-text>
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn variant="outlined" @click="reset">Clear</v-btn>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-content-save-outline"
                            :loading="salesStore.saving"
                            @click="submit"
                        >
                            Save sale
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
                                <CustomerPicker v-model="customer" label="Customer (optional for walk-in sales)" />
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="date"
                                    density="comfortable"
                                    label="Sale date"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="discount"
                                    :error-messages="fieldErrors.discount"
                                    label="Discount"
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
                    <v-card-title class="text-subtitle-1 font-weight-medium">Items</v-card-title>
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

                        <v-autocomplete
                            v-model="selectedItem"
                            :items="itemItems"
                            :loading="itemSearching"
                            hide-no-data
                            item-title="title"
                            item-subtitle="subtitle"
                            item-value="id"
                            label="Scan or search a tag, barcode, or name"
                            no-data-text="Type at least two characters to search in-stock items"
                            return-object
                            variant="outlined"
                            @update:search="searchItems"
                            @update:model-value="addItem"
                        >
                            <template #prepend-inner>
                                <v-icon icon="mdi-barcode-scan" size="20" />
                            </template>
                        </v-autocomplete>
                    </v-card-text>

                    <v-divider />

                    <v-table density="comfortable">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Weight</th>
                                <th class="text-end">Rate/g</th>
                                <th class="text-end">Making</th>
                                <th class="text-end">Line total</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in lines" :key="line.item_id">
                                <td>
                                    <div class="font-weight-medium">{{ line.name }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ line.tag_no }} · {{ line.karat }}K
                                    </div>
                                </td>
                                <td class="text-end">
                                    {{ formatWeight(line.net_weight) }}
                                </td>
                                <td class="text-end">
                                    <v-text-field
                                        v-model="line.rate"
                                        :hint="`Shop rate ${money(line.shop_rate)}`"
                                        persistent-hint
                                        :placeholder="Number(line.shop_rate).toFixed(2)"
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
                                        aria-label="Remove item"
                                        icon="mdi-delete-outline"
                                        size="small"
                                        variant="text"
                                        @click="removeLine(index)"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!lines.length">
                                <td class="text-center text-medium-emphasis" colspan="6">
                                    No items added yet.
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>

                <v-card class="mb-6" elevation="2">
                    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                        Old gold exchange
                        <v-btn
                            prepend-icon="mdi-plus"
                            size="small"
                            variant="tonal"
                            @click="addExchangeRow"
                        >
                            Add exchange
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
                                    label="Description"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-select
                                    v-model="exchange.karat"
                                    :items="karatItems"
                                    label="Karat"
                                    variant="outlined"
                                    density="compact"
                                    @update:model-value="onExchangeKaratChange(exchange)"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="exchange.weight"
                                    :error-messages="fieldErrors[`exchanges.${index}.weight`]"
                                    label="Weight"
                                    min="0.001"
                                    step="0.001"
                                    suffix="g"
                                    type="number"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="exchange.rate"
                                    :hint="`Shop rate ${money(exchange.shop_rate)}`"
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
                                    label="Store as scrap"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="12" md="1" class="d-flex align-center justify-end">
                                <v-btn
                                    aria-label="Remove exchange"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="removeExchangeRow(index)"
                                />
                            </v-col>
                        </v-row>

                        <div v-if="!exchanges.length" class="text-medium-emphasis">
                            No old gold submitted for exchange.
                        </div>
                    </v-card-text>
                </v-card>

                <v-card elevation="2">
                    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                        Payments
                        <v-btn
                            prepend-icon="mdi-plus"
                            size="small"
                            variant="tonal"
                            @click="addPaymentRow"
                        >
                            Add payment
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
                                    :items="PAYMENT_METHOD_OPTIONS"
                                    label="Method"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-text-field
                                    v-model="payment.amount"
                                    label="Amount"
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
                                    label="Reference"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="12" md="2" class="d-flex align-center justify-end">
                                <v-btn
                                    aria-label="Remove payment"
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
                    <v-card-title class="text-subtitle-1 font-weight-medium">Invoice preview</v-card-title>
                    <v-card-text>
                        <v-list density="compact" class="bg-transparent">
                            <v-list-item>
                                <v-list-item-title>Subtotal</v-list-item-title>
                                <template #append>{{ money(subtotal) }}</template>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title>Discount</v-list-item-title>
                                <template #append>- {{ money(discountAmount) }}</template>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title>VAT ({{ vatPercentage }}%)</v-list-item-title>
                                <template #append>{{ money(vat) }}</template>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title>Old gold exchange</v-list-item-title>
                                <template #append>- {{ money(exchangeTotal) }}</template>
                            </v-list-item>
                        </v-list>

                        <v-divider class="my-3" />

                        <div class="d-flex justify-space-between text-subtitle-1 font-weight-bold">
                            <span>Total</span>
                            <span>{{ money(total) }}</span>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mt-2">
                            <span>Paid</span>
                            <span>{{ money(paid) }}</span>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mt-1">
                            <span>Due</span>
                            <span :class="{ 'text-error font-weight-medium': due > 0 }">{{ money(due) }}</span>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
