<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { GOLD_KARATS } from '../../constants/gold-rates'
import { useAuthStore } from '../../stores/auth'
import { useGoldRatesStore } from '../../stores/gold-rates'
import { useSettingsStore } from '../../stores/settings'

const authStore = useAuthStore()
const rateStore = useGoldRatesStore()
const settingsStore = useSettingsStore()
const canManage = computed(() => authStore.can('manage gold rates'))
const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')
const page = ref(1)
const perPage = ref(15)
const search = ref('')
const errorMessage = ref('')
const todayInputs = reactive({ 18: '', 21: '', 22: '', 24: '' })
const todaySaving = reactive({})
const dialog = ref(false)
const dialogSaving = ref(false)
const editingId = ref(null)
const editForm = reactive({
    karat: 22,
    rate_per_gram: '',
    effective_date: todayDateString(),
})
const deleteDialog = ref(false)
const deleteTarget = ref(null)
let searchTimer = null

const headers = [
    { title: 'Karat', key: 'karat', width: 100 },
    { title: 'Rate / gram', key: 'rate_per_gram' },
    { title: 'Effective date', key: 'effective_date' },
    { title: 'Created by', key: 'created_by' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
]

const karatItems = GOLD_KARATS.map((karat) => ({
    title: karat.label,
    value: karat.value,
}))

const currentRateExists = (karat) => rateStore.latest.some(
    (rate) => rate.karat === karat && rate.effective_date === todayDateString(),
)

function todayDateString(date = new Date()) {
    return date.toISOString().slice(0, 10)
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

async function load() {
    errorMessage.value = ''
    rateStore.clearError()

    const results = await Promise.allSettled([
        rateStore.fetchLatest(),
        fetchHistory(),
        settingsStore.fetchSettings(),
    ])

    if (results.some((result) => result.status === 'rejected')) {
        errorMessage.value = rateStore.error ?? settingsStore.error ?? 'Unable to load gold rates.'
    }
}

async function fetchHistory(force = false) {
    errorMessage.value = ''

    try {
        await rateStore.fetchHistory({
            page: page.value,
            per_page: perPage.value,
            search: search.value || undefined,
        }, force)
    } catch {
        errorMessage.value = rateStore.error ?? 'Unable to load gold-rate history.'
    }
}

async function refreshAfterWrite() {
    await fetchHistory(true)

    if (rateStore.error) {
        errorMessage.value = rateStore.error
    }
}

function handleTableOptions(options) {
    if (typeof options.page === 'number') {
        page.value = options.page
    }

    if (typeof options.itemsPerPage === 'number') {
        perPage.value = options.itemsPerPage
    }

    fetchHistory()
}

async function saveToday(karat) {
    errorMessage.value = ''
    const rate = rateStore.latest.find((item) => item.karat === karat)

    if (!todayInputs[karat]) {
        errorMessage.value = `Enter a rate for ${karat}K.`
        return
    }

    todaySaving[karat] = true

    try {
        await rateStore.saveRate({
            karat,
            rate_per_gram: String(todayInputs[karat]),
            effective_date: todayDateString(),
        }, rate?.effective_date === todayDateString() ? rate.id : null)
        await refreshAfterWrite()
    } catch {
        errorMessage.value = rateStore.error ?? 'Unable to save the gold rate.'
    } finally {
        todaySaving[karat] = false
    }
}

function openCreate() {
    editingId.value = null
    Object.assign(editForm, {
        karat: 22,
        rate_per_gram: '',
        effective_date: todayDateString(),
    })
    dialog.value = true
}

function openEdit(rate) {
    editingId.value = rate.id
    Object.assign(editForm, {
        karat: rate.karat,
        rate_per_gram: rate.rate_per_gram,
        effective_date: rate.effective_date,
    })
    dialog.value = true
}

async function saveDialog() {
    errorMessage.value = ''

    try {
        await rateStore.saveRate({
            karat: Number(editForm.karat),
            rate_per_gram: String(editForm.rate_per_gram),
            effective_date: editForm.effective_date,
        }, editingId.value)
        dialog.value = false
        await refreshAfterWrite()
    } catch {
        errorMessage.value = rateStore.error ?? 'Unable to save the gold rate.'
    }
}

function openDelete(rate) {
    deleteTarget.value = rate
    deleteDialog.value = true
}

async function confirmDelete() {
    if (!deleteTarget.value) {
        return
    }

    dialogSaving.value = true
    errorMessage.value = ''

    try {
        await rateStore.removeRate(deleteTarget.value.id)
        deleteDialog.value = false
        deleteTarget.value = null
        await refreshAfterWrite()
    } catch {
        errorMessage.value = rateStore.error ?? 'Unable to delete the gold rate.'
    } finally {
        dialogSaving.value = false
    }
}

watch(() => rateStore.latest, (rates) => {
    GOLD_KARATS.forEach(({ value }) => {
        const rate = rates.find((item) => item.karat === value)

        if (rate && !todayInputs[value]) {
            todayInputs[value] = rate.rate_per_gram
        }
    })
}, { deep: true })

watch(search, () => {
    window.clearTimeout(searchTimer)
    page.value = 1
    searchTimer = window.setTimeout(fetchHistory, 300)
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
                        <v-card-subtitle>Daily pricing</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">Gold Rates</v-card-title>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        Add rate
                    </v-btn>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    variant="tonal"
                    type="error"
                    closable
                    @click:close="errorMessage = ''"
                >
                    {{ errorMessage }}
                </v-alert>
            </v-col>

            <v-col cols="12">
                <v-card elevation="2">
                    <v-card-item>
                        <v-card-title class="text-h6">Today&apos;s rates</v-card-title>
                        <v-card-subtitle>
                            Save one rate per gram for the common karats. Newer effective dates become the latest rate.
                        </v-card-subtitle>
                    </v-card-item>
                    <v-card-text>
                        <v-row>
                            <v-col v-for="karat in GOLD_KARATS" :key="karat.value" cols="12" sm="6" md="3">
                                <v-card class="rate-card h-100" variant="outlined">
                                    <v-card-text>
                                        <div class="d-flex align-center justify-space-between mb-3">
                                            <span class="text-h6 font-weight-bold">{{ karat.label }}</span>
                                            <v-chip v-if="currentRateExists(karat.value)" color="success" size="small" variant="tonal">
                                                Saved
                                            </v-chip>
                                        </div>
                                        <v-text-field
                                            v-model="todayInputs[karat.value]"
                                            :disabled="!canManage"
                                            :prefix="currencySymbol"
                                            hide-details
                                            label="Rate per gram"
                                            min="0.01"
                                            step="0.01"
                                            type="number"
                                        />
                                        <v-btn
                                            v-if="canManage"
                                            class="mt-4"
                                            color="primary"
                                            :loading="todaySaving[karat.value]"
                                            block
                                            @click="saveToday(karat.value)"
                                        >
                                            Save {{ karat.label }}
                                        </v-btn>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12">
                <v-card elevation="2">
                    <v-card-item class="pb-0">
                        <div class="d-flex flex-wrap align-center justify-space-between ga-4">
                            <div>
                                <v-card-title class="text-h6">Rate history</v-card-title>
                                <v-card-subtitle>All saved effective rates, newest first.</v-card-subtitle>
                            </div>
                            <v-text-field
                                v-model="search"
                                class="history-search"
                                clearable
                                density="compact"
                                hide-details
                                label="Search by karat or date"
                                prepend-inner-icon="mdi-magnify"
                                variant="outlined"
                            />
                        </div>
                    </v-card-item>

                    <v-data-table-server
                        v-model:items-per-page="perPage"
                        v-model:page="page"
                        :headers="headers"
                        :items="rateStore.history"
                        :items-length="rateStore.meta.total"
                        :loading="rateStore.loadingHistory"
                        item-value="id"
                        @update:options="handleTableOptions"
                    >
                        <template #item.karat="{ item }">
                            <v-chip color="primary" size="small" variant="tonal">{{ item.karat }}K</v-chip>
                        </template>
                        <template #item.rate_per_gram="{ item }">
                            {{ currencySymbol }}{{ formatMoney(item.rate_per_gram) }}
                        </template>
                        <template #item.effective_date="{ item }">
                            {{ item.effective_date }}
                        </template>
                        <template #item.created_by="{ item }">
                            {{ item.created_by?.name ?? '—' }}
                        </template>
                        <template #item.actions="{ item }">
                            <div v-if="canManage" class="d-flex justify-end ga-1">
                                <v-btn
                                    aria-label="Edit gold rate"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(item)"
                                />
                                <v-btn
                                    aria-label="Delete gold rate"
                                    color="error"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="openDelete(item)"
                                />
                            </div>
                        </template>
                    </v-data-table-server>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="dialog" max-width="520">
            <v-card>
                <v-card-title>{{ editingId ? 'Edit gold rate' : 'Add gold rate' }}</v-card-title>
                <v-card-text>
                    <v-form @submit.prevent="saveDialog">
                        <v-select
                            v-model="editForm.karat"
                            :items="karatItems"
                            label="Karat"
                            required
                        />
                        <v-text-field
                            v-model="editForm.rate_per_gram"
                            label="Rate per gram"
                            min="0.01"
                            :prefix="currencySymbol"
                            required
                            step="0.01"
                            type="number"
                        />
                        <v-text-field
                            v-model="editForm.effective_date"
                            label="Effective date"
                            max="9999-12-31"
                            required
                            type="date"
                        />
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="dialogSaving" @click="saveDialog">Save rate</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="420">
            <v-card>
                <v-card-title>Delete this rate?</v-card-title>
                <v-card-text>
                    This removes the selected historical rate permanently.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
                    <v-btn color="error" :loading="dialogSaving" @click="confirmDelete">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.history-search {
    max-width: 320px;
}

.rate-card {
    border-color: rgb(138 106 50 / 20%);
}
</style>
