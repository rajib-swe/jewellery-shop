<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useCustomersStore } from '../../stores/customers'
import { useSettingsStore } from '../../stores/settings'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const customerStore = useCustomersStore()
const settingsStore = useSettingsStore()
const customerId = computed(() => route.params.id)
const activeTab = ref('overview')
const errorMessage = ref('')
const canManage = computed(() => authStore.can('manage customers'))
const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')

const tabs = [
    { value: 'overview', label: 'Overview', icon: 'mdi-account-outline' },
    { value: 'sales', label: 'Sales', icon: 'mdi-receipt-text-outline' },
    { value: 'pawns', label: 'Pawns', icon: 'mdi-handshake-outline' },
    { value: 'payments', label: 'Payments', icon: 'mdi-cash-multiple' },
]

const tabItems = computed(() => ({
    sales: customerStore.history.sales,
    pawns: customerStore.history.pawns,
    payments: customerStore.history.payments,
}))

const tabEmptyText = {
    sales: 'Sales will appear here when the sale module is connected.',
    pawns: 'Pawns will appear here when the pawn module is connected.',
    payments: 'Payments will appear here when the accounts module is connected.',
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
    }).format(new Date(value))
}

function initials(customer) {
    return customer.name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
}

function tabCount(tab) {
    return tabItems.value[tab]?.length ?? 0
}

async function load() {
    errorMessage.value = ''
    customerStore.clearError()
    customerStore.clearCurrent()

    try {
        await Promise.all([
            customerStore.fetchCustomer(customerId.value, true),
            customerStore.fetchHistory(customerId.value, true),
            settingsStore.fetchSettings(),
        ])
    } catch {
        errorMessage.value = customerStore.error ?? settingsStore.error ?? 'Unable to load the customer.'
    }
}

function goBack() {
    router.push({ name: 'customers' })
}

function editCustomer() {
    router.push({ name: 'customer-edit', params: { id: customerId.value } })
}

watch(customerId, load)
onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row justify="center">
            <v-col cols="12" lg="10">
                <div class="d-flex align-center ga-3 mb-6">
                    <v-btn
                        aria-label="Back to customers"
                        icon="mdi-arrow-left"
                        variant="text"
                        @click="goBack"
                    />
                    <div>
                        <v-card-subtitle>Customer profile</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold pa-0">Customer details</v-card-title>
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

                <v-skeleton-loader v-if="customerStore.loadingCurrent" type="card" />

                <template v-else-if="customerStore.current">
                    <v-card class="mb-6" elevation="2">
                        <v-card-text>
                            <div class="d-flex flex-wrap align-center ga-4">
                                <v-avatar
                                    :image="customerStore.current.photo_url || undefined"
                                    color="primary"
                                    size="88"
                                >
                                    <span v-if="!customerStore.current.photo_url" class="text-h5 font-weight-bold">
                                        {{ initials(customerStore.current) }}
                                    </span>
                                </v-avatar>
                                <div class="customer-profile-heading">
                                    <div class="text-h5 font-weight-bold">{{ customerStore.current.name }}</div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        {{ customerStore.current.code }}
                                    </div>
                                    <a :href="`tel:${customerStore.current.phone}`" class="text-decoration-none">
                                        {{ customerStore.current.phone }}
                                    </a>
                                </div>
                                <v-spacer />
                                <v-btn
                                    v-if="canManage"
                                    color="primary"
                                    prepend-icon="mdi-pencil-outline"
                                    :to="{ name: 'customer-edit', params: { id: customerId } }"
                                >
                                    Edit
                                </v-btn>
                            </div>
                        </v-card-text>
                    </v-card>

                    <v-card elevation="2">
                        <v-tabs
                            v-model="activeTab"
                            class="px-2"
                            color="primary"
                            density="comfortable"
                            show-arrows
                        >
                            <v-tab
                                v-for="tab in tabs"
                                :key="tab.value"
                                :prepend-icon="tab.icon"
                                :value="tab.value"
                            >
                                {{ tab.label }}
                                <v-chip
                                    v-if="tab.value !== 'overview'"
                                    class="ml-2"
                                    size="x-small"
                                    variant="tonal"
                                >
                                    {{ tabCount(tab.value) }}
                                </v-chip>
                            </v-tab>
                        </v-tabs>

                        <v-divider />

                        <v-window v-model="activeTab" class="pa-4 pa-md-6">
                            <v-window-item value="overview">
                                <v-row>
                                    <v-col cols="12" sm="6" md="4">
                                        <v-card class="h-100" color="primary" variant="tonal">
                                            <v-card-text>
                                                <v-icon icon="mdi-cash-multiple" size="28" />
                                                <div class="text-caption mt-3">Due balance</div>
                                                <div class="text-h5 font-weight-bold">
                                                    {{ currencySymbol }}{{ formatMoney(customerStore.history.due_balance) }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="12" sm="6" md="4">
                                        <v-card class="h-100" variant="outlined">
                                            <v-card-text>
                                                <v-icon icon="mdi-card-account-details-outline" size="28" />
                                                <div class="text-caption mt-3">NID</div>
                                                <div class="text-subtitle-1 font-weight-medium">
                                                    {{ customerStore.current.nid || 'Not provided' }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="12" sm="6" md="4">
                                        <v-card class="h-100" variant="outlined">
                                            <v-card-text>
                                                <v-icon icon="mdi-calendar-plus-outline" size="28" />
                                                <div class="text-caption mt-3">Added</div>
                                                <div class="text-subtitle-1 font-weight-medium">
                                                    {{ formatDate(customerStore.current.created_at) }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <div class="text-subtitle-1 font-weight-bold mb-2">Address</div>
                                        <div class="text-body-2 text-medium-emphasis">
                                            {{ customerStore.current.address || 'Not provided' }}
                                        </div>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <div class="text-subtitle-1 font-weight-bold mb-2">Notes</div>
                                        <div class="text-body-2 text-medium-emphasis">
                                            {{ customerStore.current.notes || 'No notes added.' }}
                                        </div>
                                    </v-col>
                                </v-row>
                            </v-window-item>

                            <v-window-item v-for="tab in tabs.slice(1)" :key="tab.value" :value="tab.value">
                                <v-empty-state
                                    :icon="tab.icon"
                                    :title="`No ${tab.label.toLowerCase()} yet`"
                                    :text="tabEmptyText[tab.value]"
                                />
                            </v-window-item>
                        </v-window>
                    </v-card>
                </template>

                <v-empty-state
                    v-else-if="!customerStore.loadingCurrent"
                    icon="mdi-account-off-outline"
                    title="Customer not found"
                    text="The customer may have been removed."
                >
                    <template #actions>
                        <v-btn color="primary" @click="goBack">Back to customers</v-btn>
                    </template>
                </v-empty-state>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.customer-profile-heading {
    min-width: 0;
}
</style>
