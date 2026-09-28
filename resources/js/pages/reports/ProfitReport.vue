<script setup>
import { computed, onMounted, ref } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { useReportsStore } from '../../stores/reports'

const reportsStore = useReportsStore()
const reportRef = ref(null)
const report = computed(() => reportsStore.profit)

function load(params) {
    reportsStore.fetchProfit(params).catch(() => {
        // The alert above the table shows the message.
    })
}

onMounted(() => load(reportRef.value.query()))
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <ReportPage
                    ref="reportRef"
                    report="profit"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    :subtitle="$t('reports.profitSubtitle')"
                    :title="$t('reports.profitTitle')"
                    @change="load"
                >
                    <template #default="{ formatAmount: money }">
                        <v-row v-if="report">
                            <v-col cols="12" md="6">
                                <v-card class="h-100" elevation="2">
                                    <v-card-title class="text-subtitle-1 font-weight-medium">
                                        {{ $t('reports.income') }}
                                    </v-card-title>
                                    <v-list density="comfortable" class="bg-transparent">
                                        <v-list-item>
                                            <v-list-item-title>
                                                {{ $t('reports.totalSales') }}
                                            </v-list-item-title>
                                            <template #append>
                                                {{ money(report.sales_total) }}
                                            </template>
                                        </v-list-item>
                                        <v-list-item>
                                            <v-list-item-title>
                                                {{ $t('reports.totalPaid') }}
                                            </v-list-item-title>
                                            <template #append>
                                                {{ money(report.sales_paid) }}
                                            </template>
                                        </v-list-item>
                                        <v-list-item>
                                            <v-list-item-title>
                                                {{ $t('reports.cashIn') }}
                                            </v-list-item-title>
                                            <template #append>
                                                {{ money(report.cash_in) }}
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-card class="h-100" elevation="2">
                                    <v-card-title class="text-subtitle-1 font-weight-medium">
                                        {{ $t('reports.expenses') }}
                                    </v-card-title>
                                    <v-list density="comfortable" class="bg-transparent">
                                        <v-list-item>
                                            <v-list-item-title>
                                                {{ $t('reports.purchaseCost') }}
                                            </v-list-item-title>
                                            <template #append>
                                                {{ money(report.purchase_cost) }}
                                            </template>
                                        </v-list-item>
                                        <v-list-item>
                                            <v-list-item-title>
                                                {{ $t('reports.otherExpenses') }}
                                            </v-list-item-title>
                                            <template #append>
                                                {{ money(report.expenses) }}
                                            </template>
                                        </v-list-item>
                                        <v-list-item>
                                            <v-list-item-title>
                                                {{ $t('reports.cashOut') }}
                                            </v-list-item-title>
                                            <template #append>
                                                {{ money(report.cash_out) }}
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-card>
                            </v-col>

                            <v-col cols="12">
                                <v-card
                                    :color="Number(report.gross_profit) >= 0 ? 'success' : 'error'"
                                    variant="tonal"
                                >
                                    <v-card-text class="d-flex flex-wrap align-center justify-space-between ga-4">
                                        <div>
                                            <div class="text-caption">
                                                {{ $t('reports.grossProfit') }}
                                            </div>
                                            <div class="text-h4 font-weight-bold">
                                                {{ money(report.gross_profit) }}
                                            </div>
                                        </div>
                                        <v-chip color="primary" size="large" variant="flat">
                                            {{ $t('reports.margin') }}: {{ report.margin_percentage }}%
                                        </v-chip>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>

                        <v-alert v-if="report" class="mt-4" color="info" density="comfortable" variant="tonal">
                            {{ $t('reports.profitHint') }}
                        </v-alert>
                    </template>
                </ReportPage>
            </v-col>
        </v-row>
    </v-container>
</template>
