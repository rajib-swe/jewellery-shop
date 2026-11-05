<script setup>
import { onMounted, ref } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { useOptionLabels } from '../../constants/options'
import { useReportsStore } from '../../stores/reports'

const reportsStore = useReportsStore()
const { paymentMethodOptions: methodItems } = useOptionLabels()
const reportRef = ref(null)

function load(params) {
    reportsStore.fetchInterestEarned(params).catch(() => {
        // The alert above the table shows the message.
    })
}

function paymentTitle(method) {
    return methodItems.value.find((item) => item.value === method)?.title ?? method
}

function typeTitle(type) {
    return `options.pawnType${type.charAt(0).toUpperCase()}${type.slice(1)}`
}

onMounted(() => load(reportRef.value.query()))
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <ReportPage
                    ref="reportRef"
                    report="interest-earned"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    :subtitle="$t('reports.interestSubtitle')"
                    :title="$t('reports.interestTitle')"
                    @change="load"
                >
                    <template #default="{ formatAmount: money }">
                        <v-row v-if="reportsStore.interestEarned">
                            <v-col cols="6" md="3">
                                <v-card color="success" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.interestCollected') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.interestEarned.interest_collected) }}
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ $t('reports.collections', {
                                                count: reportsStore.interestEarned.interest_collection_count,
                                            }) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-card color="primary" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.principalCollected') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.interestEarned.principal_collected) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-card color="info" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.redemptionCollected') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.interestEarned.redemption_collected) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-card color="warning" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.interestAccrued') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.interestEarned.interest_accrued_outstanding) }}
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ $t('reports.interestAccruedHint') }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>

                        <v-card v-if="reportsStore.interestEarned" class="mt-2" elevation="2">
                            <v-table v-if="reportsStore.interestEarned.rows.length" density="comfortable">
                                <thead>
                                    <tr>
                                        <th>{{ $t('common.date') }}</th>
                                        <th>{{ $t('pawns.pawnNo') }}</th>
                                        <th>{{ $t('reports.customer') }}</th>
                                        <th>{{ $t('common.status') }}</th>
                                        <th>{{ $t('common.method') }}</th>
                                        <th class="text-end">{{ $t('common.amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in reportsStore.interestEarned.rows"
                                        :key="`${row.date}-${index}`"
                                    >
                                        <td class="text-no-wrap">{{ row.date }}</td>
                                        <td>
                                            <router-link
                                                class="text-decoration-none font-weight-medium"
                                                :to="{ name: 'pawn-profile', params: { id: row.pawn_id } }"
                                            >
                                                {{ row.pawn_no }}
                                            </router-link>
                                        </td>
                                        <td>{{ row.customer || '—' }}</td>
                                        <td>
                                            <v-chip size="small" variant="tonal">
                                                {{ $t(typeTitle(row.type)) }}
                                            </v-chip>
                                        </td>
                                        <td>{{ paymentTitle(row.method) }}</td>
                                        <td class="text-end font-weight-medium">
                                            {{ money(row.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
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
