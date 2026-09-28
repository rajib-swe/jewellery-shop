<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useOptionLabels } from '../../constants/options'
import { CASH_SOURCE_TYPES } from '../../constants/accounts'
import { useAccountsStore } from '../../stores/accounts'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { useCurrency, formatAmount } from '../../utils/format'

const authStore = useAuthStore()
const accountsStore = useAccountsStore()
const localeStore = useLocaleStore()
const currencySymbol = useCurrency()
const { paymentMethodOptions: methodItems, cashDirectionOptions: directionItems } = useOptionLabels()
const canManage = computed(() => authStore.can('manage accounts'))
const search = ref('')
const sourceFilter = ref('')
const directionFilter = ref('')
const methodFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const dialog = ref(false)
const errorMessage = ref('')
const today = new Date().toISOString().slice(0, 10)
const form = reactive({
    direction: 'out',
    amount: '',
    date: today,
    method: 'cash',
    reference: '',
    note: '',
})
const summary = computed(() => accountsStore.summary)
const options = computed(() => ({
    page: accountsStore.transactionsMeta.current_page,
    per_page: accountsStore.transactionsMeta.per_page,
    search: search.value || undefined,
    source_type: sourceFilter.value || undefined,
    direction: directionFilter.value || undefined,
    method: methodFilter.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
}))
const headers = computed(() => [
    { title: localeStore.t('common.date'), key: 'date' },
    { title: localeStore.t('accounts.source'), key: 'source_type' },
    { title: localeStore.t('accounts.description'), key: 'note' },
    { title: localeStore.t('common.method'), key: 'method' },
    { title: localeStore.t('accounts.in'), key: 'in', align: 'end' },
    { title: localeStore.t('accounts.out'), key: 'out', align: 'end' },
])
const total = computed(() => accountsStore.transactionsMeta.total)
const sourceItems = computed(() => [
    { title: localeStore.t('accounts.filterAll'), value: '' },
    ...CASH_SOURCE_TYPES.map((value) => ({
        title: localeStore.t(`options.cashSource${value.charAt(0).toUpperCase()}${value.slice(1)}`),
        value,
    })),
])
const methodFilterItems = computed(() => [
    { title: localeStore.t('accounts.filterAll'), value: '' },
    ...methodItems.value,
])

function money(value) {
    return formatAmount(value, currencySymbol.value)
}

function methodTitle(method) {
    return localeStore.t(`options.method${method.charAt(0).toUpperCase()}${method.slice(1)}`)
}

function sourceTitle(source) {
    return localeStore.t(`options.cashSource${source.charAt(0).toUpperCase()}${source.slice(1)}`)
}

function sourceColor(source) {
    return ({
        sale_payment: 'success',
        pawn_payment: 'primary',
        pawn_disbursement: 'warning',
        supplier_payment: 'error',
        expense: 'error',
        cash_adjustment: 'secondary',
    })[source] ?? 'default'
}

async function load() {
    accountsStore.clearError()

    try {
        await accountsStore.fetchTransactions(options.value, true)
    } catch {
        // The store exposes the message, the table simply stays empty.
    }
}

async function loadSummary() {
    try {
        await accountsStore.fetchSummary(dateFrom.value || today, true)
    } catch {
        // The banner is skipped; the ledger below still loads.
    }
}

function changePage(page) {
    if (page < 1) {
        return
    }

    options.value.page = page
    load()
}

function openDialog() {
    errorMessage.value = ''

    Object.assign(form, {
        direction: 'out',
        amount: '',
        date: today,
        method: 'cash',
        reference: '',
        note: '',
    })

    dialog.value = true
}

async function submit() {
    errorMessage.value = ''

    if (!(Number(form.amount) > 0)) {
        errorMessage.value = localeStore.t('accounts.amountRequired')
        return
    }

    try {
        await accountsStore.recordCashEntry({
            direction: form.direction,
            amount: Number(form.amount).toFixed(2),
            date: form.date || null,
            method: form.method,
            reference: form.reference.trim() || null,
            note: form.note.trim() || null,
        })
        dialog.value = false
        await Promise.all([load(), loadSummary()])
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? accountsStore.error
            ?? localeStore.t('accounts.saveFailed')
    }
}

let debounce = null

watch([search, sourceFilter, directionFilter, methodFilter, dateTo], () => {
    options.value.page = 1
    clearTimeout(debounce)
    debounce = setTimeout(load, 300)
})

watch(dateFrom, loadSummary)

onMounted(() => {
    load()
    loadSummary()
})
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('accounts.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('accounts.cashBookTitle') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('accounts.cashBookIntro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-cash-plus"
                        @click="openDialog"
                    >
                        {{ $t('accounts.addCashEntry') }}
                    </v-btn>
                </div>

                <v-row v-if="summary" class="mb-2">
                    <v-col cols="6" md="3">
                        <v-card color="primary" variant="tonal">
                            <v-card-text>
                                <v-icon icon="mdi-wallet-outline" size="26" />
                                <div class="text-caption mt-2">{{ $t('accounts.opening') }}</div>
                                <div class="text-h6 font-weight-bold">{{ money(summary.opening_balance) }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-card color="success" variant="tonal">
                            <v-card-text>
                                <v-icon icon="mdi-arrow-bottom-left" size="26" />
                                <div class="text-caption mt-2">{{ $t('accounts.in') }}</div>
                                <div class="text-h6 font-weight-bold">{{ money(summary.total_in) }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-card color="error" variant="tonal">
                            <v-card-text>
                                <v-icon icon="mdi-arrow-top-right" size="26" />
                                <div class="text-caption mt-2">{{ $t('accounts.out') }}</div>
                                <div class="text-h6 font-weight-bold">{{ money(summary.total_out) }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-card :color="summary.locked ? 'warning' : 'secondary'" variant="tonal">
                            <v-card-text>
                                <v-icon icon="mdi-cash-multiple" size="26" />
                                <div class="text-caption mt-2">{{ $t('accounts.closing') }}</div>
                                <div class="text-h6 font-weight-bold">{{ money(summary.closing_balance) }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-card elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="search"
                                    clearable
                                    density="comfortable"
                                    :label="$t('common.search')"
                                    :placeholder="$t('accounts.searchPlaceholder')"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
                                <v-select
                                    v-model="sourceFilter"
                                    :items="sourceItems"
                                    :label="$t('accounts.source')"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="2">
                                <v-select
                                    v-model="directionFilter"
                                    :items="directionItems"
                                    :label="$t('accounts.direction')"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="2">
                                <v-select
                                    v-model="methodFilter"
                                    :items="methodFilterItems"
                                    :label="$t('common.method')"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="1">
                                <v-text-field
                                    v-model="dateFrom"
                                    :label="$t('common.from')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="1">
                                <v-text-field
                                    v-model="dateTo"
                                    :label="$t('common.to')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>

                        <v-alert v-if="summary?.locked" class="mt-2" color="warning" density="comfortable" variant="tonal">
                            {{ $t('accounts.dayLocked') }}
                        </v-alert>
                    </v-card-text>

                    <v-divider />

                    <v-alert
                        v-if="accountsStore.error"
                        class="ma-4"
                        closable
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                        @click:close="accountsStore.clearError()"
                    >
                        {{ accountsStore.error }}
                    </v-alert>

                    <v-data-table-server
                        :headers="headers"
                        :items="accountsStore.transactions"
                        :items-length="total"
                        :loading="accountsStore.loadingTransactions"
                        item-value="id"
                        :page="accountsStore.transactionsMeta.current_page"
                        :page-size="accountsStore.transactionsMeta.per_page"
                        density="comfortable"
                    >
                        <template #item.source_type="{ item }">
                            <v-chip :color="sourceColor(item.source_type)" size="small" variant="tonal">
                                {{ sourceTitle(item.source_type) }}
                            </v-chip>
                        </template>

                        <template #item.note="{ item }">
                            <div>{{ item.note || '—' }}</div>
                            <div v-if="item.reference" class="text-caption text-medium-emphasis">
                                {{ item.reference }}
                            </div>
                        </template>

                        <template #item.method="{ item }">
                            <v-chip size="x-small" variant="tonal">
                                {{ methodTitle(item.method) }}
                            </v-chip>
                        </template>

                        <template #item.in="{ item }">
                            <span v-if="item.direction === 'in'" class="font-weight-medium text-success">
                                {{ money(item.amount) }}
                            </span>
                            <span v-else class="text-medium-emphasis">—</span>
                        </template>

                        <template #item.out="{ item }">
                            <span v-if="item.direction === 'out'" class="font-weight-medium text-error">
                                {{ money(item.amount) }}
                            </span>
                            <span v-else class="text-medium-emphasis">—</span>
                        </template>

                        <template #no-data>
                            <div class="py-6 text-medium-emphasis">
                                {{ $t('accounts.noTransactions') }}
                            </div>
                        </template>
                    </v-data-table-server>

                    <v-divider />

                    <div class="d-flex align-center justify-end pa-3">
                        <v-pagination
                            :length="Math.ceil(total / accountsStore.transactionsMeta.per_page) || 1"
                            :model-value="accountsStore.transactionsMeta.current_page"
                            density="comfortable"
                            rounded
                            :total-visible="5"
                            @update:model-value="changePage"
                        />
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="dialog" max-width="560">
            <v-card>
                <v-card-title>{{ $t('accounts.addCashEntry') }}</v-card-title>
                <v-card-text>
                    <v-alert
                        v-if="errorMessage"
                        class="mb-4"
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                    >
                        {{ errorMessage }}
                    </v-alert>

                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        {{ $t('accounts.cashEntryHint') }}
                    </v-alert>

                    <v-select
                        v-model="form.direction"
                        :items="directionItems"
                        :label="$t('accounts.direction')"
                        prepend-inner-icon="mdi-swap-vertical"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="form.amount"
                        :label="$t('common.amount')"
                        min="0.01"
                        step="0.01"
                        :suffix="currencySymbol"
                        type="number"
                        variant="outlined"
                    />
                    <v-select
                        v-model="form.method"
                        :items="methodItems"
                        :label="$t('common.method')"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="form.date"
                        :label="$t('common.date')"
                        type="date"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="form.reference"
                        :label="$t('common.reference')"
                        variant="outlined"
                    />
                    <v-textarea
                        v-model="form.note"
                        :label="$t('common.notes')"
                        rows="2"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="accountsStore.saving" @click="submit">
                        {{ $t('common.save') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
