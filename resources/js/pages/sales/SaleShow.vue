<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PAYMENT_METHOD_OPTIONS } from '../../constants/sales'
import { useAuthStore } from '../../stores/auth'
import { useSalesStore } from '../../stores/sales'
import { useSettingsStore } from '../../stores/settings'
import { gramsToTraditional } from '../../utils/weight'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const salesStore = useSalesStore()
const settingsStore = useSettingsStore()
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

const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')
const weightUnit = computed(() => settingsStore.settings.weight_unit || 'gram')

function money(value) {
    return `${currencySymbol.value}${Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function formatWeight(grams) {
    const value = Number(grams || 0)

    if (weightUnit.value === 'vori') {
        return `${gramsToTraditional(value).vori.toFixed(4)} vori`
    }

    return `${value.toFixed(3)} g`
}

function methodColor(method) {
    return {
        cash: 'success',
        bkash: 'primary',
        nagad: 'warning',
        card: 'info',
        bank: 'default',
    }[method] ?? 'default'
}

async function load() {
    errorMessage.value = ''
    salesStore.clearCurrent()

    try {
        await Promise.all([
            settingsStore.fetchSettings(),
            salesStore.fetchSale(saleId.value, true),
        ])
    } catch {
        errorMessage.value = salesStore.error ?? 'Unable to load the sale.'
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
        errorMessage.value = 'Enter a payment amount greater than zero.'
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
        errorMessage.value = error.response?.data?.message ?? salesStore.error ?? 'Unable to record the payment.'
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
        errorMessage.value = error.response?.data?.message ?? salesStore.error ?? 'Unable to void the sale.'
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
                                Sales
                            </v-btn>
                        </v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ sale?.invoice_no ?? 'Sale' }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            <template v-if="sale">
                                {{ sale.date }}
                                <template v-if="sale.customer">
                                    · {{ sale.customer.name }} ({{ sale.customer.phone }})
                                </template>
                                <template v-else>· Walk-in customer</template>
                                <template v-if="sale.user"> · Sold by {{ sale.user.name }}</template>
                            </template>
                        </v-card-text>
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn
                            v-if="canManage && sale && !isVoided && Number(sale.due) > 0"
                            color="primary"
                            prepend-icon="mdi-cash-plus"
                            @click="openPayment"
                        >
                            Add due payment
                        </v-btn>
                        <v-btn
                            v-if="canVoid && sale && !isVoided"
                            color="error"
                            prepend-icon="mdi-undo-variant"
                            variant="outlined"
                            @click="openVoid"
                        >
                            Void sale
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
                    This sale was voided{{ sale.voided_at ? ` on ${new Date(sale.voided_at).toLocaleString()}` : '' }}.
                    <template v-if="sale.void_reason">Reason: {{ sale.void_reason }}</template>
                </v-alert>

                <v-progress-linear v-if="salesStore.loadingCurrent" indeterminate />
            </v-col>

            <template v-if="sale">
                <v-col cols="12" lg="8">
                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">Items</v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Weight</th>
                                    <th class="text-end">Rate/g</th>
                                    <th class="text-end">Gold value</th>
                                    <th class="text-end">Making</th>
                                    <th class="text-end">Line total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in sale.items" :key="line.id">
                                    <td>
                                        <div class="font-weight-medium">{{ line.name }}</div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ line.tag_no }} · {{ line.karat }}K
                                        </div>
                                    </td>
                                    <td class="text-end">{{ formatWeight(line.weight) }}</td>
                                    <td class="text-end">{{ money(line.rate) }}</td>
                                    <td class="text-end">{{ money(line.gold_value) }}</td>
                                    <td class="text-end">
                                        {{ money(line.making) }}
                                        <div v-if="Number(line.stone_price) > 0" class="text-caption text-medium-emphasis">
                                            + {{ money(line.stone_price) }} stone
                                        </div>
                                    </td>
                                    <td class="text-end font-weight-medium">{{ money(line.line_total) }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>

                    <v-card v-if="sale.exchanges.length" class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">Old gold exchange</v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Karat</th>
                                    <th class="text-end">Weight</th>
                                    <th class="text-end">Rate/g</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="exchange in sale.exchanges" :key="exchange.id">
                                    <td>
                                        {{ exchange.description }}
                                        <div v-if="exchange.item_id" class="text-caption text-medium-emphasis">
                                            Kept as a scrap item
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
                        <v-card-title class="text-subtitle-1 font-weight-medium">Payments</v-card-title>
                        <v-list v-if="sale.payments.length" class="bg-transparent">
                            <v-list-item
                                v-for="payment in sale.payments"
                                :key="payment.id"
                                :title="money(payment.amount)"
                                :subtitle="payment.user?.name"
                            >
                                <template #prepend>
                                    <v-chip :color="methodColor(payment.method)" size="small" variant="tonal">
                                        {{ payment.method }}
                                    </v-chip>
                                </template>
                                <template v-if="payment.reference" #append>
                                    <span class="text-caption text-medium-emphasis">{{ payment.reference }}</span>
                                </template>
                            </v-list-item>
                        </v-list>
                        <v-card-text v-else class="text-medium-emphasis">
                            No payment recorded for this sale.
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="4">
                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">Totals</v-card-title>
                        <v-card-text>
                            <v-list density="compact" class="bg-transparent">
                                <v-list-item>
                                    <v-list-item-title>Subtotal</v-list-item-title>
                                    <template #append>{{ money(sale.subtotal) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>Discount</v-list-item-title>
                                    <template #append>- {{ money(sale.discount) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>VAT</v-list-item-title>
                                    <template #append>{{ money(sale.vat) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>Old gold exchange</v-list-item-title>
                                    <template #append>- {{ money(sale.exchange_amount) }}</template>
                                </v-list-item>
                            </v-list>

                            <v-divider class="my-3" />

                            <div class="d-flex justify-space-between text-subtitle-1 font-weight-bold">
                                <span>Total</span>
                                <span>{{ money(sale.total) }}</span>
                            </div>
                            <div class="d-flex justify-space-between text-body-2 mt-2">
                                <span>Paid</span>
                                <span>{{ money(sale.paid) }}</span>
                            </div>
                            <div class="d-flex justify-space-between text-body-2 mt-1">
                                <span>Due</span>
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
                <v-card-title>Add a due payment</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        Outstanding due: {{ money(sale?.due) }}
                    </v-alert>
                    <v-select
                        v-model="paymentForm.method"
                        :items="PAYMENT_METHOD_OPTIONS"
                        label="Method"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="paymentForm.amount"
                        label="Amount"
                        min="0.01"
                        step="0.01"
                        :suffix="currencySymbol"
                        type="number"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="paymentForm.reference"
                        label="Reference"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="paymentDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="salesStore.saving" @click="submitPayment">Record payment</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="voidDialog" max-width="480">
            <v-card>
                <v-card-title>Void this sale?</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="warning" density="comfortable" variant="tonal">
                        The items return to stock, the exchange scrap is removed, and every payment is reversed.
                    </v-alert>
                    <v-textarea
                        v-model="voidForm.reason"
                        label="Reason"
                        rows="2"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="voidDialog = false">Cancel</v-btn>
                    <v-btn color="error" :loading="salesStore.saving" @click="confirmVoid">Void sale</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
