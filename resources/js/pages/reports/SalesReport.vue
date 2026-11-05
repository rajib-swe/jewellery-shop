<script setup>
import { onMounted, ref } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { useReportsStore } from '../../stores/reports'

const reportsStore = useReportsStore()
const reportRef = ref(null)

function load(params) {
    reportsStore.fetchSales(params).catch(() => {
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
                    report="sales"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    show-group-by
                    :subtitle="$t('reports.salesSubtitle')"
                    :title="$t('reports.salesTitle')"
                    @change="load"
                >
                    <template #default="{ formatAmount: money, formatWeight }">
                        <v-card v-if="reportsStore.sales" elevation="2">
                            <v-card-text>
                                <v-row>
                                    <v-col cols="6" md="3">
                                        <v-card color="primary" variant="tonal">
                                            <v-card-text>
                                                <div class="text-caption">
                                                    {{ $t('reports.invoiceCount') }}
                                                </div>
                                                <div class="text-h5 font-weight-bold">
                                                    {{ reportsStore.sales.invoice_count }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="6" md="3">
                                        <v-card color="success" variant="tonal">
                                            <v-card-text>
                                                <div class="text-caption">
                                                    {{ $t('reports.totalSales') }}
                                                </div>
                                                <div class="text-h5 font-weight-bold">
                                                    {{ money(reportsStore.sales.total_sales) }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="6" md="3">
                                        <v-card color="warning" variant="tonal">
                                            <v-card-text>
                                                <div class="text-caption">
                                                    {{ $t('reports.totalPaid') }}
                                                </div>
                                                <div class="text-h5 font-weight-bold">
                                                    {{ money(reportsStore.sales.total_paid) }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="6" md="3">
                                        <v-card color="error" variant="tonal">
                                            <v-card-text>
                                                <div class="text-caption">
                                                    {{ $t('common.due') }}
                                                </div>
                                                <div class="text-h5 font-weight-bold">
                                                    {{ money(reportsStore.sales.total_due) }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                </v-row>
                            </v-card-text>

                            <v-table v-if="reportsStore.sales.rows.length" density="comfortable">
                                <thead>
                                    <tr>
                                        <th>{{ $t('reports.period') }}</th>
                                        <th class="text-end">{{ $t('reports.invoiceCount') }}</th>
                                        <th class="text-end">{{ $t('reports.totalSales') }}</th>
                                        <th class="text-end">{{ $t('common.paid') }}</th>
                                        <th class="text-end">{{ $t('common.due') }}</th>
                                        <th class="text-end">{{ $t('reports.weight') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in reportsStore.sales.rows" :key="row.period">
                                        <td class="text-no-wrap">{{ row.period }}</td>
                                        <td class="text-end">{{ row.invoice_count }}</td>
                                        <td class="text-end">{{ money(row.total) }}</td>
                                        <td class="text-end">{{ money(row.paid) }}</td>
                                        <td class="text-end">{{ money(row.due) }}</td>
                                        <td class="text-end">{{ formatWeight(row.weight) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>{{ $t('common.total') }}</th>
                                        <th class="text-end">{{ reportsStore.sales.invoice_count }}</th>
                                        <th class="text-end">{{ money(reportsStore.sales.total_sales) }}</th>
                                        <th class="text-end">{{ money(reportsStore.sales.total_paid) }}</th>
                                        <th class="text-end">{{ money(reportsStore.sales.total_due) }}</th>
                                        <th class="text-end">
                                            {{ formatWeight(reportsStore.sales.total_weight) }}
                                        </th>
                                    </tr>
                                </tfoot>
                            </v-table>

                            <v-card-text v-else class="text-medium-emphasis">
                                {{ $t('reports.noData') }}
                            </v-card-text>
                        </v-card>
                    </template>
                </ReportPage>
            </v-col>
        </v-row>
    </v-container>
</template>
