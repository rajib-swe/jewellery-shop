<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { useSalesStore } from '../../stores/sales'
import { useCurrency, useWeightFormatter } from '../../utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const salesStore = useSalesStore()
const { paymentMethodOptions: paymentItems } = useOptionLabels()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()
const saleId = computed(() => route.params.id)
const canManage = computed(() => authStore.can('manage sales'))
const canVoid = computed(() => authStore.can('void sales'))
const sale = computed(() => salesStore.currentSale)
const isVoided = computed(() => sale.value?.status === 'void')
const errorMessage = ref('')
const paymentDialog = ref(false)
const voidDialog = ref(false)
const paymentForm = reactive({ method: 'cash', amount: '', reference: '' })
const voidForm = reactive({ reason: '' })

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const methodColor = (method) => ({
    cash: 'success',
    bkash: 'primary',
    nagad: 'warning',
    card: 'info',
    bank: 'default',
}[method] ?? 'default')

function capitalize(value) {
    return String(value).charAt(0).toUpperCase() + String(value).slice(1)
}

function openPdf(id, size = 'a4', download = false) {
    const query = new URLSearchParams()
    if (size) {
        query.set('size', size)
    }
    if (download) {
        query.set('download', '1')
    } else {
        query.set('format', 'html')
        query.set('print', '1')
    }
    window.open(`/sales/${id}/invoice?${query.toString()}`, '_blank')
}

function openReceipt(paymentId, size = 'a4', download = false) {
    const query = new URLSearchParams()
    if (size) {
        query.set('size', size)
    }
    if (download) {
        query.set('download', '1')
    } else {
        query.set('format', 'html')
        query.set('print', '1')
    }
    window.open(`/payments/${paymentId}/receipt?${query.toString()}`, '_blank')
}

async function load() {
    errorMessage.value = ''
    salesStore.clearCurrent()

    try {
        await salesStore.fetchSale(saleId.value, true)
    } catch {
        errorMessage.value = salesStore.error ?? localeStore.t('sales.loadFailed')
    }
}

function openPayment() {
    errorMessage.value = ''
    Object.assign(paymentForm, {
        method: 'cash',
        amount: sale.value?.due ?? '',
        reference: '',
    })
    paymentDialog.value = true
}

async function submitPayment() {
    errorMessage.value = ''

    if (!(Number(paymentForm.amount) > 0)) {
        errorMessage.value = localeStore.t('sales.amountRequired')
        return
    }

    try {
        await salesStore.recordPayment(saleId.value, {
            method: paymentForm.method,
            amount: Number(paymentForm.amount).toFixed(2),
            reference: paymentForm.reference.trim() || null,
        })
        paymentDialog.value = false
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? salesStore.error
            ?? localeStore.t('sales.paymentFailed')
    }
}

function openVoid() {
    errorMessage.value = ''
    voidForm.reason = ''
    voidDialog.value = true
}

async function confirmVoid() {
    errorMessage.value = ''

    try {
        await salesStore.voidCurrentSale(saleId.value, voidForm.reason.trim() || null)
        voidDialog.value = false
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? salesStore.error
            ?? localeStore.t('sales.voidFailed')
    }
}

watch(saleId, load)

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
                                @click="router.push({ name: 'sales' })"
                            >
                                {{ $t('nav.sales') }}
                            </v-btn>
                        </v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ sale?.invoice_no ?? $t('sales.invoice') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            <template v-if="sale">
                                {{ sale.date }}
                                <template v-if="sale.customer">
                                    · {{ sale.customer.name }} ({{ sale.customer.phone }})
                                </template>
                                <template v-else>· {{ $t('sales.walkInCustomer') }}</template>
                                <template v-if="sale.user">
                                    · {{ $t('sales.soldBy') }} {{ sale.user.name }}
                                </template>
                            </template>
                        </v-card-text>
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-menu v-if="sale">
                            <template #activator="{ props }">
                                <v-btn
                                    color="secondary"
                                    prepend-icon="mdi-printer"
                                    v-bind="props"
                                >
                                    {{ $t('sales.printInvoice') }}
                                </v-btn>
                            </template>
                            <v-list density="compact">
                                <v-list-item
                                    prepend-icon="mdi-file-document-outline"
                                    :title="$t('sales.printA4')"
                                    @click="openPdf(sale.id, 'a4')"
                                />
                                <v-list-item
                                    prepend-icon="mdi-receipt-text-outline"
                                    :title="$t('sales.printThermal')"
                                    @click="openPdf(sale.id, 'thermal')"
                                />
                                <v-divider />
                                <v-list-item
                                    prepend-icon="mdi-download"
                                    :title="$t('sales.downloadPdf')"
                                    @click="openPdf(sale.id, 'a4', true)"
                                />
                            </v-list>
                        </v-menu>
                        <v-btn
                            v-if="canManage && sale && !isVoided && Number(sale.due) > 0"
                            color="primary"
                            prepend-icon="mdi-cash-plus"
                            @click="openPayment"
                        >
                            {{ $t('sales.addDuePayment') }}
                        </v-btn>
                        <v-btn
                            v-if="canVoid && sale && !isVoided"
                            color="error"
                            prepend-icon="mdi-undo-variant"
                            variant="outlined"
                            @click="openVoid"
                        >
                            {{ $t('sales.voidSale') }}
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

                <v-alert
                    v-if="isVoided"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                >
                    {{ $t('sales.voidSale') }}<template v-if="sale.voided_at">
                        · {{ new Date(sale.voided_at).toLocaleString() }}
                    </template>
                    <template v-if="sale.void_reason"> · {{ sale.void_reason }}</template>
                </v-alert>

                <v-progress-linear v-if="salesStore.loadingCurrent" indeterminate />
            </v-col>

            <template v-if="sale">
                <v-col cols="12" lg="8">
                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('sales.items') }}</v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>{{ $t('sales.items') }}</th>
                                    <th class="text-end">{{ $t('inventory.netWeight') }}</th>
                                    <th class="text-end">{{ $t('sales.ratePerGram') }}</th>
                                    <th class="text-end">{{ $t('sales.goldValue') }}</th>
                                    <th class="text-end">{{ $t('sales.making') }}</th>
                                    <th class="text-end">{{ $t('sales.lineTotal') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in sale.items" :key="line.id">
                                    <td>
                                        <div class="font-weight-medium">{{ line.name }}</div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ line.item_id ? line.tag_no : $t('sales.handwrittenTag') }}
                                            · {{ line.karat }}K
                                        </div>
                                    </td>
                                    <td class="text-end">{{ formatWeight(line.weight) }}</td>
                                    <td class="text-end">{{ money(line.rate) }}</td>
                                    <td class="text-end">{{ money(line.gold_value) }}</td>
                                    <td class="text-end">
                                        {{ money(line.making) }}
                                        <div v-if="Number(line.stone_price) > 0" class="text-caption text-medium-emphasis">
                                            + {{ money(line.stone_price) }} {{ $t('inventory.stonePrice') }}
                                        </div>
                                    </td>
                                    <td class="text-end font-weight-medium">{{ money(line.line_total) }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>

                    <v-card v-if="sale.exchanges.length" class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('sales.oldGoldExchange') }}
                        </v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>{{ $t('sales.description') }}</th>
                                    <th>{{ $t('inventory.karat') }}</th>
                                    <th class="text-end">{{ $t('inventory.netWeight') }}</th>
                                    <th class="text-end">{{ $t('sales.ratePerGram') }}</th>
                                    <th class="text-end">{{ $t('common.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="exchange in sale.exchanges" :key="exchange.id">
                                    <td>
                                        {{ exchange.description }}
                                        <div v-if="exchange.item_id" class="text-caption text-medium-emphasis">
                                            {{ $t('sales.storeAsScrap') }}
                                        </div>
                                    </td>
                                    <td>{{ exchange.karat }}K</td>
                                    <td class="text-end">{{ formatWeight(exchange.weight) }}</td>
                                    <td class="text-end">{{ money(exchange.rate) }}</td>
                                    <td class="text-end font-weight-medium">{{ money(exchange.amount) }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>

                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('sales.payments') }}</v-card-title>
                        <v-list v-if="sale.payments.length" class="bg-transparent">
                            <v-list-item
                                v-for="payment in sale.payments"
                                :key="payment.id"
                                :title="money(payment.amount)"
                                :subtitle="payment.user?.name"
                            >
                                <template #prepend>
                                    <v-chip :color="methodColor(payment.method)" size="small" variant="tonal">
                                        {{ $t(`options.method${capitalize(payment.method)}`) }}
                                    </v-chip>
                                </template>
                                <template #append>
                                    <div class="d-flex align-center ga-2">
                                        <span v-if="payment.reference" class="text-caption text-medium-emphasis">{{ payment.reference }}</span>
                                        <v-btn
                                            density="compact"
                                            icon="mdi-printer"
                                            size="small"
                                            variant="text"
                                            :title="$t('sales.printReceipt')"
                                            @click="openReceipt(payment.id, 'a4')"
                                        />
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                        <v-card-text v-else class="text-medium-emphasis">
                            {{ $t('sales.noPayment') }}
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="4">
                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('common.total') }}</v-card-title>
                        <v-card-text>
                            <v-list density="compact" class="bg-transparent">
                                <v-list-item>
                                    <v-list-item-title>{{ $t('common.subtotal') }}</v-list-item-title>
                                    <template #append>{{ money(sale.subtotal) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('common.discount') }}</v-list-item-title>
                                    <template #append>- {{ money(sale.discount) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('common.vat') }}</v-list-item-title>
                                    <template #append>{{ money(sale.vat) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('sales.oldGoldExchange') }}</v-list-item-title>
                                    <template #append>- {{ money(sale.exchange_amount) }}</template>
                                </v-list-item>
                            </v-list>

                            <v-divider class="my-3" />

                            <div class="d-flex justify-space-between text-subtitle-1 font-weight-bold">
                                <span>{{ $t('common.total') }}</span>
                                <span>{{ money(sale.total) }}</span>
                            </div>
                            <div class="d-flex justify-space-between text-body-2 mt-2">
                                <span>{{ $t('common.paid') }}</span>
                                <span>{{ money(sale.paid) }}</span>
                            </div>
                            <div class="d-flex justify-space-between text-body-2 mt-1">
                                <span>{{ $t('common.due') }}</span>
                                <span :class="{ 'text-error font-weight-medium': Number(sale.due) > 0 }">
                                    {{ money(sale.due) }}
                                </span>
                            </div>

                            <template v-if="sale.notes">
                                <v-divider class="my-3" />
                                <div class="text-body-2 text-medium-emphasis">{{ sale.notes }}</div>
                            </template>
                        </v-card-text>
                    </v-card>
                </v-col>
            </template>
        </v-row>

        <v-dialog v-model="paymentDialog" max-width="480">
            <v-card>
                <v-card-title>{{ $t('sales.paymentTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        {{ $t('sales.outstandingDue', { amount: money(sale?.due) }) }}
                    </v-alert>
                    <v-select
                        v-model="paymentForm.method"
                        :items="paymentItems"
                        :label="$t('common.method')"
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
                    <v-text-field
                        v-model="paymentForm.reference"
                        :label="$t('common.reference')"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="paymentDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="salesStore.saving" @click="submitPayment">
                        {{ $t('sales.recordPayment') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="voidDialog" max-width="480">
            <v-card>
                <v-card-title>{{ $t('sales.voidTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="warning" density="comfortable" variant="tonal">
                        {{ $t('sales.voidBody') }}
                    </v-alert>
                    <v-textarea
                        v-model="voidForm.reason"
                        :label="$t('sales.reason')"
                        rows="2"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="voidDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" :loading="salesStore.saving" @click="confirmVoid">
                        {{ $t('sales.voidSale') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
