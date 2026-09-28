<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import SalesTrendChart from '../components/SalesTrendChart.vue'
import GoldPriceTable from '../components/GoldPriceTable.vue'
import { useAuthStore } from '../stores/auth'
import { useGoldRatesStore } from '../stores/gold-rates'
import { useLocaleStore } from '../stores/locale'
import { useReportsStore } from '../stores/reports'
import { useCurrency, formatAmount, useWeightFormatter } from '../utils/format'

const router = useRouter()
const authStore = useAuthStore()
const goldRateStore = useGoldRatesStore()
const localeStore = useLocaleStore()
const reportsStore = useReportsStore()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()

const dashboard = computed(() => reportsStore.dashboard)
const greetingName = computed(() => authStore.user?.name ?? '')
const latestRate = computed(() => goldRateStore.latest.find((rate) => rate.karat === 22)
    ?? goldRateStore.latest[0]
    ?? null)

function money(value) {
    return formatAmount(value, currencySymbol.value)
}

async function load() {
    await Promise.allSettled([
        reportsStore.fetchDashboard(),
        goldRateStore.fetchLatest(),
    ])
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="mb-6">
                    <v-card-subtitle>{{ $t('dashboard.overview') }}</v-card-subtitle>
                    <v-card-title class="text-h4 font-weight-bold">
                        {{ $t('dashboard.welcome', { name: greetingName }) }}
                    </v-card-title>
                </div>

                <v-alert
                    v-if="reportsStore.error"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                >
                    {{ reportsStore.error }}
                </v-alert>

                <v-progress-linear v-if="reportsStore.loadingDashboard" class="mb-2" indeterminate />

                <template v-if="dashboard">
                    <v-row>
                        <v-col cols="12" sm="6" md="4" lg="3">
                            <v-card class="h-100" color="primary" variant="tonal">
                                <v-card-text>
                                    <v-icon icon="mdi-receipt-text-outline" size="28" />
                                    <div class="text-caption mt-2">
                                        {{ $t('reports.todaySales') }}
                                    </div>
                                    <div class="text-h5 font-weight-bold">
                                        {{ money(dashboard.today.sales_total) }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('reports.invoiceCount') }}:
                                        {{ dashboard.today.invoice_count }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" sm="6" md="4" lg="3">
                            <v-card class="h-100" color="success" variant="tonal">
                                <v-card-text>
                                    <v-icon icon="mdi-cash-multiple" size="28" />
                                    <div class="text-caption mt-2">
                                        {{ $t('accounts.closing') }}
                                    </div>
                                    <div class="text-h5 font-weight-bold">
                                        {{ money(dashboard.today.cash_balance) }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        +{{ money(dashboard.today.cash_in) }} /
                                        −{{ money(dashboard.today.cash_out) }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" sm="6" md="4" lg="3">
                            <v-card class="h-100" color="secondary" variant="tonal">
                                <v-card-text>
                                    <v-icon icon="mdi-handshake-outline" size="28" />
                                    <div class="text-caption mt-2">
                                        {{ $t('reports.activePawns') }}
                                    </div>
                                    <div class="text-h5 font-weight-bold">
                                        {{ dashboard.pawns.active_count }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('reports.outstandingPrincipal') }}:
                                        {{ money(dashboard.pawns.outstanding_principal) }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" sm="6" md="4" lg="3">
                            <v-card
                                class="h-100"
                                :color="dashboard.pawns.overdue_count > 0 ? 'error' : 'success'"
                                variant="tonal"
                            >
                                <v-card-text>
                                    <v-icon icon="mdi-calendar-alert" size="28" />
                                    <div class="text-caption mt-2">
                                        {{ $t('reports.overduePawns') }}
                                    </div>
                                    <div class="text-h5 font-weight-bold">
                                        {{ dashboard.pawns.overdue_count }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('reports.interestDue') }}:
                                        {{ money(dashboard.pawns.interest_due) }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" sm="6" md="4" lg="3">
                            <v-card class="h-100" color="info" variant="tonal">
                                <v-card-text>
                                    <v-icon icon="mdi-scale-balance" size="28" />
                                    <div class="text-caption mt-2">
                                        {{ $t('inventory.totalNetWeight') }}
                                    </div>
                                    <div class="text-h5 font-weight-bold">
                                        {{ formatWeight(dashboard.stock.total_weight) }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('inventory.totalItems') }}:
                                        {{ dashboard.stock.total_items }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" sm="6" md="4" lg="3">
                            <v-card class="h-100" color="warning" variant="tonal">
                                <v-card-text>
                                    <v-icon icon="mdi-swap-horizontal" size="28" />
                                    <div class="text-caption mt-2">
                                        {{ $t('reports.netPosition') }}
                                    </div>
                                    <div class="text-h5 font-weight-bold">
                                        {{ money(dashboard.receivables) }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('reports.payable') }}: {{ money(dashboard.payables) }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>

                    <v-row class="mt-2">
                        <v-col cols="12" lg="8">
                            <v-card class="h-100" elevation="2">
                                <v-card-title class="text-subtitle-1 font-weight-medium">
                                    {{ $t('reports.salesTrend') }}
                                </v-card-title>
                                <v-card-text>
                                    <SalesTrendChart :rows="dashboard.sales_trend" />
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" lg="4">
                            <v-card class="h-100" elevation="2">
                                <v-card-title class="text-subtitle-1 font-weight-medium">
                                    {{ $t('dashboard.liveGoldPrice') }}
                                </v-card-title>
                                <v-card-text>
                                    <div
                                        v-if="latestRate"
                                        class="d-flex align-center ga-3 mb-4"
                                    >
                                        <v-chip color="secondary" variant="tonal">
                                            {{ latestRate.karat }}K ·
                                            {{ money(latestRate.rate_per_gram) }}/{{ $t('units.gram') }}
                                        </v-chip>
                                    </div>
                                    <GoldPriceTable />
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>

                    <v-row class="mt-2">
                        <v-col cols="12">
                            <v-card elevation="2">
                                <v-card-title class="text-subtitle-1 font-weight-medium">
                                    {{ $t('reports.quickLinks') }}
                                </v-card-title>
                                <v-card-text>
                                    <div class="d-flex flex-wrap ga-2">
                                        <v-btn
                                            prepend-icon="mdi-chart-line-variant"
                                            variant="outlined"
                                            :to="{ name: 'report-sales' }"
                                        >
                                            {{ $t('reports.salesTitle') }}
                                        </v-btn>
                                        <v-btn
                                            prepend-icon="mdi-scale-balance"
                                            variant="outlined"
                                            :to="{ name: 'report-stock' }"
                                        >
                                            {{ $t('reports.stockTitle') }}
                                        </v-btn>
                                        <v-btn
                                            prepend-icon="mdi-handshake-outline"
                                            variant="outlined"
                                            :to="{ name: 'report-pawns' }"
                                        >
                                            {{ $t('reports.pawnTitle') }}
                                        </v-btn>
                                        <v-btn
                                            prepend-icon="mdi-calendar-alert"
                                            variant="outlined"
                                            :to="{ name: 'report-overdue-pawns' }"
                                        >
                                            {{ $t('reports.overdueTitle') }}
                                        </v-btn>
                                        <v-btn
                                            prepend-icon="mdi-book-open-page-variant-outline"
                                            variant="outlined"
                                            :to="{ name: 'cash-book' }"
                                        >
                                            {{ $t('accounts.cashBookTitle') }}
                                        </v-btn>
                                        <v-btn
                                            prepend-icon="mdi-lock-check-outline"
                                            variant="outlined"
                                            :to="{ name: 'daily-closing' }"
                                        >
                                            {{ $t('accounts.closingTitle') }}
                                        </v-btn>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </template>
            </v-col>
        </v-row>
    </v-container>
</template>
