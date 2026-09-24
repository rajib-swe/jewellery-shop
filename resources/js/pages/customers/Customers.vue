<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useCustomersStore } from '../../stores/customers'
import { useSettingsStore } from '../../stores/settings'

const router = useRouter()
const authStore = useAuthStore()
const customerStore = useCustomersStore()
const settingsStore = useSettingsStore()
const canManage = computed(() => authStore.can('manage customers'))
const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')
const page = ref(1)
const perPage = ref(15)
const search = ref('')
const errorMessage = ref('')
const deleteDialog = ref(false)
const deleteTarget = ref(null)
let searchTimer = null

const headers = [
    { title: 'Customer', key: 'name' },
    { title: 'Phone', key: 'phone' },
    { title: 'Code', key: 'code' },
    { title: 'Due balance', key: 'opening_balance', align: 'end' },
    { title: '', key: 'actions', sortable: false, align: 'end', width: 112 },
]

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

async function load() {
    errorMessage.value = ''
    customerStore.clearError()

    try {
        await Promise.all([
            customerStore.fetchList({
                page: page.value,
                per_page: perPage.value,
                search: search.value || undefined,
            }),
            settingsStore.fetchSettings(),
        ])
    } catch {
        errorMessage.value = customerStore.error ?? settingsStore.error ?? 'Unable to load customers.'
    }
}

async function fetchCustomers(force = false) {
    errorMessage.value = ''

    try {
        await customerStore.fetchList({
            page: page.value,
            per_page: perPage.value,
            search: search.value || undefined,
        }, force)
    } catch {
        errorMessage.value = customerStore.error ?? 'Unable to load customers.'
    }
}

function handleTableOptions(options) {
    if (typeof options.page === 'number') {
        page.value = options.page
    }

    if (typeof options.itemsPerPage === 'number') {
        perPage.value = options.itemsPerPage
    }

    fetchCustomers()
}

function openCreate() {
    router.push({ name: 'customer-create' })
}

function openProfile(customer) {
    router.push({ name: 'customer-profile', params: { id: customer.id } })
}

function openEdit(customer) {
    router.push({ name: 'customer-edit', params: { id: customer.id } })
}

function openDelete(customer) {
    deleteTarget.value = customer
    deleteDialog.value = true
}

async function confirmDelete() {
    if (!deleteTarget.value) {
        return
    }

    errorMessage.value = ''

    try {
        await customerStore.removeCustomer(deleteTarget.value.id)
        deleteDialog.value = false
        deleteTarget.value = null
        await fetchCustomers(true)
    } catch {
        errorMessage.value = customerStore.error ?? 'Unable to delete the customer.'
    }
}

function customerInitials(customer) {
    return customer.name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
}

watch(search, () => {
    window.clearTimeout(searchTimer)
    page.value = 1
    searchTimer = window.setTimeout(() => fetchCustomers(), 300)
})

onMounted(load)
onBeforeUnmount(() => window.clearTimeout(searchTimer))
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>Customer directory</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">Customers</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            Keep customer details, balances, and activity in one place.
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-account-plus-outline"
                        size="large"
                        @click="openCreate"
                    >
                        Add customer
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

                <v-card class="customer-table-card" elevation="2">
                    <v-card-item class="pb-0">
                        <div class="d-flex flex-wrap align-center justify-space-between ga-4">
                            <div>
                                <v-card-title class="text-h6">Customer records</v-card-title>
                                <v-card-subtitle>{{ customerStore.meta.total }} total</v-card-subtitle>
                            </div>
                            <v-text-field
                                v-model="search"
                                class="customer-search"
                                clearable
                                density="comfortable"
                                hide-details
                                label="Search name, phone, NID, or code"
                                prepend-inner-icon="mdi-magnify"
                                variant="outlined"
                            />
                        </div>
                    </v-card-item>

                    <v-progress-linear v-if="customerStore.loading" indeterminate />

                    <v-data-table-server
                        v-model:items-per-page="perPage"
                        v-model:page="page"
                        class="d-none d-md-block"
                        :headers="headers"
                        :items="customerStore.items"
                        :items-length="customerStore.meta.total"
                        :loading="customerStore.loading"
                        item-value="id"
                        @update:options="handleTableOptions"
                    >
                        <template #item.name="{ item }">
                            <div class="d-flex align-center ga-3 py-2">
                                <v-avatar
                                    :image="item.photo_url || undefined"
                                    color="primary"
                                    size="40"
                                >
                                    <span v-if="!item.photo_url" class="text-caption font-weight-bold">
                                        {{ customerInitials(item) }}
                                    </span>
                                </v-avatar>
                                <div>
                                    <div class="font-weight-medium">{{ item.name }}</div>
                                    <div v-if="item.address" class="text-caption text-medium-emphasis">
                                        {{ item.address }}
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template #item.phone="{ item }">
                            <a :href="`tel:${item.phone}`" class="text-decoration-none">
                                {{ item.phone }}
                            </a>
                        </template>
                        <template #item.code="{ item }">
                            <v-chip size="small" variant="tonal">{{ item.code }}</v-chip>
                        </template>
                        <template #item.opening_balance="{ item }">
                            {{ currencySymbol }}{{ formatMoney(item.opening_balance) }}
                        </template>
                        <template #item.actions="{ item }">
                            <div class="d-flex justify-end ga-1">
                                <v-btn
                                    aria-label="View customer"
                                    icon="mdi-eye-outline"
                                    size="small"
                                    variant="text"
                                    @click="openProfile(item)"
                                />
                                <v-btn
                                    v-if="canManage"
                                    aria-label="Edit customer"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(item)"
                                />
                                <v-btn
                                    v-if="canManage"
                                    aria-label="Delete customer"
                                    color="error"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="openDelete(item)"
                                />
                            </div>
                        </template>
                        <template #no-data>
                            <div class="pa-8 text-center text-medium-emphasis">
                                No customers found.
                            </div>
                        </template>
                    </v-data-table-server>

                    <v-row v-if="customerStore.items.length" class="d-md-none px-3 pb-3" dense>
                        <v-col v-for="customer in customerStore.items" :key="customer.id" cols="12" sm="6">
                            <v-card class="customer-mobile-card h-100" variant="outlined" @click="openProfile(customer)">
                                <v-card-text>
                                    <div class="d-flex align-center ga-3 mb-4">
                                        <v-avatar
                                            :image="customer.photo_url || undefined"
                                            color="primary"
                                            size="48"
                                        >
                                            <span v-if="!customer.photo_url" class="text-caption font-weight-bold">
                                                {{ customerInitials(customer) }}
                                            </span>
                                        </v-avatar>
                                        <div class="customer-mobile-name">
                                            <div class="font-weight-bold">{{ customer.name }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ customer.phone }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-space-between align-center mb-2">
                                        <span class="text-caption text-medium-emphasis">Customer code</span>
                                        <span class="text-caption">{{ customer.code }}</span>
                                    </div>
                                    <div class="d-flex justify-space-between align-center">
                                        <span class="text-caption text-medium-emphasis">Due balance</span>
                                        <strong>{{ currencySymbol }}{{ formatMoney(customer.opening_balance) }}</strong>
                                    </div>
                                </v-card-text>
                                <v-card-actions v-if="canManage" class="px-3 pb-3">
                                    <v-btn
                                        aria-label="Edit customer"
                                        icon="mdi-pencil-outline"
                                        size="small"
                                        variant="text"
                                        @click.stop="openEdit(customer)"
                                    />
                                    <v-btn
                                        aria-label="Delete customer"
                                        color="error"
                                        icon="mdi-delete-outline"
                                        size="small"
                                        variant="text"
                                        @click.stop="openDelete(customer)"
                                    />
                                </v-card-actions>
                            </v-card>
                        </v-col>
                    </v-row>

                    <div v-if="!customerStore.loading && !customerStore.items.length" class="d-md-none pa-8 text-center text-medium-emphasis">
                        No customers found.
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="deleteDialog" max-width="420">
            <v-card>
                <v-card-title>Delete this customer?</v-card-title>
                <v-card-text>
                    This permanently removes {{ deleteTarget?.name }} and their profile. This cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
                    <v-btn
                        color="error"
                        :loading="customerStore.saving"
                        @click="confirmDelete"
                    >
                        Delete
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.customer-search {
    width: min(100%, 360px);
}

.customer-table-card {
    overflow: hidden;
}

.customer-mobile-card {
    cursor: pointer;
}

.customer-mobile-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.v-data-table__wrapper) {
    overflow-x: auto;
}
</style>
