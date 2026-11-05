<script setup>
import { onMounted } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { useReportsStore } from '../../stores/reports'

const reportsStore = useReportsStore()

function load() {
    reportsStore.fetchStock().catch(() => {
        // The alert above the table shows the message.
    })
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <ReportPage
                    report="stock"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    :show-dates="false"
                    :subtitle="$t('reports.stockSubtitle')"
                    :title="$t('reports.stockTitle')"
                >
                    <template #default="{ formatAmount: money, formatWeight }">
                        <v-row v-if="reportsStore.stock">
                            <v-col cols="12" sm="4">
                                <v-card color="primary" variant="tonal">
                                    <v-card-text>
                                        <v-icon icon="mdi-package-variant-closed" size="26" />
                                        <div class="text-caption mt-2">
                                            {{ $t('inventory.totalItems') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ reportsStore.stock.total_items }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-card color="secondary" variant="tonal">
                                    <v-card-text>
                                        <v-icon icon="mdi-scale-balance" size="26" />
                                        <div class="text-caption mt-2">
                                            {{ $t('inventory.totalNetWeight') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ formatWeight(reportsStore.stock.total_weight) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-card color="success" variant="tonal">
                                    <v-card-text>
                                        <v-icon icon="mdi-cash-multiple" size="26" />
                                        <div class="text-caption mt-2">
                                            {{ $t('reports.stockValue') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.stock.total_value) }}
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ $t('reports.stockValueHint') }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>

                        <v-row v-if="reportsStore.stock" class="mt-2">
                            <v-col cols="12" md="6">
                                <v-card class="h-100" elevation="2">
                                    <v-card-title class="text-subtitle-1 font-weight-medium">
                                        {{ $t('reports.byKarat') }}
                                    </v-card-title>
                                    <v-table density="comfortable">
                                        <thead>
                                            <tr>
                                                <th>{{ $t('inventory.karat') }}</th>
                                                <th class="text-end">
                                                    {{ $t('inventory.itemCount') }}
                                                </th>
                                                <th class="text-end">
                                                    {{ $t('inventory.netWeight') }}
                                                </th>
                                                <th class="text-end">
                                                    {{ $t('reports.stockValue') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in reportsStore.stock.by_karat" :key="row.karat">
                                                <td>{{ row.karat }}K</td>
                                                <td class="text-end">{{ row.item_count }}</td>
                                                <td class="text-end">
                                                    {{ formatWeight(row.total_weight) }}
                                                </td>
                                                <td class="text-end">{{ money(row.total_value) }}</td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-card class="h-100" elevation="2">
                                    <v-card-title class="text-subtitle-1 font-weight-medium">
                                        {{ $t('reports.byCategory') }}
                                    </v-card-title>
                                    <v-table density="comfortable">
                                        <thead>
                                            <tr>
                                                <th>{{ $t('inventory.category') }}</th>
                                                <th class="text-end">
                                                    {{ $t('inventory.itemCount') }}
                                                </th>
                                                <th class="text-end">
                                                    {{ $t('inventory.netWeight') }}
                                                </th>
                                                <th class="text-end">
                                                    {{ $t('reports.stockValue') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="row in reportsStore.stock.by_category"
                                                :key="row.category_id"
                                            >
                                                <td>{{ row.category }}</td>
                                                <td class="text-end">{{ row.item_count }}</td>
                                                <td class="text-end">
                                                    {{ formatWeight(row.total_weight) }}
                                                </td>
                                                <td class="text-end">{{ money(row.total_value) }}</td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-card>
                            </v-col>
                        </v-row>
                    </template>
                </ReportPage>
            </v-col>
        </v-row>
    </v-container>
</template>
