<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useInventoryStore } from '../../stores/inventory'
import { useLocaleStore } from '../../stores/locale'
import { usePurchasesStore } from '../../stores/purchases'
import { useSuppliersStore } from '../../stores/suppliers'
import { useCurrency, useWeightFormatter } from '../../utils/format'

const router = useRouter()
const authStore = useAuthStore()
const inventoryStore = useInventoryStore()
const localeStore = useLocaleStore()
const purchasesStore = usePurchasesStore()
const suppliersStore = useSuppliersStore()
const { karatOptions, paymentMethodOptions: methodItems } = useOptionLabels()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()
const canOverrideRate = computed(() => authStore.can('manage gold rates'))
const supplier = ref(null)
const categories = ref([])
const errorMessage = ref('')
const today = new Date().toISOString().slice(0, 10)
const form = reactive({
    date: today,
    discount: '0.00',
    notes: '',
    payNow: '0.00',
    paymentMethod: 'cash',
    paymentReference: '',
    items: [blankItem()],
})

function blankItem() {
    return {
        name: '',
        category_id: null,
        karat: 22,
        gross_weight: '',
        stone_weight: '0.000',
        making_value: '0.00',
        rate: null,
    }
}

const supplierItems = computed(() => suppliersStore.items.map((row) => ({
    title: `${row.name} · ${row.code} · ${localeStore.t(`options.${row.type}`)}`,
    value: row.id,
})))

const shopRate = (karat) => purchasesStore.rates?.[karat] ?? null

function rateFor(item) {
    if (item.rate !== null && item.rate !== undefined && item.rate !== '') {
        return Number(item.rate)
    }

    const rate = shopRate(item.karat)

    return rate === null ? null : Number(rate)
}

function lineAmount(item) {
    const rate = rateFor(item)

    if (rate === null) {
        return 0
    }

    const net = (Number(item.gross_weight) || 0) - (Number(item.stone_weight) || 0)

    if (net <= 0) {
        return 0
    }

    return net * rate + (Number(item.making_value) || 0)
}

const subtotal = computed(() => form.items.reduce(
    (total, item) => total + lineAmount(item),
    0,
))
const discount = computed(() => Number(form.discount) || 0)
const total = computed(() => Math.max(0, subtotal.value - discount.value))
const paid = computed(() => Number(form.payNow) || 0)
const due = computed(() => Math.max(0, total.value - paid.value))
const totalNetWeight = computed(() => form.items.reduce(
    (total, item) => total + Math.max(0, (Number(item.gross_weight) || 0) - (Number(item.stone_weight) || 0)),
    0,
))
const missingRates = computed(() => form.items.filter((item) => rateFor(item) === null))

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function addItem() {
    form.items.push(blankItem())
}

function removeItem(index) {
    form.items.splice(index, 1)
}

function enableOverride(item) {
    const rate = shopRate(item.karat)

    item.rate = rate === null ? null : Number(rate)
}

function disableOverride(item) {
    item.rate = null
}

function clearForm() {
    supplier.value = null
    Object.assign(form, {
        date: today,
        discount: '0.00',
        notes: '',
        payNow: '0.00',
        paymentMethod: 'cash',
        paymentReference: '',
        items: [blankItem()],
    })
    errorMessage.value = ''
}

function validate() {
    if (!supplier.value) {
        return localeStore.t('purchases.selectSupplier')
    }

    if (!form.items.length) {
        return localeStore.t('purchases.needOneItem')
    }

    if (form.items.some((item) => !item.name.trim() || !(Number(item.gross_weight) > 0) || !item.category_id)) {
        return localeStore.t('inventory.description')
    }

    if (missingRates.value.length) {
        const karat = missingRates.value[0].karat

        return localeStore.t('purchases.noRate', { karat, date: form.date })
    }

    if (discount.value > subtotal.value) {
        return localeStore.t('sales.discountTooHigh')
    }

    if (paid.value > total.value) {
        return localeStore.t('sales.paidTooHigh')
    }

    return null
}

async function submit() {
    errorMessage.value = validate()

    if (errorMessage.value) {
        return
    }

    const payload = new FormData()
    payload.append('supplier_id', String(supplier.value))
    payload.append('date', form.date)
    payload.append('discount', discount.value.toFixed(2))

    if (form.notes.trim()) {
        payload.append('notes', form.notes.trim())
    }

    if (paid.value > 0) {
        payload.append('payment[amount]', paid.value.toFixed(2))
        payload.append('payment[method]', form.paymentMethod)

        if (form.paymentReference.trim()) {
            payload.append('payment[reference]', form.paymentReference.trim())
        }
    }

    form.items.forEach((item, index) => {
        payload.append(`items[${index}][name]`, item.name.trim())
        payload.append(`items[${index}][category_id]`, String(item.category_id))
        payload.append(`items[${index}][karat]`, String(item.karat))
        payload.append(`items[${index}][gross_weight]`, Number(item.gross_weight).toFixed(3))
        payload.append(`items[${index}][stone_weight]`, Number(item.stone_weight || 0).toFixed(3))
        payload.append(`items[${index}][making_value]`, Number(item.making_value || 0).toFixed(2))

        if (item.rate !== null && item.rate !== undefined && item.rate !== '') {
            payload.append(`items[${index}][rate]`, Number(item.rate).toFixed(2))
        }
    })

    try {
        const purchase = await purchasesStore.savePurchase(payload)

        router.push({ name: 'purchase-profile', params: { id: purchase.id } })
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? purchasesStore.error
            ?? localeStore.t('purchases.saveFailed')
    }
}

async function loadRates() {
    try {
        await purchasesStore.fetchRates(form.date, true)
    } catch {
        // The server is still the authority; the preview just stays blank.
    }
}

let debounce = null

watch(() => form.date, () => {
    clearTimeout(debounce)
    debounce = setTimeout(loadRates, 300)
})

onMounted(async () => {
    try {
        await suppliersStore.fetchList({ per_page: 100 }, true)
    } catch {
        // The form still validates and reports on submit.
    }

    try {
        categories.value = await inventoryStore.fetchCategories()
    } catch {
        categories.value = []
    }

    await loadRates()
})
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row justify="center">
            <v-col cols="12" lg="10">
                <div class="d-flex align-center ga-3 mb-6">
                    <v-btn
                        :aria-label="$t('common.back')"
                        icon="mdi-arrow-left"
                        variant="text"
                        @click="router.push({ name: 'purchases' })"
                    />
                    <div>
                        <v-card-subtitle>{{ $t('purchases.listTitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold pa-0">
                            {{ $t('purchases.newTitle') }}
                        </v-card-title>
                    </div>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    closable
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                    @click:close="errorMessage = ''"
                >
                    {{ errorMessage }}
                </v-alert>

                <v-form @submit.prevent="submit">
                    <v-card class="mb-6" elevation="2">
                        <v-card-text>
                            <v-row>
                                <v-col cols="12" md="7">
                                    <v-select
                                        v-model="supplier"
                                        :items="supplierItems"
                                        :label="$t('purchases.selectSupplier')"
                                        prepend-inner-icon="mdi-store-outline"
                                        variant="outlined"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6" md="5">
                                    <v-text-field
                                        v-model="form.date"
                                        :label="$t('purchases.purchaseDate')"
                                        type="date"
                                        variant="outlined"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.notes"
                                        :label="$t('common.notes')"
                                        rows="2"
                                        variant="outlined"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>

                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                            <span>{{ $t('purchases.items') }}</span>
                            <v-btn
                                color="primary"
                                prepend-icon="mdi-plus"
                                size="small"
                                variant="text"
                                @click="addItem"
                            >
                                {{ $t('purchases.addItem') }}
                            </v-btn>
                        </v-card-title>
                        <v-card-text>
                            <v-table density="comfortable">
                                <thead>
                                    <tr>
                                        <th>{{ $t('purchases.itemName') }}</th>
                                        <th style="width: 175px">{{ $t('inventory.category') }}</th>
                                        <th style="width: 120px">{{ $t('inventory.karat') }}</th>
                                        <th style="width: 145px">{{ $t('inventory.grossWeight') }}</th>
                                        <th style="width: 145px">{{ $t('inventory.stoneWeight') }}</th>
                                        <th style="width: 150px">{{ $t('sales.ratePerGram') }}</th>
                                        <th style="width: 150px">{{ $t('sales.making') }}</th>
                                        <th style="width: 150px" class="text-end">
                                            {{ $t('purchases.lineAmount') }}
                                        </th>
                                        <th style="width: 60px" />
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in form.items" :key="index">
                                        <td>
                                            <v-text-field
                                                v-model="item.name"
                                                :label="$t('purchases.itemName')"
                                                density="compact"
                                                variant="outlined"
                                            />
                                        </td>
                                        <td>
                                            <v-select
                                                v-model="item.category_id"
                                                density="compact"
                                                :items="categories"
                                                item-title="name"
                                                item-value="id"
                                                :label="$t('inventory.selectCategory')"
                                                variant="outlined"
                                            />
                                        </td>
                                        <td>
                                            <v-select
                                                v-model="item.karat"
                                                :items="karatOptions"
                                                density="compact"
                                                variant="outlined"
                                            />
                                        </td>
                                        <td>
                                            <v-text-field
                                                v-model="item.gross_weight"
                                                density="compact"
                                                min="0.001"
                                                step="0.001"
                                                type="number"
                                                variant="outlined"
                                            />
                                        </td>
                                        <td>
                                            <v-text-field
                                                v-model="item.stone_weight"
                                                density="compact"
                                                min="0"
                                                step="0.001"
                                                type="number"
                                                variant="outlined"
                                            />
                                        </td>
                                        <td>
                                            <v-text-field
                                                v-model="item.rate"
                                                :disabled="!canOverrideRate || item.rate === null"
                                                :hint="item.rate === null ? undefined : $t('purchases.rateOverrideHint')"
                                                density="compact"
                                                min="0.01"
                                                persistent-hint
                                                :placeholder="shopRate(item.karat) ?? '—'"
                                                step="0.01"
                                                :suffix="currencySymbol"
                                                type="number"
                                                variant="outlined"
                                            />
                                            <div class="d-flex align-center justify-space-between ga-1 mt-1">
                                                <span class="text-caption text-medium-emphasis">
                                                    <template v-if="rateFor(item) === null">
                                                        {{ $t('purchases.noRate', { karat: item.karat, date: form.date }) }}
                                                    </template>
                                                    <template v-else>
                                                        {{ $t('sales.shopRate', { rate: money(rateFor(item)) }) }}
                                                    </template>
                                                </span>
                                                <v-btn
                                                    v-if="canOverrideRate && item.rate === null"
                                                    density="compact"
                                                    size="x-small"
                                                    variant="text"
                                                    @click="enableOverride(item)"
                                                >
                                                    {{ $t('purchases.rateOverride') }}
                                                </v-btn>
                                                <v-btn
                                                    v-else-if="canOverrideRate"
                                                    density="compact"
                                                    size="x-small"
                                                    variant="text"
                                                    @click="disableOverride(item)"
                                                >
                                                    {{ $t('common.clear') }}
                                                </v-btn>
                                            </div>
                                        </td>
                                        <td>
                                            <v-text-field
                                                v-model="item.making_value"
                                                density="compact"
                                                min="0"
                                                step="0.01"
                                                :suffix="currencySymbol"
                                                type="number"
                                                variant="outlined"
                                            />
                                        </td>
                                        <td class="text-end font-weight-medium">
                                            {{ money(lineAmount(item)) }}
                                        </td>
                                        <td>
                                            <v-btn
                                                :aria-label="$t('purchases.removeItem')"
                                                color="error"
                                                density="compact"
                                                icon="mdi-delete-outline"
                                                size="small"
                                                variant="text"
                                                @click="removeItem(index)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>

                            <v-alert
                                v-if="!form.items.length"
                                class="mt-4"
                                color="info"
                                density="comfortable"
                                variant="tonal"
                            >
                                {{ $t('purchases.noItems') }}
                            </v-alert>

                            <div class="text-caption text-medium-emphasis mt-2">
                                {{ $t('inventory.netWeight') }}: {{ formatWeight(totalNetWeight) }}
                            </div>
                        </v-card-text>
                    </v-card>

                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('purchases.preview') }}
                        </v-card-title>
                        <v-card-text>
                            <v-row>
                                <v-col cols="12" md="7">
                                    <v-row dense>
                                        <v-col cols="6" sm="4" class="text-center">
                                            <div class="text-caption text-medium-emphasis">
                                                {{ $t('common.subtotal') }}
                                            </div>
                                            <div class="text-h6 font-weight-medium">{{ money(subtotal) }}</div>
                                        </v-col>
                                        <v-col cols="6" sm="4" class="text-center">
                                            <div class="text-caption text-medium-emphasis">
                                                {{ $t('common.discount') }}
                                            </div>
                                            <v-text-field
                                                v-model="form.discount"
                                                density="compact"
                                                min="0"
                                                step="0.01"
                                                :suffix="currencySymbol"
                                                type="number"
                                                variant="outlined"
                                            />
                                        </v-col>
                                        <v-col cols="12" sm="4" class="text-center">
                                            <div class="text-caption text-medium-emphasis">
                                                {{ $t('common.total') }}
                                            </div>
                                            <div class="text-h6 font-weight-bold">{{ money(total) }}</div>
                                        </v-col>
                                    </v-row>

                                    <v-divider class="my-4" />

                                    <v-row dense align="center">
                                        <v-col cols="12" sm="6">
                                            <v-text-field
                                                v-model="form.payNow"
                                                :hint="$t('purchases.payNowHint')"
                                                :label="$t('purchases.payNow')"
                                                min="0"
                                                persistent-hint
                                                step="0.01"
                                                :suffix="currencySymbol"
                                                type="number"
                                                variant="outlined"
                                            />
                                        </v-col>
                                        <v-col cols="12" sm="6">
                                            <v-select
                                                v-model="form.paymentMethod"
                                                :disabled="paid <= 0"
                                                :items="methodItems"
                                                :label="$t('common.method')"
                                                variant="outlined"
                                            />
                                            <v-text-field
                                                v-model="form.paymentReference"
                                                :disabled="paid <= 0"
                                                :label="$t('common.reference')"
                                                variant="outlined"
                                            />
                                        </v-col>
                                    </v-row>
                                </v-col>

                                <v-col cols="12" md="5">
                                    <v-list density="compact" class="bg-transparent">
                                        <v-list-item>
                                            <v-list-item-title>{{ $t('common.total') }}</v-list-item-title>
                                            <template #append>{{ money(total) }}</template>
                                        </v-list-item>
                                        <v-list-item>
                                            <v-list-item-title>{{ $t('common.paid') }}</v-list-item-title>
                                            <template #append>{{ money(paid) }}</template>
                                        </v-list-item>
                                        <v-list-item>
                                            <v-list-item-title>{{ $t('common.due') }}</v-list-item-title>
                                            <template #append>
                                                <span :class="{ 'text-error font-weight-medium': due > 0 }">
                                                    {{ money(due) }}
                                                </span>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-col>
                            </v-row>
                        </v-card-text>

                        <v-card-actions class="pa-4 pt-0">
                            <v-btn variant="text" @click="clearForm">{{ $t('sales.clearForm') }}</v-btn>
                            <v-spacer />
                            <v-btn color="primary" :loading="purchasesStore.saving" size="large" type="submit">
                                {{ $t('common.save') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-form>
            </v-col>
        </v-row>
    </v-container>
</template>
