<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { usePurchasesStore } from '../../stores/purchases'
import { useSuppliersStore } from '../../stores/suppliers'
import { useCurrency, useWeightFormatter } from '../../utils/format'

const router = useRouter()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const purchasesStore = usePurchasesStore()
const suppliersStore = useSuppliersStore()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()
const canManage = computed(() => authStore.can('manage purchases'))
const search = ref('')
const supplierFilter = ref(null)
const dateFrom = ref('')
const dateTo = ref('')
const options = computed(() => ({
    page: purchasesStore.meta.current_page,
    per_page: purchasesStore.meta.per_page,
    search: search.value || undefined,
    supplier_id: supplierFilter.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
}))
const headers = computed(() => [
    { title: localeStore.t('purchases.purchaseNo'), key: 'purchase_no' },
    { title: localeStore.t('common.date'), key: 'date' },
    { title: localeStore.t('purchases.selectSupplier'), key: 'supplier' },
    { title: localeStore.t('common.subtotal'), key: 'subtotal', align: 'end' },
    { title: localeStore.t('common.discount'), key: 'discount', align: 'end' },
    { title: localeStore.t('common.total'), key: 'total', align: 'end' },
    { title: localeStore.t('common.paid'), key: 'paid', align: 'end' },
    { title: localeStore.t('common.due'), key: 'due', align: 'end' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
])
const supplierItems = computed(() => suppliersStore.items.map((supplier) => ({
    title: `${supplier.name} · ${supplier.code}`,
    value: supplier.id,
})))
const total = computed(() => purchasesStore.meta.total)

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

async function load() {
    purchasesStore.clearError()

    try {
        await purchasesStore.fetchPurchases(options.value, true)
    } catch {
        // The store exposes the message, the table simply stays empty.
    }
}

async function loadSuppliers() {
    try {
        await suppliersStore.fetchList({ per_page: 100 }, true)
    } catch {
        // The supplier filter is optional.
    }
}

function changePage(page) {
    if (page < 1) {
        return
    }

    options.value.page = page
    load()
}

function openPurchase(purchaseId) {
    router.push({ name: 'purchase-profile', params: { id: purchaseId } })
}

let debounce = null

watch([search, supplierFilter, dateFrom, dateTo], () => {
    options.value.page = 1
    clearTimeout(debounce)
    debounce = setTimeout(load, 300)
})

onMounted(() => {
    loadSuppliers()
    load()
})
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('purchases.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('purchases.listTitle') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('purchases.listIntro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-plus"
                        :to="{ name: 'purchase-create' }"
                    >
                        {{ $t('nav.newPurchase') }}
                    </v-btn>
                </div>

                <v-card elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="search"
                                    clearable
                                    density="comfortable"
                                    :label="$t('common.search')"
                                    :placeholder="$t('purchases.searchPlaceholder')"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="supplierFilter"
                                    clearable
                                    density="comfortable"
                                    :items="supplierItems"
                                    :label="$t('purchases.selectSupplier')"
                                    prepend-inner-icon="mdi-store-outline"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="dateFrom"
                                    density="comfortable"
                                    hide-details
                                    :label="$t('common.from')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="dateTo"
                                    density="comfortable"
                                    hide-details
                                    :label="$t('common.to')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-divider />

                    <v-alert
                        v-if="purchasesStore.error"
                        class="ma-4"
                        closable
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                        @click:close="purchasesStore.clearError()"
                    >
                        {{ purchasesStore.error }}
                    </v-alert>

                    <v-data-table-server
                        :headers="headers"
                        :items="purchasesStore.purchases"
                        :items-length="total"
                        :loading="purchasesStore.loadingPurchases"
                        item-value="id"
                        :page="purchasesStore.meta.current_page"
                        :page-size="purchasesStore.meta.per_page"
                        density="comfortable"
                    >
                        <template #item.purchase_no="{ item }">
                            <button class="table-link" type="button" @click="openPurchase(item.id)">
                                {{ item.purchase_no }}
                            </button>
                        </template>

                        <template #item.date="{ item }">
                            {{ item.date }}
                        </template>

                        <template #item.supplier="{ item }">
                            <router-link
                                v-if="item.supplier"
                                :to="{ name: 'supplier-profile', params: { id: item.supplier.id } }"
                            >
                                {{ item.supplier.name }}
                            </router-link>
                            <span v-else class="text-medium-emphasis">—</span>
                            <div v-if="item.supplier" class="text-caption text-medium-emphasis">
                                {{ $t(`options.${item.supplier.type}`) }}
                            </div>
                        </template>

                        <template #item.subtotal="{ item }">
                            {{ money(item.subtotal) }}
                        </template>

                        <template #item.discount="{ item }">
                            <span v-if="Number(item.discount) > 0">- {{ money(item.discount) }}</span>
                            <span v-else class="text-medium-emphasis">—</span>
                        </template>

                        <template #item.total="{ item }">
                            <span class="font-weight-medium">{{ money(item.total) }}</span>
                        </template>

                        <template #item.paid="{ item }">
                            {{ money(item.paid) }}
                        </template>

                        <template #item.due="{ item }">
                            <span
                                :class="{ 'text-error font-weight-medium': Number(item.due) > 0 }"
                            >
                                {{ money(item.due) }}
                            </span>
                        </template>

                        <template #item.actions="{ item }">
                            <v-btn
                                density="compact"
                                icon="mdi-chevron-right"
                                size="small"
                                variant="text"
                                @click="openPurchase(item.id)"
                            />
                        </template>

                        <template #no-data>
                            <div class="py-6 text-medium-emphasis">
                                {{ $t('purchases.noPurchases') }}
                            </div>
                        </template>
                    </v-data-table-server>

                    <v-divider />

                    <div class="d-flex align-center justify-end pa-3">
                        <v-pagination
                            :length="Math.ceil(total / purchasesStore.meta.per_page) || 1"
                            :model-value="purchasesStore.meta.current_page"
                            density="comfortable"
                            rounded
                            :total-visible="5"
                            @update:model-value="changePage"
                        />
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.table-link {
    color: rgb(var(--v-theme-primary));
    cursor: pointer;
    font-weight: 500;
    padding: 0;
    text-align: left;
}
</style>
