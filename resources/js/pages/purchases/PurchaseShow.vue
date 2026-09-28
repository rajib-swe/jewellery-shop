<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLocaleStore } from '../../stores/locale'
import { usePurchasesStore } from '../../stores/purchases'
import { useCurrency, useWeightFormatter } from '../../utils/format'

const route = useRoute()
const router = useRouter()
const localeStore = useLocaleStore()
const purchasesStore = usePurchasesStore()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()
const purchaseId = computed(() => route.params.id)
const purchase = computed(() => purchasesStore.currentPurchase)
const errorMessage = ref('')

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

const methodTitle = (method) => localeStore.t(`options.method${method.charAt(0).toUpperCase()}${method.slice(1)}`)

const totalNetWeight = computed(() => (purchase.value?.items ?? []).reduce(
    (total, item) => total + Number(item.net_weight),
    0,
))

async function load() {
    errorMessage.value = ''
    purchasesStore.clearCurrent()
    purchasesStore.clearError()

    try {
        await purchasesStore.fetchPurchase(purchaseId.value, true)
    } catch {
        errorMessage.value = purchasesStore.error ?? localeStore.t('purchases.loadFailed')
    }
}

watch(purchaseId, load)

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
                                @click="router.push({ name: 'purchases' })"
                            >
                                {{ $t('nav.purchases') }}
                            </v-btn>
                        </v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ purchase?.purchase_no ?? $t('purchases.listTitle') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            <template v-if="purchase">
                                {{ purchase.date }}
                                <template v-if="purchase.supplier">
                                    ·
                                    <router-link
                                        :to="{ name: 'supplier-profile', params: { id: purchase.supplier.id } }"
                                    >
                                        {{ purchase.supplier.name }}
                                    </router-link>
                                    ({{ $t(`options.${purchase.supplier.type}`) }})
                                </template>
                                <template v-if="purchase.user"> · {{ purchase.user.name }}</template>
                            </template>
                        </v-card-text>
                    </div>

                    <v-btn
                        v-if="purchase?.supplier"
                        color="primary"
                        prepend-icon="mdi-cash-plus"
                        :to="{ name: 'supplier-profile', params: { id: purchase.supplier.id } }"
                    >
                        {{ $t('suppliers.ledger') }}
                    </v-btn>
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

                <v-progress-linear v-if="purchasesStore.loadingCurrent" indeterminate />
            </v-col>

            <template v-if="purchase">
                <v-col cols="12" lg="8">
                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('purchases.items') }}
                        </v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>{{ $t('inventory.tagNo') }}</th>
                                    <th>{{ $t('common.name') }}</th>
                                    <th>{{ $t('inventory.karat') }}</th>
                                    <th class="text-end">{{ $t('inventory.netWeight') }}</th>
                                    <th class="text-end">{{ $t('sales.ratePerGram') }}</th>
                                    <th class="text-end">{{ $t('sales.making') }}</th>
                                    <th class="text-end">{{ $t('common.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in purchase.items" :key="item.id">
                                    <td>
                                        <v-chip size="x-small" variant="tonal">
                                            {{ $t('purchases.inStock') }}
                                        </v-chip>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ item.tag_no }}
                                        </div>
                                    </td>
                                    <td class="font-weight-medium">{{ item.name }}</td>
                                    <td>{{ item.karat }}K</td>
                                    <td class="text-end">{{ formatWeight(item.net_weight) }}</td>
                                    <td class="text-end">{{ money(item.rate) }}</td>
                                    <td class="text-end">
                                        <span v-if="Number(item.making_value) > 0">
                                            {{ money(item.making_value) }}
                                        </span>
                                        <span v-else class="text-medium-emphasis">—</span>
                                    </td>
                                    <td class="text-end font-weight-medium">{{ money(item.amount) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">{{ $t('common.total') }}</th>
                                    <th class="text-end">{{ formatWeight(totalNetWeight) }}</th>
                                    <th colspan="2" />
                                    <th class="text-end">{{ money(purchase.subtotal) }}</th>
                                </tr>
                            </tfoot>
                        </v-table>
                    </v-card>

                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('purchases.payNow') }}
                        </v-card-title>
                        <v-list v-if="purchase.payments.length" class="bg-transparent">
                            <v-list-item
                                v-for="payment in purchase.payments"
                                :key="payment.id"
                                :subtitle="`${payment.date} · ${payment.user?.name ?? ''}`"
                                :title="money(payment.amount)"
                            >
                                <template #prepend>
                                    <v-chip :color="methodColor(payment.method)" size="small" variant="tonal">
                                        {{ methodTitle(payment.method) }}
                                    </v-chip>
                                </template>
                                <template #append>
                                    <span
                                        v-if="payment.reference"
                                        class="text-caption text-medium-emphasis"
                                    >
                                        {{ payment.reference }}
                                    </span>
                                </template>
                            </v-list-item>
                        </v-list>
                        <v-card-text v-else class="text-medium-emphasis">
                            {{ $t('purchases.noPayments') }}
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="4">
                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('common.total') }}
                        </v-card-title>
                        <v-card-text>
                            <v-list density="compact" class="bg-transparent">
                                <v-list-item>
                                    <v-list-item-title>{{ $t('common.subtotal') }}</v-list-item-title>
                                    <template #append>{{ money(purchase.subtotal) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('common.discount') }}</v-list-item-title>
                                    <template #append>- {{ money(purchase.discount) }}</template>
                                </v-list-item>
                            </v-list>

                            <v-divider class="my-3" />

                            <div class="d-flex justify-space-between text-subtitle-1 font-weight-bold">
                                <span>{{ $t('common.total') }}</span>
                                <span>{{ money(purchase.total) }}</span>
                            </div>
                            <div class="d-flex justify-space-between text-body-2 mt-2">
                                <span>{{ $t('common.paid') }}</span>
                                <span>{{ money(purchase.paid) }}</span>
                            </div>
                            <div class="d-flex justify-space-between text-body-2 mt-1">
                                <span>{{ $t('common.due') }}</span>
                                <span :class="{ 'text-error font-weight-medium': Number(purchase.due) > 0 }">
                                    {{ money(purchase.due) }}
                                </span>
                            </div>

                            <template v-if="purchase.notes">
                                <v-divider class="my-3" />
                                <div class="text-body-2 text-medium-emphasis">{{ purchase.notes }}</div>
                            </template>
                        </v-card-text>
                    </v-card>
                </v-col>
            </template>
        </v-row>
    </v-container>
</template>
