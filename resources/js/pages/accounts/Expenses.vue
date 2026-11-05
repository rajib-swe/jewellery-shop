<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useAccountsStore } from '../../stores/accounts'
import { useLocaleStore } from '../../stores/locale'
import { useCurrency, formatAmount } from '../../utils/format'

const authStore = useAuthStore()
const accountsStore = useAccountsStore()
const localeStore = useLocaleStore()
const currencySymbol = useCurrency()
const { expenseCategoryOptions: categoryItems, paymentMethodOptions: methodItems } = useOptionLabels()
const canManage = computed(() => authStore.can('manage accounts'))
const search = ref('')
const categoryFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const dialog = ref(false)
const deleteDialog = ref(false)
const deleteTarget = ref(null)
const errorMessage = ref('')
const today = new Date().toISOString().slice(0, 10)
const form = reactive({
    category: 'other',
    title: '',
    amount: '',
    date: today,
    method: 'cash',
    reference: '',
    note: '',
})
const options = computed(() => ({
    page: accountsStore.expensesMeta.current_page,
    per_page: accountsStore.expensesMeta.per_page,
    search: search.value || undefined,
    category: categoryFilter.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
}))
const headers = computed(() => [
    { title: localeStore.t('common.date'), key: 'date' },
    { title: localeStore.t('accounts.expenseTitle'), key: 'title' },
    { title: localeStore.t('accounts.category'), key: 'category' },
    { title: localeStore.t('common.method'), key: 'method' },
    { title: localeStore.t('common.amount'), key: 'amount', align: 'end' },
    { title: localeStore.t('common.notes'), key: 'note' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
])
const total = computed(() => accountsStore.expensesMeta.total)
const totalAmount = computed(() => accountsStore.expenses
    .reduce((sum, expense) => sum + Number(expense.amount || 0), 0))
const filterItems = computed(() => [
    { title: localeStore.t('accounts.filterAll'), value: '' },
    ...categoryItems.value,
])

function money(value) {
    return formatAmount(value, currencySymbol.value)
}

function categoryTitle(category) {
    return localeStore.t(`options.expense${category.charAt(0).toUpperCase()}${category.slice(1)}`)
}

function methodTitle(method) {
    return localeStore.t(`options.method${method.charAt(0).toUpperCase()}${method.slice(1)}`)
}

async function load() {
    accountsStore.clearError()

    try {
        await accountsStore.fetchExpenses(options.value, true)
    } catch {
        // The store exposes the message, the table simply stays empty.
    }
}

function changePage(page) {
    if (page < 1) {
        return
    }

    options.value.page = page
    load()
}

function openCreate() {
    errorMessage.value = ''

    Object.assign(form, {
        category: 'other',
        title: '',
        amount: '',
        date: today,
        method: 'cash',
        reference: '',
        note: '',
    })

    dialog.value = true
}

function openEdit(expense) {
    errorMessage.value = ''

    Object.assign(form, {
        category: expense.category,
        title: expense.title ?? '',
        amount: expense.amount ?? '',
        date: expense.date,
        method: expense.method,
        reference: expense.reference ?? '',
        note: expense.note ?? '',
    })

    deleteTarget.value = expense
    dialog.value = true
}

async function submit() {
    errorMessage.value = ''

    if (!form.title.trim()) {
        errorMessage.value = localeStore.t('accounts.titleRequired')
        return
    }

    if (!(Number(form.amount) > 0)) {
        errorMessage.value = localeStore.t('accounts.amountRequired')
        return
    }

    const payload = {
        category: form.category,
        title: form.title.trim(),
        amount: Number(form.amount).toFixed(2),
        date: form.date || null,
        method: form.method,
        reference: form.reference.trim() || null,
        note: form.note.trim() || null,
    }

    try {
        await accountsStore.saveExpense(payload, deleteTarget.value?.id ?? null)
        dialog.value = false
        deleteTarget.value = null
        await load()
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? accountsStore.error
            ?? localeStore.t('accounts.saveFailed')
    }
}

function askDelete(expense) {
    errorMessage.value = ''
    deleteTarget.value = expense
    deleteDialog.value = true
}

async function confirmDelete() {
    try {
        await accountsStore.removeExpense(deleteTarget.value.id)
        deleteDialog.value = false
        deleteTarget.value = null
        await load()
    } catch {
        // The error is shown by the alert above the table.
    }
}

let debounce = null

watch([search, categoryFilter, dateFrom, dateTo], () => {
    options.value.page = 1
    clearTimeout(debounce)
    debounce = setTimeout(load, 300)
})

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('accounts.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('accounts.expensesTitle') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('accounts.expensesIntro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        {{ $t('accounts.addExpense') }}
                    </v-btn>
                </div>

                <v-card class="mb-6" color="primary" variant="tonal">
                    <v-card-text>
                        <div class="text-caption">{{ $t('accounts.pageTotal') }}</div>
                        <div class="text-h4 font-weight-bold">{{ money(totalAmount) }}</div>
                    </v-card-text>
                </v-card>

                <v-card elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="4">
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
                                    v-model="categoryFilter"
                                    :items="filterItems"
                                    :label="$t('accounts.category')"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="dateFrom"
                                    :label="$t('common.from')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="dateTo"
                                    :label="$t('common.to')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>
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
                        :items="accountsStore.expenses"
                        :items-length="total"
                        :loading="accountsStore.loadingExpenses"
                        item-value="id"
                        :page="accountsStore.expensesMeta.current_page"
                        :page-size="accountsStore.expensesMeta.per_page"
                        density="comfortable"
                    >
                        <template #item.title="{ item }">
                            <span class="font-weight-medium">{{ item.title }}</span>
                        </template>

                        <template #item.category="{ item }">
                            <v-chip size="small" variant="tonal">
                                {{ categoryTitle(item.category) }}
                            </v-chip>
                        </template>

                        <template #item.method="{ item }">
                            <v-chip size="x-small" variant="tonal">
                                {{ methodTitle(item.method) }}
                            </v-chip>
                        </template>

                        <template #item.amount="{ item }">
                            <span class="font-weight-medium text-error">
                                {{ money(item.amount) }}
                            </span>
                        </template>

                        <template #item.note="{ item }">
                            <span class="text-caption text-medium-emphasis">
                                {{ item.note || '—' }}
                            </span>
                        </template>

                        <template #item.actions="{ item }">
                            <div v-if="canManage" class="d-flex justify-end ga-1">
                                <v-btn
                                    :aria-label="$t('common.edit')"
                                    density="compact"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(item)"
                                />
                                <v-btn
                                    :aria-label="$t('common.delete')"
                                    color="error"
                                    density="compact"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="askDelete(item)"
                                />
                            </div>
                        </template>

                        <template #no-data>
                            <div class="py-6 text-medium-emphasis">
                                {{ $t('accounts.noExpenses') }}
                            </div>
                        </template>
                    </v-data-table-server>

                    <v-divider />

                    <div class="d-flex align-center justify-end pa-3">
                        <v-pagination
                            :length="Math.ceil(total / accountsStore.expensesMeta.per_page) || 1"
                            :model-value="accountsStore.expensesMeta.current_page"
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
                <v-card-title>
                    {{ deleteTarget ? $t('common.edit') : $t('accounts.addExpense') }}
                </v-card-title>
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

                    <v-text-field
                        v-model="form.title"
                        :label="$t('accounts.expenseTitle')"
                        prepend-inner-icon="mdi-text-box-outline"
                        variant="outlined"
                    />
                    <v-select
                        v-model="form.category"
                        :items="categoryItems"
                        :label="$t('accounts.category')"
                        prepend-inner-icon="mdi-shape-outline"
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

                    <v-alert class="mt-4" color="info" density="comfortable" variant="tonal">
                        {{ $t('accounts.expenseCashHint') }}
                    </v-alert>
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

        <v-dialog v-model="deleteDialog" max-width="480">
            <v-card>
                <v-card-title>{{ $t('accounts.deleteTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="warning" density="comfortable" variant="tonal">
                        {{ $t('accounts.deleteBody') }}
                    </v-alert>
                    <div v-if="deleteTarget" class="text-body-1">
                        {{ deleteTarget.title }} · {{ money(deleteTarget.amount) }}
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" :loading="accountsStore.saving" @click="confirmDelete">
                        {{ $t('common.delete') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
