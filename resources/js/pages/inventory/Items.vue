<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useInventoryStore } from '../../stores/inventory'
import { ITEM_STATUS_OPTIONS, KARAT_OPTIONS, STOCK_MOVEMENT_OPTIONS } from '../../constants/inventory'

const router = useRouter()
const authStore = useAuthStore()
const inventoryStore = useInventoryStore()
const canManage = computed(() => authStore.can('manage inventory'))
const page = ref(1)
const perPage = ref(15)
const search = ref('')
const categoryId = ref(null)
const karat = ref(null)
const status = ref(null)
const errorMessage = ref('')
const deleteDialog = ref(false)
const deleteTarget = ref(null)
const adjustmentDialog = ref(false)
const adjustmentTarget = ref(null)
const adjustmentForm = reactive({
    type: 'out',
    weight: '',
    status: 'in_stock',
    note: '',
})
let filterTimer = null

const headers = [
    { title: 'Item', key: 'name' },
    { title: 'Category', key: 'category' },
    { title: 'Karat', key: 'karat', width: 90 },
    { title: 'Net weight', key: 'net_weight', align: 'end' },
    { title: 'Status', key: 'status' },
    { title: '', key: 'actions', sortable: false, align: 'end', width: 150 },
]

const categoryItems = computed(() => inventoryStore.categories.map((category) => ({
    title: category.name,
    value: category.id,
})))

const statusColor = (value) => ({
    in_stock: 'success',
    sold: 'primary',
    pawned: 'warning',
    scrap: 'error',
}[value] ?? 'default')

function formatWeight(value) {
    return `${Number(value || 0).toFixed(3)} g`
}

function itemInitials(item) {
    return item.name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
}

async function load() {
    errorMessage.value = ''
    inventoryStore.clearError()

    try {
        await Promise.all([
            inventoryStore.fetchCategories(),
            fetchItems(),
        ])
    } catch {
        errorMessage.value = inventoryStore.error ?? 'Unable to load inventory items.'
    }
}

async function fetchItems(force = false) {
    try {
        await inventoryStore.fetchItems({
            page: page.value,
            per_page: perPage.value,
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
            karat: karat.value || undefined,
            status: status.value || undefined,
        }, force)
    } catch {
        errorMessage.value = inventoryStore.error ?? 'Unable to load inventory items.'
    }
}

function handleTableOptions(options) {
    if (typeof options.page === 'number') {
        page.value = options.page
    }

    if (typeof options.itemsPerPage === 'number') {
        perPage.value = options.itemsPerPage
    }

    fetchItems()
}

function openCreate() {
    router.push({ name: 'item-create' })
}

function openEdit(item) {
    router.push({ name: 'item-edit', params: { id: item.id } })
}

function openDelete(item) {
    deleteTarget.value = item
    deleteDialog.value = true
}

async function confirmDelete() {
    if (!deleteTarget.value) {
        return
    }

    errorMessage.value = ''

    try {
        await inventoryStore.removeItem(deleteTarget.value.id)
        deleteDialog.value = false
        deleteTarget.value = null
        await fetchItems(true)
    } catch {
        errorMessage.value = inventoryStore.error ?? 'Unable to delete the inventory item.'
    }
}

function openAdjustment(item) {
    adjustmentTarget.value = item
    Object.assign(adjustmentForm, {
        type: 'out',
        weight: item.net_weight,
        status: 'sold',
        note: '',
    })
    errorMessage.value = ''
    adjustmentDialog.value = true
}

async function saveAdjustment() {
    if (!adjustmentTarget.value) {
        return
    }

    errorMessage.value = ''

    if (!adjustmentForm.weight || Number(adjustmentForm.weight) <= 0) {
        errorMessage.value = 'Enter a movement weight greater than zero.'
        return
    }

    try {
        await inventoryStore.recordAdjustment(adjustmentTarget.value.id, {
            type: adjustmentForm.type,
            weight: String(adjustmentForm.weight),
            status: adjustmentForm.status,
            note: adjustmentForm.note.trim() || null,
        })
        adjustmentDialog.value = false
        adjustmentTarget.value = null
        await Promise.all([fetchItems(true), inventoryStore.fetchSummary(true)])
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? inventoryStore.error ?? 'Unable to record the adjustment.'
    }
}

function printLabels() {
    router.push({ name: 'item-labels' })
}

watch([search, categoryId, karat, status], () => {
    window.clearTimeout(filterTimer)
    page.value = 1
    filterTimer = window.setTimeout(() => fetchItems(), 300)
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
                        <v-card-subtitle>Gold inventory</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">Items</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            Track catalogue details, weights, images, tags, and stock status.
                        </v-card-text>
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn
                            prepend-icon="mdi-printer-outline"
                            variant="outlined"
                            @click="printLabels"
                        >
                            Print labels
                        </v-btn>
                        <v-btn
                            v-if="canManage"
                            color="primary"
                            prepend-icon="mdi-plus"
                            @click="openCreate"
                        >
                            Add item
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
                                    label="Search name, tag, or barcode"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
                                <v-select
                                    v-model="categoryId"
                                    clearable
                                    density="comfortable"
                                    hide-details
                                    :items="categoryItems"
                                    label="Category"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" sm="3" md="2">
                                <v-select
                                    v-model="karat"
                                    clearable
                                    density="comfortable"
                                    hide-details
                                    :items="KARAT_OPTIONS"
                                    label="Karat"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" sm="3" md="3">
                                <v-select
                                    v-model="status"
                                    clearable
                                    density="comfortable"
                                    hide-details
                                    :items="ITEM_STATUS_OPTIONS"
                                    label="Status"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-divider />

                    <v-progress-linear v-if="inventoryStore.loadingItems" indeterminate />

                    <v-data-table-server
                        v-model:items-per-page="perPage"
                        v-model:page="page"
                        class="d-none d-md-block"
                        :headers="headers"
                        :items="inventoryStore.items"
                        :items-length="inventoryStore.meta.total"
                        :loading="inventoryStore.loadingItems"
                        item-value="id"
                        @update:options="handleTableOptions"
                    >
                        <template #item.name="{ item }">
                            <div class="d-flex align-center ga-3 py-2">
                                <v-avatar :image="item.image_url || undefined" color="primary" size="40">
                                    <span v-if="!item.image_url" class="text-caption font-weight-bold">
                                        {{ itemInitials(item) }}
                                    </span>
                                </v-avatar>
                                <div>
                                    <div class="font-weight-medium">{{ item.name }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ item.tag_no }}</div>
                                </div>
                            </div>
                        </template>
                        <template #item.category="{ item }">
                            {{ item.category.name }}
                        </template>
                        <template #item.karat="{ item }">
                            <v-chip size="small" variant="tonal">{{ item.karat }}K</v-chip>
                        </template>
                        <template #item.net_weight="{ item }">
                            {{ formatWeight(item.net_weight) }}
                        </template>
                        <template #item.status="{ item }">
                            <v-chip :color="statusColor(item.status)" size="small" variant="tonal">
                                {{ item.status.replace('_', ' ') }}
                            </v-chip>
                        </template>
                        <template #item.actions="{ item }">
                            <div class="d-flex justify-end ga-1">
                                <v-btn
                                    v-if="canManage"
                                    aria-label="Edit item"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(item)"
                                />
                                <v-btn
                                    v-if="canManage"
                                    aria-label="Adjust item stock"
                                    icon="mdi-swap-vertical"
                                    size="small"
                                    variant="text"
                                    @click="openAdjustment(item)"
                                />
                                <v-btn
                                    v-if="canManage"
                                    aria-label="Delete item"
                                    color="error"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="openDelete(item)"
                                />
                            </div>
                        </template>
                        <template #no-data>
                            <div class="pa-8 text-center text-medium-emphasis">No inventory items found.</div>
                        </template>
                    </v-data-table-server>

                    <v-row v-if="inventoryStore.items.length" class="d-md-none px-3 pb-3" dense>
                        <v-col v-for="item in inventoryStore.items" :key="item.id" cols="12" sm="6">
                            <v-card class="item-mobile-card h-100" variant="outlined">
                                <v-card-text>
                                    <div class="d-flex align-center ga-3 mb-4">
                                        <v-avatar :image="item.image_url || undefined" color="primary" size="48">
                                            <span v-if="!item.image_url" class="text-caption font-weight-bold">
                                                {{ itemInitials(item) }}
                                            </span>
                                        </v-avatar>
                                        <div class="item-mobile-name">
                                            <div class="font-weight-bold">{{ item.name }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ item.tag_no }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-space-between mb-2">
                                        <span class="text-caption text-medium-emphasis">Category</span>
                                        <span class="text-caption">{{ item.category.name }}</span>
                                    </div>
                                    <div class="d-flex justify-space-between mb-2">
                                        <span class="text-caption text-medium-emphasis">Net weight</span>
                                        <strong>{{ formatWeight(item.net_weight) }}</strong>
                                    </div>
                                    <v-chip :color="statusColor(item.status)" size="small" variant="tonal">
                                        {{ item.status.replace('_', ' ') }}
                                    </v-chip>
                                </v-card-text>
                                <v-card-actions v-if="canManage" class="px-3 pb-3">
                                    <v-btn
                                        aria-label="Edit item"
                                        icon="mdi-pencil-outline"
                                        size="small"
                                        variant="text"
                                        @click="openEdit(item)"
                                    />
                                    <v-btn
                                        aria-label="Adjust item stock"
                                        icon="mdi-swap-vertical"
                                        size="small"
                                        variant="text"
                                        @click="openAdjustment(item)"
                                    />
                                    <v-btn
                                        aria-label="Delete item"
                                        color="error"
                                        icon="mdi-delete-outline"
                                        size="small"
                                        variant="text"
                                        @click="openDelete(item)"
                                    />
                                </v-card-actions>
                            </v-card>
                        </v-col>
                    </v-row>

                    <div v-if="!inventoryStore.loadingItems && !inventoryStore.items.length" class="d-md-none pa-8 text-center text-medium-emphasis">
                        No inventory items found.
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="deleteDialog" max-width="420">
            <v-card>
                <v-card-title>Delete this item?</v-card-title>
                <v-card-text>
                    This removes {{ deleteTarget?.name }} and its stock history. This cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
                    <v-btn color="error" :loading="inventoryStore.saving" @click="confirmDelete">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="adjustmentDialog" max-width="520">
            <v-card>
                <v-card-title>Adjust {{ adjustmentTarget?.name }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="info" density="comfortable" variant="tonal">
                        This records a stock movement and changes the item status through the inventory service.
                    </v-alert>
                    <v-select
                        v-model="adjustmentForm.type"
                        :items="STOCK_MOVEMENT_OPTIONS"
                        label="Movement type"
                        required
                    />
                    <v-text-field
                        v-model="adjustmentForm.weight"
                        label="Movement weight"
                        min="0.001"
                        required
                        step="0.001"
                        suffix="g"
                        type="number"
                    />
                    <v-select
                        v-model="adjustmentForm.status"
                        :items="ITEM_STATUS_OPTIONS"
                        label="New item status"
                        required
                    />
                    <v-textarea
                        v-model="adjustmentForm.note"
                        auto-grow
                        label="Note"
                        rows="2"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="adjustmentDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="inventoryStore.saving" @click="saveAdjustment">Record adjustment</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.item-mobile-card {
    min-width: 0;
}

.item-mobile-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.v-data-table__wrapper) {
    overflow-x: auto;
}
</style>
