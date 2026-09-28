<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { SUPPLIER_TYPES } from '../../constants/suppliers'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { useSuppliersStore } from '../../stores/suppliers'
import { useCurrency } from '../../utils/format'

const router = useRouter()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const suppliersStore = useSuppliersStore()
const currencySymbol = useCurrency()
const canManage = computed(() => authStore.can('manage suppliers'))
const search = ref('')
const typeFilter = ref('')
const deleteDialog = ref(false)
const deleteTarget = ref(null)
const options = computed(() => ({
    page: suppliersStore.meta.current_page,
    per_page: suppliersStore.meta.per_page,
    search: search.value || undefined,
    type: typeFilter.value || undefined,
}))
const headers = computed(() => [
    { title: localeStore.t('common.code'), key: 'code' },
    { title: localeStore.t('common.name'), key: 'name' },
    { title: localeStore.t('suppliers.selectType'), key: 'type' },
    { title: localeStore.t('common.phone'), key: 'phone' },
    { title: localeStore.t('nav.purchases'), key: 'purchases_count', align: 'end' },
    {
        title: localeStore.t('suppliers.balance'),
        key: 'balance',
        align: 'end',
        sortable: false,
    },
    { title: '', key: 'actions', sortable: false, align: 'end' },
])
const typeItems = computed(() => SUPPLIER_TYPES.map((value) => ({
    value,
    title: localeStore.t(`options.${value}`),
})))
const total = computed(() => suppliersStore.meta.total)

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function typeColor(type) {
    return type === 'karigor' ? 'primary' : 'secondary'
}

async function load() {
    suppliersStore.clearError()

    try {
        await suppliersStore.fetchList(options.value, true)
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

function openSupplier(supplierId) {
    router.push({ name: 'supplier-profile', params: { id: supplierId } })
}

function askDelete(supplier) {
    suppliersStore.clearError()
    deleteTarget.value = supplier
    deleteDialog.value = true
}

async function confirmDelete() {
    try {
        await suppliersStore.removeSupplier(deleteTarget.value.id)
        deleteDialog.value = false
        deleteTarget.value = null
        await load()
    } catch {
        // The error is shown by the alert above the table.
    }
}

let debounce = null

watch([search, typeFilter], () => {
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
                        <v-card-subtitle>{{ $t('suppliers.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('suppliers.listTitle') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('suppliers.listIntro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-plus"
                        :to="{ name: 'supplier-create' }"
                    >
                        {{ $t('suppliers.addSupplier') }}
                    </v-btn>
                </div>

                <v-card elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="7">
                                <v-text-field
                                    v-model="search"
                                    clearable
                                    density="comfortable"
                                    :label="$t('common.search')"
                                    :placeholder="$t('suppliers.searchPlaceholder')"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" md="5">
                                <v-btn-toggle
                                    v-model="typeFilter"
                                    color="primary"
                                    density="comfortable"
                                    mandatory
                                    variant="outlined"
                                >
                                    <v-btn value="">{{ $t('suppliers.filterAll') }}</v-btn>
                                    <v-btn
                                        v-for="item in typeItems"
                                        :key="item.value"
                                        :value="item.value"
                                    >
                                        {{ item.title }}
                                    </v-btn>
                                </v-btn-toggle>
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-divider />

                    <v-alert
                        v-if="suppliersStore.error"
                        class="ma-4"
                        closable
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                        @click:close="suppliersStore.clearError()"
                    >
                        {{ suppliersStore.error }}
                    </v-alert>

                    <v-data-table-server
                        :headers="headers"
                        :items="suppliersStore.items"
                        :items-length="total"
                        :loading="suppliersStore.loading"
                        item-value="id"
                        :page="suppliersStore.meta.current_page"
                        :page-size="suppliersStore.meta.per_page"
                        density="comfortable"
                    >
                        <template #item.name="{ item }">
                            <button class="table-link" type="button" @click="openSupplier(item.id)">
                                {{ item.name }}
                            </button>
                            <div v-if="item.address" class="text-caption text-medium-emphasis">
                                {{ item.address }}
                            </div>
                        </template>

                        <template #item.type="{ item }">
                            <v-chip :color="typeColor(item.type)" size="small" variant="tonal">
                                {{ $t(`options.${item.type}`) }}
                            </v-chip>
                        </template>

                        <template #item.phone="{ item }">
                            {{ item.phone || '—' }}
                        </template>

                        <template #item.purchases_count="{ item }">
                            {{ item.purchases_count }}
                        </template>

                        <template #item.balance="{ item }">
                            <span
                                class="font-weight-medium"
                                :class="{ 'text-error': Number(item.balance) > 0 }"
                            >
                                {{ money(item.balance) }}
                            </span>
                        </template>

                        <template #item.actions="{ item }">
                            <div class="d-flex justify-end ga-1">
                                <v-btn
                                    :aria-label="$t('common.edit')"
                                    density="compact"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    :to="{ name: 'supplier-edit', params: { id: item.id } }"
                                />
                                <v-btn
                                    v-if="canManage"
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
                                {{ $t('suppliers.noSuppliers') }}
                            </div>
                        </template>
                    </v-data-table-server>

                    <v-divider />

                    <div class="d-flex align-center justify-end pa-3">
                        <v-pagination
                            :length="Math.ceil(total / suppliersStore.meta.per_page) || 1"
                            :model-value="suppliersStore.meta.current_page"
                            density="comfortable"
                            rounded
                            :total-visible="5"
                            @update:model-value="changePage"
                        />
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="deleteDialog" max-width="480">
            <v-card>
                <v-card-title>{{ $t('suppliers.deleteTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="warning" density="comfortable" variant="tonal">
                        {{ $t('suppliers.deleteBody') }}
                    </v-alert>
                    <div v-if="deleteTarget" class="text-body-1">
                        {{ deleteTarget.name }} · {{ deleteTarget.code }}
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" :loading="suppliersStore.saving" @click="confirmDelete">
                        {{ $t('common.delete') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
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
