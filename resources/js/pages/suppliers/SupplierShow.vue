<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { usePurchasesStore } from '../../stores/purchases'
import { useSuppliersStore } from '../../stores/suppliers'
import { useCurrency } from '../../utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const purchasesStore = usePurchasesStore()
const suppliersStore = useSuppliersStore()
const { paymentMethodOptions: methodItems } = useOptionLabels()
const currencySymbol = useCurrency()
const supplierId = computed(() => route.params.id)
const supplier = computed(() => suppliersStore.current)
const ledger = computed(() => suppliersStore.ledger)
const canManage = computed(() => authStore.can('manage suppliers'))
const openPurchases = ref([])
const errorMessage = ref('')
const paymentDialog = ref(false)
const today = new Date().toISOString().slice(0, 10)
const paymentForm = reactive({ amount: '', date: today, method: 'cash', reference: '', note: '', purchase_id: null })

const purchaseItems = computed(() => [
    { title: localeStore.t('suppliers.noAdvance'), value: null },
    ...openPurchases.value.map((purchase) => ({
        title: `${purchase.purchase_no} · ${purchase.date} · ${money(purchase.due)}`,
        value: purchase.id,
    })),
])

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function methodColor(method) {
    return ({
        cash: 'success',
        bkash: 'primary',
        nagad: 'warning',
        card: 'info',
        bank: 'default',
    })[method] ?? 'default'
}

function methodTitle(method) {
    return localeStore.t(`options.method${method.charAt(0).toUpperCase()}${method.slice(1)}`)
}

async function load() {
    errorMessage.value = ''
    suppliersStore.clearCurrent()
    suppliersStore.clearError()

    try {
        await suppliersStore.fetchSupplier(supplierId.value, true)
    } catch {
        errorMessage.value = suppliersStore.error ?? localeStore.t('suppliers.loadFailed')
        return
    }

    try {
        await suppliersStore.fetchLedger(supplierId.value, true)
    } catch {
        errorMessage.value = suppliersStore.error ?? localeStore.t('suppliers.loadFailed')
    }
}

async function openPayment() {
    errorMessage.value = ''

    try {
        const response = await purchasesStore.fetchPurchases({
            supplier_id: supplierId.value,
            per_page: 100,
        }, true)

        openPurchases.value = response.data
            .filter((purchase) => Number(purchase.due) > 0)
            .sort((a, b) => a.date.localeCompare(b.date))
    } catch {
        openPurchases.value = []
    }

    Object.assign(paymentForm, {
        amount: '',
        date: today,
        method: 'cash',
        reference: '',
        note: '',
        purchase_id: null,
    })
    paymentDialog.value = true
}

function failure(error, fallback) {
    errorMessage.value = error.response?.data?.message
        ?? suppliersStore.error
        ?? localeStore.t(fallback)
}

async function submitPayment() {
    errorMessage.value = ''

    if (!(Number(paymentForm.amount) > 0)) {
        errorMessage.value = localeStore.t('pawns.paymentRequired')
        return
    }

    try {
        await suppliersStore.recordPayment(supplierId.value, {
            amount: Number(paymentForm.amount).toFixed(2),
            date: paymentForm.date,
            method: paymentForm.method,
            reference: paymentForm.reference.trim() || null,
            note: paymentForm.note.trim() || null,
            purchase_id: paymentForm.purchase_id ?? null,
        })
        paymentDialog.value = false
        await load()
    } catch (error) {
        failure(error, 'suppliers.paymentFailed')
    }
}

watch(supplierId, load)

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>
                            <v-btn
                                prepend-icon="mdi-arrow-left"
                                size="small"
                                variant="text"
                                @click="router.push({ name: 'suppliers' })"
                            >
                                {{ $t('nav.suppliers') }}
                            </v-btn>
                        </v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ supplier?.name ?? $t('suppliers.profile') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            <template v-if="supplier">
                                {{ supplier.code }}
                                <template v-if="supplier.phone"> · {{ supplier.phone }}</template>
                                · {{ $t(`options.${supplier.type}`) }}
                            </template>
                        </v-card-text>
                    </div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-btn
                            v-if="canManage"
                            color="primary"
                            prepend-icon="mdi-cash-plus"
                            @click="openPayment"
                        >
                            {{ $t('suppliers.addPayment') }}
                        </v-btn>
                        <v-btn
                            v-if="canManage"
                            color="secondary"
                            prepend-icon="mdi-pencil-outline"
                            variant="outlined"
                            :to="{ name: 'supplier-edit', params: { id: supplierId } }"
                        >
                            {{ $t('common.edit') }}
                        </v-btn>
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

                <v-progress-linear v-if="suppliersStore.loadingCurrent" indeterminate />
            </v-col>

            <template v-if="supplier">
                <v-col cols="12" md="4">
                    <v-card class="mb-6" elevation="2">
                        <v-card-text>
                            <v-row dense>
                                <v-col cols="4" md="12" class="text-center">
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('suppliers.balance') }}
                                    </div>
                                    <div
                                        class="text-h5 font-weight-bold"
                                        :class="{ 'text-error': Number(ledger.balance) > 0 }"
                                    >
                                        {{ money(ledger.balance) }}
                                    </div>
                                </v-col>
                                <v-col cols="4" md="12" class="text-center">
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('suppliers.totalPurchases') }}
                                    </div>
                                    <div class="text-h6 font-weight-medium">
                                        {{ money(ledger.total_purchases) }}
                                    </div>
                                </v-col>
                                <v-col cols="4" md="12" class="text-center">
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('suppliers.totalPayments') }}
                                    </div>
                                    <div class="text-h6 font-weight-medium">
                                        {{ money(ledger.total_payments) }}
                                    </div>
                                </v-col>
                            </v-row>

                            <v-divider class="my-4" />

                            <v-list density="compact" class="bg-transparent">
                                <v-list-item>
                                    <v-list-item-title>{{ $t('suppliers.selectType') }}</v-list-item-title>
                                    <template #append>
                                        <v-chip size="small" variant="tonal">
                                            {{ $t(`options.${supplier.type}`) }}
                                        </v-chip>
                                    </template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('nav.purchases') }}</v-list-item-title>
                                    <template #append>{{ supplier.purchases_count }}</template>
                                </v-list-item>
                                <v-list-item v-if="supplier.address">
                                    <v-list-item-title>{{ $t('common.address') }}</v-list-item-title>
                                    <template #append class="text-medium-emphasis">
                                        {{ supplier.address }}
                                    </template>
                                </v-list-item>
                            </v-list>

                            <template v-if="supplier.notes">
                                <v-divider class="my-4" />
                                <div class="text-body-2 text-medium-emphasis">{{ supplier.notes }}</div>
                            </template>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="8">
                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('suppliers.ledger') }}
                        </v-card-title>
                        <v-card-text class="pb-0">
                            <v-alert color="info" density="comfortable" variant="tonal">
                                {{ $t('suppliers.ledgerHint') }}
                            </v-alert>
                        </v-card-text>

                        <v-table v-if="ledger.transactions.length" density="comfortable">
                            <thead>
                                <tr>
                                    <th>{{ $t('common.date') }}</th>
                                    <th>{{ $t('common.reference') }}</th>
                                    <th class="text-end">{{ $t('common.amount') }}</th>
                                    <th class="text-end">{{ $t('common.paid') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in ledger.transactions" :key="`${row.kind}-${row.id}`">
                                    <td class="text-no-wrap">{{ row.date }}</td>
                                    <td>
                                        <v-chip
                                            :color="row.kind === 'purchase' ? 'primary' : 'success'"
                                            size="x-small"
                                            variant="tonal"
                                        >
                                            {{ $t(`options.${row.kind === 'purchase' ? 'ledgerRowPurchase' : 'ledgerRowPayment'}`) }}
                                        </v-chip>
                                        <div class="text-caption text-medium-emphasis">
                                            <router-link
                                                v-if="row.kind === 'purchase'"
                                                :to="{ name: 'purchase-profile', params: { id: row.id } }"
                                            >
                                                {{ row.reference }}
                                            </router-link>
                                            <template v-else>
                                                {{ row.reference ?? $t('options.advance') }}
                                                <template v-if="row.method">
                                                    · {{ methodTitle(row.method) }}
                                                </template>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span v-if="Number(row.debit) > 0" class="font-weight-medium">
                                            {{ money(row.debit) }}
                                        </span>
                                        <span v-else class="text-medium-emphasis">—</span>
                                    </td>
                                    <td class="text-end">
                                        <span v-if="Number(row.credit) > 0" class="font-weight-medium text-success">
                                            {{ money(row.credit) }}
                                        </span>
                                        <span v-else class="text-medium-emphasis">—</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">{{ $t('common.total') }}</th>
                                    <th class="text-end">{{ money(ledger.total_purchases) }}</th>
                                    <th class="text-end text-success">{{ money(ledger.total_payments) }}</th>
                                </tr>
                                <tr>
                                    <th colspan="2">{{ $t('suppliers.balance') }}</th>
                                    <th class="text-end" colspan="2">
                                        {{ money(ledger.balance) }}
                                    </th>
                                </tr>
                            </tfoot>
                        </v-table>

                        <v-card-text v-else class="text-medium-emphasis">
                            {{ $t('suppliers.noTransactions') }}
                        </v-card-text>
                    </v-card>
                </v-col>
            </template>
        </v-row>

        <v-dialog v-model="paymentDialog" max-width="520">
            <v-card>
                <v-card-title>{{ $t('suppliers.paymentTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        {{ $t('suppliers.paymentBody') }}
                    </v-alert>
                    <v-alert
                        v-if="ledger"
                        class="mb-4"
                        color="primary"
                        density="comfortable"
                        variant="tonal"
                    >
                        {{ $t('suppliers.balance') }}: {{ money(ledger.balance) }}
                    </v-alert>
                    <v-select
                        v-model="paymentForm.purchase_id"
                        clearable
                        :items="purchaseItems"
                        :label="$t('suppliers.againstPurchase')"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="paymentForm.amount"
                        :label="$t('common.amount')"
                        min="0.01"
                        step="0.01"
                        :suffix="currencySymbol"
                        type="number"
                        variant="outlined"
                    />
                    <v-select
                        v-model="paymentForm.method"
                        :items="methodItems"
                        :label="$t('common.method')"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="paymentForm.date"
                        :label="$t('common.date')"
                        type="date"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="paymentForm.reference"
                        :label="$t('common.reference')"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="paymentForm.note"
                        :label="$t('common.notes')"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="paymentDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="suppliersStore.saving" @click="submitPayment">
                        {{ $t('suppliers.recordPayment') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
