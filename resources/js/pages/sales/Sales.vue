<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSalesStore } from '../../stores/sales'
import { useSettingsStore } from '../../stores/settings'
import { SALE_STATUS_OPTIONS } from '../../constants/sales'

const router = useRouter()
const authStore = useAuthStore()
const salesStore = useSalesStore()
const settingsStore = useSettingsStore()
const canManage = computed(() => authStore.can('manage sales'))
const page = ref(1)
const perPage = ref(15)
const search = ref('')
const status = ref(null)
const dateFrom = ref(null)
const dateTo = ref(null)
const errorMessage = ref('')
let filterTimer = null

const headers = [
    { title: 'Invoice', key: 'invoice_no' },
    { title: 'Date', key: 'date' },
    { title: 'Customer', key: 'customer' },
    { title: 'Total', key: 'total', align: 'end' },
    { title: 'Paid', key: 'paid', align: 'end' },
    { title: 'Due', key: 'due', align: 'end' },
    { title: 'Status', key: 'status' },
]

const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')

function money(value) {
    return `${currencySymbol.value}${Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function statusColor(value) {
    return { completed: 'success', void: 'error' }[value] ?? 'default'
}

async function load() {
    errorMessage.value = ''
    salesStore.clearError()

    try {
        await Promise.all([
            settingsStore.fetchSettings(),
            fetchSales(),
        ])
    } catch {
        errorMessage.value = salesStore.error ?? 'Unable to load sales.'
    }
}

async function fetchSales(force = false) {
    try {
        await salesStore.fetchSales({
            page: page.value,
            per_page: perPage.value,
            search: search.value || undefined,
            status: status.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        }, force)
    } catch {
        errorMessage.value = salesStore.error ?? 'Unable to load sales.'
    }
}

function handleTableOptions(options) {
    if (typeof options.page === 'number') {
        page.value = options.page
    }

    if (typeof options.itemsPerPage === 'number') {
        perPage.value = options.itemsPerPage
    }

    fetchSales()
}

function openCreate() {
    router.push({ name: 'sale-create' })
}

function openSale(sale) {
    router.push({ name: 'sale-profile', params: { id: sale.id } })
}

watch([search, status, dateFrom, dateTo], () => {
    window.clearTimeout(filterTimer)
    page.value = 1
    filterTimer = window.setTimeout(() => fetchSales(), 300)
})

onMounted(load)
onBeforeUnmount(() => window.clearTimeout(filterTimer))
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>Sales</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">Invoices</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            Every recorded sale, its payment state, and the customer due.
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        New sale
                    </v-btn>
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
            </v-col>

            <v-col cols="12">
                <v-card elevation="2">
                    <v-card-text>
                        <v-row dense>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="search"
                                    clearable
                                    density="comfortable"
                                    hide-details
                                    label="Search invoice, customer, or phone"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-select
                                    v-model="status"
                                    clearable
                                    density="comfortable"
                                    hide-details
                                    :items="SALE_STATUS_OPTIONS"
                                    label="Status"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-text-field
                                    v-model="dateFrom"
                                    density="comfortable"
                                    hide-details
                                    label="From"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-text-field
                                    v-model="dateTo"
                                    density="comfortable"
                                    hide-details
                                    label="To"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-divider />

                    <v-progress-linear v-if="salesStore.loadingSales" indeterminate />

                    <v-data-table-server
                        v-model:items-per-page="perPage"
                        v-model:page="page"
                        :headers="headers"
                        :items="salesStore.sales"
                        :items-length="salesStore.meta.total"
                        :loading="salesStore.loadingSales"
                        item-value="id"
                        @click:row="(_event, { item }) => openSale(item)"
                        @update:options="handleTableOptions"
                    >
                        <template #item.invoice_no="{ item }">
                            <div class="font-weight-medium py-2">{{ item.invoice_no }}</div>
                        </template>
                        <template #item.date="{ item }">
                            {{ item.date }}
                        </template>
                        <template #item.customer="{ item }">
                            <div v-if="item.customer">
                                <div>{{ item.customer.name }}</div>
                                <div class="text-caption text-medium-emphasis">{{ item.customer.phone }}</div>
                            </div>
                            <span v-else class="text-medium-emphasis">Walk-in</span>
                        </template>
                        <template #item.total="{ item }">
                            {{ money(item.total) }}
                        </template>
                        <template #item.paid="{ item }">
                            {{ money(item.paid) }}
                        </template>
                        <template #item.due="{ item }">
                            <span :class="{ 'text-error font-weight-medium': Number(item.due) > 0 }">
                                {{ money(item.due) }}
                            </span>
                        </template>
                        <template #item.status="{ item }">
                            <v-chip :color="statusColor(item.status)" size="small" variant="tonal">
                                {{ item.status }}
                            </v-chip>
                        </template>
                        <template #no-data>
                            <div class="pa-8 text-center text-medium-emphasis">No sales found.</div>
                        </template>
                    </v-data-table-server>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
