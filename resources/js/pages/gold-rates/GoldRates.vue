<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { GOLD_KARAT_VALUES } from '../../constants/gold-rates'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useGoldRatesStore } from '../../stores/gold-rates'
import { useLocaleStore } from '../../stores/locale'
import { useSettingsStore } from '../../stores/settings'

const authStore = useAuthStore()
const rateStore = useGoldRatesStore()
const localeStore = useLocaleStore()
const settingsStore = useSettingsStore()
const { goldKaratOptions: karatItems } = useOptionLabels()
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

const headers = computed(() => [
    { title: localeStore.t('goldRates.karat'), key: 'karat', width: 100 },
    { title: localeStore.t('goldRates.ratePerGramColumn'), key: 'rate_per_gram' },
    { title: localeStore.t('goldRates.effectiveDate'), key: 'effective_date' },
    { title: localeStore.t('goldRates.createdBy'), key: 'created_by' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
])

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
        errorMessage.value = rateStore.error ?? settingsStore.error ?? localeStore.t('goldRates.loadFailed')
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
        errorMessage.value = rateStore.error ?? localeStore.t('goldRates.loadFailed')
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
        errorMessage.value = localeStore.t('goldRates.rateRequired', { karat: `${karat}K` })
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
        errorMessage.value = rateStore.error ?? localeStore.t('goldRates.saveFailed')
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
        errorMessage.value = rateStore.error ?? localeStore.t('goldRates.saveFailed')
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
        errorMessage.value = rateStore.error ?? localeStore.t('goldRates.deleteFailed')
    } finally {
        dialogSaving.value = false
    }
}

watch(() => rateStore.latest, (rates) => {
    GOLD_KARAT_VALUES.forEach((value) => {
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
                        <v-card-subtitle>{{ $t('goldRates.subtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">{{ $t('goldRates.title') }}</v-card-title>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        {{ $t('goldRates.addRate') }}
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
                        <v-card-title class="text-h6">{{ $t('goldRates.todaysRates') }}</v-card-title>
                        <v-card-subtitle>
                            {{ $t('goldRates.todaysRatesBody') }}
                        </v-card-subtitle>
                    </v-card-item>
                    <v-card-text>
                        <v-row>
                            <v-col v-for="karat in GOLD_KARAT_VALUES" :key="karat" cols="12" sm="6" md="3">
                                <v-card class="rate-card h-100" variant="outlined">
                                    <v-card-text>
                                        <div class="d-flex align-center justify-space-between mb-3">
                                            <span class="text-h6 font-weight-bold">{{ karat }}K</span>
                                            <v-chip v-if="currentRateExists(karat)" color="success" size="small" variant="tonal">
                                                {{ $t('common.saved') }}
                                            </v-chip>
                                        </div>
                                        <v-text-field
                                            v-model="todayInputs[karat]"
                                            :disabled="!canManage"
                                            :prefix="currencySymbol"
                                            hide-details
                                            :label="$t('goldRates.ratePerGram')"
                                            min="0.01"
                                            step="0.01"
                                            type="number"
                                        />
                                        <v-btn
                                            v-if="canManage"
                                            class="mt-4"
                                            color="primary"
                                            :loading="todaySaving[karat]"
                                            block
                                            @click="saveToday(karat)"
                                        >
                                            {{ $t('goldRates.saveKarat', { karat: `${karat}K` }) }}
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
                                <v-card-title class="text-h6">{{ $t('goldRates.history') }}</v-card-title>
                                <v-card-subtitle>{{ $t('goldRates.historyBody') }}</v-card-subtitle>
                            </div>
                            <v-text-field
                                v-model="search"
                                class="history-search"
                                clearable
                                density="compact"
                                hide-details
                                :label="$t('goldRates.searchPlaceholder')"
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
                                    :aria-label="$t('goldRates.editRate')"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(item)"
                                />
                                <v-btn
                                    :aria-label="$t('common.delete')"
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
                <v-card-title>
                    {{ editingId ? $t('goldRates.editRate') : $t('goldRates.addRateTitle') }}
                </v-card-title>
                <v-card-text>
                    <v-form @submit.prevent="saveDialog">
                        <v-select
                            v-model="editForm.karat"
                            :items="karatItems"
                            :label="$t('goldRates.karat')"
                            required
                        />
                        <v-text-field
                            v-model="editForm.rate_per_gram"
                            :label="$t('goldRates.ratePerGram')"
                            min="0.01"
                            :prefix="currencySymbol"
                            required
                            step="0.01"
                            type="number"
                        />
                        <v-text-field
                            v-model="editForm.effective_date"
                            :label="$t('goldRates.effectiveDate')"
                            max="9999-12-31"
                            required
                            type="date"
                        />
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="dialogSaving" @click="saveDialog">
                        {{ $t('goldRates.saveRate') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="420">
            <v-card>
                <v-card-title>{{ $t('goldRates.deleteTitle') }}</v-card-title>
                <v-card-text>
                    {{ $t('goldRates.deleteBody') }}
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" :loading="dialogSaving" @click="confirmDelete">
                        {{ $t('common.delete') }}
                    </v-btn>
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
