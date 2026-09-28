<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { PAWN_LIST_FILTERS } from '../../constants/pawns'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { usePawnsStore } from '../../stores/pawns'
import { useCurrency } from '../../utils/format'

const router = useRouter()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const pawnsStore = usePawnsStore()
const currencySymbol = useCurrency()
const canManage = computed(() => authStore.can('manage pawns'))
const search = ref('')
const filter = ref('all')
const options = computed(() => ({
    page: pawnsStore.meta.current_page,
    per_page: pawnsStore.meta.per_page,
    search: search.value || undefined,
    status: filter.value === 'all' || filter.value === 'overdue' ? undefined : filter.value,
    overdue: filter.value === 'overdue' ? 1 : undefined,
}))
const headers = computed(() => [
    { title: localeStore.t('pawns.pawnNo'), key: 'pawn_no' },
    { title: localeStore.t('common.name'), key: 'customer' },
    { title: localeStore.t('pawns.pawnDate'), key: 'date' },
    { title: localeStore.t('pawns.dueDate'), key: 'due_date' },
    { title: localeStore.t('pawns.principal'), key: 'principal', align: 'end' },
    { title: localeStore.t('pawns.interestRate'), key: 'interest_rate', align: 'end' },
    { title: localeStore.t('pawns.outstandingPrincipal'), key: 'outstanding', align: 'end' },
    { title: localeStore.t('pawns.interestDue'), key: 'interest_due', align: 'end' },
    { title: localeStore.t('pawns.totalPayable'), key: 'total_payable', align: 'end' },
    { title: localeStore.t('common.status'), key: 'status' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
])
const filterItems = computed(() => PAWN_LIST_FILTERS.map((value) => ({
    value,
    title: localeStore.t(`pawns.filter${value.charAt(0).toUpperCase()}${value.slice(1)}`),
})))
const filterLabel = (value) => localeStore.t(`pawns.filter${value.charAt(0).toUpperCase()}${value.slice(1)}`)
const total = computed(() => pawnsStore.meta.total)

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function statusColor(pawn) {
    if (pawn.status === 'redeemed') {
        return 'success'
    }

    if (pawn.status === 'forfeited') {
        return 'error'
    }

    return pawn.is_overdue ? 'warning' : 'info'
}

function statusTitle(pawn) {
    if (pawn.status !== 'active') {
        return localeStore.t(`options.${pawn.status}`)
    }

    return pawn.is_overdue
        ? localeStore.t('options.overdue')
        : localeStore.t('options.active')
}

async function load() {
    pawnsStore.clearError()

    try {
        await pawnsStore.fetchPawns(options.value, true)
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

function openPawn(pawnId) {
    router.push({ name: 'pawn-profile', params: { id: pawnId } })
}

let debounce = null

watch([search, filter], () => {
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
                        <v-card-subtitle>{{ $t('pawns.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('pawns.listTitle') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('pawns.listIntro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-plus"
                        :to="{ name: 'pawn-create' }"
                    >
                        {{ $t('pawns.addPawn') }}
                    </v-btn>
                </div>

                <v-card elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="search"
                                    clearable
                                    density="comfortable"
                                    :label="$t('common.search')"
                                    :placeholder="$t('pawns.searchPlaceholder')"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-btn-toggle
                                    v-model="filter"
                                    color="primary"
                                    density="comfortable"
                                    mandatory
                                    variant="outlined"
                                >
                                    <v-btn
                                        v-for="item in filterItems"
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
                        v-if="pawnsStore.error"
                        class="ma-4"
                        closable
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                        @click:close="pawnsStore.clearError()"
                    >
                        {{ pawnsStore.error }}
                    </v-alert>

                    <v-data-table-server
                        :headers="headers"
                        :items="pawnsStore.pawns"
                        :items-length="total"
                        :loading="pawnsStore.loadingPawns"
                        item-value="id"
                        :page="pawnsStore.meta.current_page"
                        :page-size="pawnsStore.meta.per_page"
                        density="comfortable"
                    >
                        <template #item.pawn_no="{ item }">
                            <button class="table-link" type="button" @click="openPawn(item.id)">
                                {{ item.pawn_no }}
                            </button>
                            <div
                                v-if="item.items?.length"
                                class="text-caption text-medium-emphasis"
                            >
                                {{ item.items.length }} × {{ item.items[0].description }}
                            </div>
                        </template>

                        <template #item.customer="{ item }">
                            <div v-if="item.customer">
                                <div class="font-weight-medium">{{ item.customer.name }}</div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ item.customer.phone }}
                                </div>
                            </div>
                            <span v-else class="text-medium-emphasis">—</span>
                        </template>

                        <template #item.due_date="{ item }">
                            {{ item.due_date }}
                            <v-chip
                                v-if="item.is_overdue"
                                class="ms-2"
                                color="warning"
                                size="x-small"
                                variant="tonal"
                            >
                                {{ $t('options.overdue') }}
                            </v-chip>
                        </template>

                        <template #item.principal="{ item }">
                            {{ money(item.principal) }}
                        </template>

                        <template #item.interest_rate="{ item }">
                            {{ item.interest_rate }}%
                        </template>

                        <template #item.outstanding="{ item }">
                            {{ money(item.summary.outstanding_principal) }}
                        </template>

                        <template #item.interest_due="{ item }">
                            {{ money(item.summary.interest_due) }}
                        </template>

                        <template #item.total_payable="{ item }">
                            <span class="font-weight-medium">
                                {{ money(item.summary.total_payable) }}
                            </span>
                        </template>

                        <template #item.status="{ item }">
                            <v-chip :color="statusColor(item)" size="small" variant="tonal">
                                {{ statusTitle(item) }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <v-btn
                                density="compact"
                                icon="mdi-chevron-right"
                                size="small"
                                variant="text"
                                @click="openPawn(item.id)"
                            />
                        </template>

                        <template #no-data>
                            <div class="py-6 text-medium-emphasis">
                                {{ $t('pawns.noPawns') }}
                            </div>
                        </template>
                    </v-data-table-server>

                    <v-divider />

                    <div class="d-flex align-center justify-end pa-3">
                        <v-pagination
                            :length="Math.ceil(total / pawnsStore.meta.per_page) || 1"
                            :model-value="pawnsStore.meta.current_page"
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
