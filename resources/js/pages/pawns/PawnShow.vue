<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { usePawnsStore } from '../../stores/pawns'
import { useSettingsStore } from '../../stores/settings'
import { useCurrency, useWeightFormatter } from '../../utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const pawnsStore = usePawnsStore()
const settingsStore = useSettingsStore()
const { paymentMethodOptions: methodItems, itemStatusOptions: statusItems } = useOptionLabels()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()
const pawnId = computed(() => route.params.id)
const pawn = computed(() => pawnsStore.currentPawn)
const summary = computed(() => pawn.value?.summary ?? null)
const canManage = computed(() => authStore.can('manage pawns'))
const canForfeit = computed(() => authStore.can('forfeit pawns'))
const isOpen = computed(() => pawn.value?.status === 'active')
const errorMessage = ref('')
const paymentDialog = ref(false)
const redeemDialog = ref(false)
const renewDialog = ref(false)
const forfeitDialog = ref(false)
const paymentForm = reactive({ amount: '', date: today(), method: 'cash', reference: '', note: '' })
const redeemForm = reactive({ date: today(), method: 'cash', reference: '', note: '' })
const renewForm = reactive({ date: today(), term_days: null, note: '' })
const forfeitForm = reactive({ reason: '', move_to_inventory: true, item_status: 'scrap' })
const graceDays = computed(() => Number(settingsStore.settings.pawn_grace_days || 0))

function today() {
    return new Date().toISOString().slice(0, 10)
}

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

const paymentTypeColor = (type) => ({
    interest: 'warning',
    principal: 'primary',
    redeem: 'success',
}[type] ?? 'default')

const paymentTypeTitle = (type) => localeStore.t(`options.pawnType${type.charAt(0).toUpperCase()}${type.slice(1)}`)

const statusColor = computed(() => {
    if (!isOpen.value) {
        return pawn.value?.status === 'redeemed' ? 'success' : 'error'
    }

    return pawn.value?.is_overdue ? 'warning' : 'info'
})

const statusTitle = computed(() => {
    if (!isOpen.value) {
        return localeStore.t(`options.${pawn.value?.status}`)
    }

    return pawn.value?.is_overdue
        ? localeStore.t('options.overdue')
        : localeStore.t('options.active')
})

const pledgeTotals = computed(() => {
    const items = pawn.value?.items ?? []

    return {
        count: items.length,
        netWeight: items.reduce((total, item) => total + Number(item.net_weight), 0),
        value: items.reduce((total, item) => total + Number(item.estimated_value), 0),
    }
})

function openDocument(path, size = 'a4', download = false) {
    const query = new URLSearchParams()
    query.set('size', size)

    if (download) {
        query.set('download', '1')
    } else {
        query.set('format', 'html')
        query.set('print', '1')
    }

    window.open(`${path}?${query.toString()}`, '_blank')
}

function printTicket(size = 'a4', download = false) {
    openDocument(`/pawns/${pawnId.value}/ticket`, size, download)
}

function printReceipt(paymentId, size = 'a4', download = false) {
    openDocument(`/pawn-payments/${paymentId}/receipt`, size, download)
}

function openPayment() {
    errorMessage.value = ''
    Object.assign(paymentForm, {
        amount: summary.value?.total_payable ?? '',
        date: today(),
        method: 'cash',
        reference: '',
        note: '',
    })
    paymentDialog.value = true
}

function openRedeem() {
    errorMessage.value = ''
    Object.assign(redeemForm, {
        date: today(),
        method: 'cash',
        reference: '',
        note: '',
    })
    redeemDialog.value = true
}

function openRenew() {
    errorMessage.value = ''
    Object.assign(renewForm, {
        date: today(),
        term_days: Number(settingsStore.settings.pawn_term_days || 30),
        note: '',
    })
    renewDialog.value = true
}

function openForfeit() {
    errorMessage.value = ''
    Object.assign(forfeitForm, { reason: '', move_to_inventory: true, item_status: 'scrap' })
    forfeitDialog.value = true
}

function failure(error, fallback) {
    errorMessage.value = error.response?.data?.message
        ?? pawnsStore.error
        ?? localeStore.t(fallback)
}

async function submitPayment() {
    errorMessage.value = ''

    if (!(Number(paymentForm.amount) > 0)) {
        errorMessage.value = localeStore.t('pawns.paymentRequired')
        return
    }

    try {
        await pawnsStore.recordPayment(pawnId.value, {
            amount: Number(paymentForm.amount).toFixed(2),
            date: paymentForm.date,
            method: paymentForm.method,
            reference: paymentForm.reference.trim() || null,
            note: paymentForm.note.trim() || null,
        })
        paymentDialog.value = false
    } catch (error) {
        failure(error, 'pawns.paymentFailed')
    }
}

async function submitRedeem() {
    errorMessage.value = ''

    try {
        await pawnsStore.redeemCurrentPawn(pawnId.value, {
            date: redeemForm.date,
            method: redeemForm.method,
            reference: redeemForm.reference.trim() || null,
            note: redeemForm.note.trim() || null,
        })
        redeemDialog.value = false
    } catch (error) {
        failure(error, 'pawns.redeemFailed')
    }
}

async function submitRenew() {
    errorMessage.value = ''

    try {
        await pawnsStore.renewCurrentPawn(pawnId.value, {
            date: renewForm.date,
            term_days: renewForm.term_days ? Number(renewForm.term_days) : null,
            note: renewForm.note.trim() || null,
        })
        renewDialog.value = false
    } catch (error) {
        failure(error, 'pawns.renewFailed')
    }
}

async function submitForfeit() {
    errorMessage.value = ''

    try {
        await pawnsStore.forfeitCurrentPawn(pawnId.value, {
            reason: forfeitForm.reason.trim() || null,
            move_to_inventory: forfeitForm.move_to_inventory,
            item_status: forfeitForm.item_status,
        })
        forfeitDialog.value = false
    } catch (error) {
        failure(error, 'pawns.forfeitFailed')
    }
}

function printLatestReceipt(size) {
    const payments = pawn.value?.payments ?? []
    const latest = payments[payments.length - 1]

    if (latest) {
        printReceipt(latest.id, size)
    }
}

async function load() {
    errorMessage.value = ''
    pawnsStore.clearCurrent()

    try {
        await settingsStore.fetchSettings()
    } catch {
        // The grace period hint is optional, the page still works without it.
    }

    try {
        await pawnsStore.fetchPawn(pawnId.value, true)
    } catch {
        errorMessage.value = pawnsStore.error ?? localeStore.t('pawns.loadFailed')
    }
}

watch(pawnId, load)

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
                                @click="router.push({ name: 'pawns' })"
                            >
                                {{ $t('nav.pawns') }}
                            </v-btn>
                        </v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ pawn?.pawn_no ?? $t('pawns.profile') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            <template v-if="pawn">
                                {{ pawn.date }}
                                <template v-if="pawn.customer">
                                    · <router-link
                                        :to="{ name: 'customer-profile', params: { id: pawn.customer.id } }"
                                    >
                                        {{ pawn.customer.name }} ({{ pawn.customer.phone }})
                                    </router-link>
                                </template>
                                <template v-if="pawn.user"> · {{ pawn.user.name }}</template>
                            </template>
                        </v-card-text>
                    </div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-menu v-if="pawn">
                            <template #activator="{ props }">
                                <v-btn color="secondary" prepend-icon="mdi-printer" v-bind="props">
                                    {{ $t('pawns.printTicket') }}
                                </v-btn>
                            </template>
                            <v-list density="compact">
                                <v-list-item
                                    prepend-icon="mdi-file-document-outline"
                                    :title="$t('pawns.printA4')"
                                    @click="printTicket('a4')"
                                />
                                <v-list-item
                                    prepend-icon="mdi-receipt-text-outline"
                                    :title="$t('pawns.printThermal')"
                                    @click="printTicket('thermal')"
                                />
                                <v-divider />
                                <v-list-item
                                    prepend-icon="mdi-download"
                                    :title="$t('pawns.downloadPdf')"
                                    @click="printTicket('a4', true)"
                                />
                            </v-list>
                        </v-menu>

                        <template v-if="canManage && isOpen">
                            <v-btn color="primary" prepend-icon="mdi-cash-plus" @click="openPayment">
                                {{ $t('pawns.addPayment') }}
                            </v-btn>
                            <v-btn
                                color="success"
                                prepend-icon="mdi-check-circle-outline"
                                variant="tonaled"
                                @click="openRedeem"
                            >
                                {{ $t('pawns.redeem') }}
                            </v-btn>
                            <v-btn
                                color="info"
                                prepend-icon="mdi-calendar-refresh"
                                variant="outlined"
                                @click="openRenew"
                            >
                                {{ $t('pawns.renew') }}
                            </v-btn>
                        </template>

                        <v-btn
                            v-if="canForfeit && isOpen"
                            color="error"
                            prepend-icon="mdi-gavel"
                            variant="outlined"
                            @click="openForfeit"
                        >
                            {{ $t('pawns.forfeit') }}
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

                <v-alert
                    v-if="pawn && !isOpen"
                    class="mb-6"
                    :color="pawn.status === 'redeemed' ? 'success' : 'error'"
                    density="comfortable"
                    variant="tonal"
                >
                    <template v-if="pawn.status === 'redeemed'">
                        {{ $t('pawns.closedTitle') }} · {{ $t('pawns.closedBody') }}
                        <template v-if="pawn.redeemed_at">
                            · {{ new Date(pawn.redeemed_at).toLocaleString() }}
                        </template>
                    </template>
                    <template v-else>
                        {{ $t('pawns.forfeitedBody') }}
                        <template v-if="pawn.forfeited_at">
                            · {{ new Date(pawn.forfeited_at).toLocaleString() }}
                        </template>
                    </template>
                    <template v-if="pawn.close_reason"> · {{ pawn.close_reason }}</template>
                </v-alert>

                <v-alert
                    v-else-if="pawn?.is_overdue"
                    class="mb-6"
                    color="warning"
                    density="comfortable"
                    variant="tonal"
                >
                    {{ $t('options.overdue') }} · {{ $t('pawns.dueDate') }} {{ pawn.due_date }}
                    (+{{ graceDays }}d)
                </v-alert>

                <v-progress-linear v-if="pawnsStore.loadingCurrent" indeterminate />
            </v-col>

            <template v-if="pawn">
                <v-col cols="12" lg="8">
                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('pawns.pledgedItems') }}
                        </v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>{{ $t('pawns.itemDescription') }}</th>
                                    <th>{{ $t('inventory.karat') }}</th>
                                    <th class="text-end">{{ $t('inventory.grossWeight') }}</th>
                                    <th class="text-end">{{ $t('inventory.netWeight') }}</th>
                                    <th class="text-end">{{ $t('pawns.estimatedValue') }}</th>
                                    <th class="text-end">{{ $t('inventory.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in pawn.items" :key="item.id">
                                    <td>
                                        <div class="d-flex align-center ga-3">
                                            <v-avatar
                                                v-if="item.photo_url"
                                                :image="item.photo_url"
                                                rounded="lg"
                                                size="40"
                                            />
                                            <div>
                                                <div class="font-weight-medium">{{ item.description }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ item.karat }}K</td>
                                    <td class="text-end">{{ formatWeight(item.gross_weight) }}</td>
                                    <td class="text-end">{{ formatWeight(item.net_weight) }}</td>
                                    <td class="text-end">{{ money(item.estimated_value) }}</td>
                                    <td class="text-end">
                                        <v-chip
                                            v-if="item.item_id"
                                            color="secondary"
                                            size="x-small"
                                            variant="tonal"
                                        >
                                            {{ $t('inventory.itemsTitle') }}
                                        </v-chip>
                                        <span v-else class="text-medium-emphasis">—</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>{{ $t('common.total') }}</th>
                                    <th />
                                    <th class="text-end">{{ formatWeight(pledgeTotals.netWeight) }}</th>
                                    <th />
                                    <th class="text-end">{{ money(pledgeTotals.value) }}</th>
                                    <th />
                                </tr>
                            </tfoot>
                        </v-table>
                    </v-card>

                    <v-card v-if="summary?.periods?.length" class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('pawns.periodBreakdown') }}
                        </v-card-title>
                        <v-table density="compact">
                            <thead>
                                <tr>
                                    <th>{{ $t('pawns.periodFrom') }}</th>
                                    <th>{{ $t('pawns.periodTo') }}</th>
                                    <th class="text-end">{{ $t('pawns.periodDays') }}</th>
                                    <th class="text-end">{{ $t('pawns.periodMonths') }}</th>
                                    <th class="text-end">{{ $t('pawns.outstandingPrincipal') }}</th>
                                    <th class="text-end">{{ $t('pawns.interestAccrued') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(period, index) in summary.periods" :key="index">
                                    <td>{{ period.from }}</td>
                                    <td>{{ period.to }}</td>
                                    <td class="text-end">{{ period.days }}</td>
                                    <td class="text-end">{{ period.months }}</td>
                                    <td class="text-end">{{ money(period.principal) }}</td>
                                    <td class="text-end">{{ money(period.interest) }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>

                    <v-card elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('pawns.ledger') }}
                        </v-card-title>
                        <v-list v-if="pawn.payments.length" class="bg-transparent">
                            <v-list-item
                                v-for="payment in pawn.payments"
                                :key="payment.id"
                                :subtitle="`${payment.date} · ${payment.user?.name ?? ''}`"
                                :title="money(payment.amount)"
                            >
                                <template #prepend>
                                    <v-chip
                                        :color="paymentTypeColor(payment.type)"
                                        size="small"
                                        variant="tonal"
                                    >
                                        {{ paymentTypeTitle(payment.type) }}
                                    </v-chip>
                                </template>
                                <template #append>
                                    <div class="d-flex align-center ga-2">
                                        <v-chip
                                            :color="methodColor(payment.method)"
                                            size="x-small"
                                            variant="tonal"
                                        >
                                            {{ $t(`options.method${payment.method.charAt(0).toUpperCase()}${payment.method.slice(1)}`) }}
                                        </v-chip>
                                        <span
                                            v-if="payment.reference"
                                            class="text-caption text-medium-emphasis"
                                        >
                                            {{ payment.reference }}
                                        </span>
                                        <v-btn
                                            density="compact"
                                            icon="mdi-printer"
                                            size="small"
                                            variant="text"
                                            :title="$t('pawns.printReceipt')"
                                            @click="printReceipt(payment.id, 'a4')"
                                        >
                                            <v-menu activator="parent">
                                                <v-list density="compact">
                                                    <v-list-item
                                                        :title="$t('pawns.printA4')"
                                                        @click="printReceipt(payment.id, 'a4')"
                                                    />
                                                    <v-list-item
                                                        :title="$t('pawns.printThermal')"
                                                        @click="printReceipt(payment.id, 'thermal')"
                                                    />
                                                    <v-divider />
                                                    <v-list-item
                                                        :title="$t('pawns.downloadPdf')"
                                                        @click="printReceipt(payment.id, 'a4', true)"
                                                    />
                                                </v-list>
                                            </v-menu>
                                        </v-btn>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                        <v-card-text v-else class="text-medium-emphasis">
                            {{ $t('pawns.noPayments') }}
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="4">
                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                            <span>{{ $t('pawns.summary') }}</span>
                            <v-chip :color="statusColor" size="small" variant="tonal">
                                {{ statusTitle }}
                            </v-chip>
                        </v-card-title>
                        <v-card-text v-if="summary">
                            <v-list density="compact" class="bg-transparent">
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.principal') }}</v-list-item-title>
                                    <template #append>{{ money(pawn.principal) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.interestRate') }}</v-list-item-title>
                                    <template #append>{{ pawn.interest_rate }}%</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.principalPaid') }}</v-list-item-title>
                                    <template #append>{{ money(summary.principal_paid) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.outstandingPrincipal') }}</v-list-item-title>
                                    <template #append>{{ money(summary.outstanding_principal) }}</template>
                                </v-list-item>
                            </v-list>

                            <v-divider class="my-3" />

                            <v-list density="compact" class="bg-transparent">
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.interestAccrued') }}</v-list-item-title>
                                    <template #append>{{ money(summary.interest_accrued) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.interestPaid') }}</v-list-item-title>
                                    <template #append>{{ money(summary.interest_paid) }}</template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.interestDue') }}</v-list-item-title>
                                    <template #append>
                                        <span :class="{ 'text-error font-weight-medium': Number(summary.interest_due) > 0 }">
                                            {{ money(summary.interest_due) }}
                                        </span>
                                    </template>
                                </v-list-item>
                            </v-list>

                            <v-divider class="my-3" />

                            <div class="d-flex justify-space-between text-subtitle-1 font-weight-bold">
                                <span>{{ $t('pawns.totalPayable') }}</span>
                                <span>{{ money(summary.total_payable) }}</span>
                            </div>

                            <div class="text-caption text-medium-emphasis mt-2">
                                {{ $t('pawns.asOf') }} {{ summary.as_of }} ·
                                {{ $t(`options.partialMonth${summary.partial_month_rule.charAt(0).toUpperCase()}${summary.partial_month_rule.slice(1)}`) }}
                            </div>

                            <v-divider class="my-3" />

                            <v-list density="compact" class="bg-transparent">
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.termStart') }}</v-list-item-title>
                                    <template #append>
                                        {{ pawn.renewed_at ? new Date(pawn.renewed_at).toLocaleDateString() : pawn.date }}
                                    </template>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title>{{ $t('pawns.dueDate') }}</v-list-item-title>
                                    <template #append>{{ pawn.due_date }}</template>
                                </v-list-item>
                                <v-list-item v-if="pawn.renewed_at">
                                    <v-list-item-title>{{ $t('pawns.renew') }}</v-list-item-title>
                                    <template #append>
                                        {{ new Date(pawn.renewed_at).toLocaleDateString() }}
                                    </template>
                                </v-list-item>                            </v-list>

                            <template v-if="pawn.notes">
                                <v-divider class="my-3" />
                                <div class="text-body-2 text-medium-emphasis">{{ pawn.notes }}</div>
                            </template>
                        </v-card-text>

                        <v-card-actions v-if="pawn.payments.length && isOpen">
                            <v-btn
                                prepend-icon="mdi-printer"
                                size="small"
                                variant="text"
                                @click="printLatestReceipt('a4')"
                            >
                                {{ $t('pawns.printReceipt') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </template>
        </v-row>

        <v-dialog v-model="paymentDialog" max-width="520">
            <v-card>
                <v-card-title>{{ $t('pawns.paymentTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        {{ $t('pawns.paymentBody') }}
                    </v-alert>
                    <v-alert
                        v-if="summary"
                        class="mb-4"
                        color="primary"
                        density="comfortable"
                        variant="tonal"
                    >
                        {{ $t('pawns.totalPayable') }}: {{ money(summary.total_payable) }} ·
                        {{ $t('pawns.interestDue') }}: {{ money(summary.interest_due) }}
                    </v-alert>
                    <v-select
                        v-model="paymentForm.method"
                        :items="methodItems"
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
                    <v-btn color="primary" :loading="pawnsStore.saving" @click="submitPayment">
                        {{ $t('pawns.recordPayment') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="redeemDialog" max-width="520">
            <v-card>
                <v-card-title>{{ $t('pawns.redeemTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="success" density="comfortable" variant="tonal">
                        {{ $t('pawns.redeemBody') }}
                    </v-alert>
                    <v-alert
                        v-if="summary"
                        class="mb-4"
                        color="primary"
                        density="comfortable"
                        variant="tonal"
                    >
                        {{ $t('pawns.totalPayable') }}: {{ money(summary.total_payable) }}
                    </v-alert>
                    <v-select
                        v-model="redeemForm.method"
                        :items="methodItems"
                        :label="$t('common.method')"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="redeemForm.date"
                        :label="$t('common.date')"
                        type="date"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="redeemForm.reference"
                        :label="$t('common.reference')"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="redeemForm.note"
                        :label="$t('common.notes')"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="redeemDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="success" :loading="pawnsStore.saving" @click="submitRedeem">
                        {{ $t('pawns.redeem') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="renewDialog" max-width="520">
            <v-card>
                <v-card-title>{{ $t('pawns.renewTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        {{ $t('pawns.renewBody') }}
                    </v-alert>
                    <v-alert
                        v-if="summary"
                        class="mb-4"
                        color="primary"
                        density="comfortable"
                        variant="tonal"
                    >
                        {{ $t('pawns.interestDue') }}: {{ money(summary.interest_due) }}
                    </v-alert>
                    <v-text-field
                        v-model="renewForm.date"
                        :label="$t('common.date')"
                        type="date"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="renewForm.term_days"
                        :label="$t('pawns.renewTermDays')"
                        min="1"
                        step="1"
                        type="number"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="renewForm.note"
                        :label="$t('common.notes')"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="renewDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="info" :loading="pawnsStore.saving" @click="submitRenew">
                        {{ $t('pawns.renew') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="forfeitDialog" max-width="520">
            <v-card>
                <v-card-title>{{ $t('pawns.forfeitTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="error" density="comfortable" variant="tonal">
                        {{ $t('pawns.forfeitBody') }}
                    </v-alert>
                    <v-textarea
                        v-model="forfeitForm.reason"
                        :label="$t('pawns.forfeitReason')"
                        rows="2"
                        variant="outlined"
                    />
                    <v-checkbox
                        v-model="forfeitForm.move_to_inventory"
                        :label="$t('pawns.moveToInventory')"
                    />
                    <v-select
                        v-if="forfeitForm.move_to_inventory"
                        v-model="forfeitForm.item_status"
                        :items="statusItems"
                        :label="$t('pawns.itemStatus')"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="forfeitDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" :loading="pawnsStore.saving" @click="submitForfeit">
                        {{ $t('pawns.forfeit') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
