<script setup>
import { onMounted } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { useReportsStore } from '../../stores/reports'

const reportsStore = useReportsStore()

function load() {
    reportsStore.fetchPawnOutstanding().catch(() => {
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
                    report="pawn-outstanding"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    :show-dates="false"
                    :subtitle="$t('reports.pawnSubtitle')"
                    :title="$t('reports.pawnTitle')"
                >
                    <template #default="{ formatAmount: money }">
                        <v-row v-if="reportsStore.pawnOutstanding">
                            <v-col cols="6" md="2">
                                <v-card color="primary" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">{{ $t('reports.activePawns') }}</div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ reportsStore.pawnOutstanding.active_count }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-card color="secondary" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.totalPrincipal') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.pawnOutstanding.total_principal) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-card color="warning" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.outstandingPrincipal') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.pawnOutstanding.outstanding_principal) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-card color="info" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.interestDue') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.pawnOutstanding.interest_due) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-card color="success" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.totalPayable') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ money(reportsStore.pawnOutstanding.total_payable) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-card color="error" variant="tonal">
                                    <v-card-text>
                                        <div class="text-caption">
                                            {{ $t('reports.overdueCount') }}
                                        </div>
                                        <div class="text-h5 font-weight-bold">
                                            {{ reportsStore.pawnOutstanding.overdue_count }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>

                        <v-card v-if="reportsStore.pawnOutstanding" class="mt-2" elevation="2">
                            <v-table v-if="reportsStore.pawnOutstanding.rows.length" density="comfortable">
                                <thead>
                                    <tr>
                                        <th>{{ $t('pawns.pawnNo') }}</th>
                                        <th>{{ $t('reports.customer') }}</th>
                                        <th>{{ $t('pawns.dueDate') }}</th>
                                        <th class="text-end">{{ $t('pawns.principal') }}</th>
                                        <th class="text-end">
                                            {{ $t('reports.outstandingPrincipal') }}
                                        </th>
                                        <th class="text-end">{{ $t('reports.interestDue') }}</th>
                                        <th class="text-end">{{ $t('reports.totalPayable') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in reportsStore.pawnOutstanding.rows"
                                        :key="row.id"
                                    >
                                        <td>
                                            <router-link
                                                class="text-decoration-none font-weight-medium"
                                                :to="{ name: 'pawn-profile', params: { id: row.id } }"
                                            >
                                                {{ row.pawn_no }}
                                            </router-link>
                                            <v-chip
                                                v-if="row.is_overdue"
                                                class="ml-2"
                                                color="error"
                                                size="x-small"
                                                variant="tonal"
                                            >
                                                {{ $t('options.overdue') }}
                                            </v-chip>
                                        </td>
                                        <td>{{ row.customer.name || '—' }}</td>
                                        <td class="text-no-wrap">{{ row.due_date }}</td>
                                        <td class="text-end">{{ money(row.principal) }}</td>
                                        <td class="text-end">
                                            {{ money(row.outstanding_principal) }}
                                        </td>
                                        <td class="text-end">{{ money(row.interest_due) }}</td>
                                        <td class="text-end font-weight-medium">
                                            {{ money(row.total_payable) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>

                            <v-card-text v-else class="text-medium-emphasis">
                                {{ $t('reports.noActivePawns') }}
                            </v-card-text>
                        </v-card>
                    </template>
                </ReportPage>
            </v-col>
        </v-row>
    </v-container>
</template>
