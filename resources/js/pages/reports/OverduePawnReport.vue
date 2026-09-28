<script setup>
import { onMounted } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { useReportsStore } from '../../stores/reports'

const reportsStore = useReportsStore()

function load() {
    reportsStore.fetchOverduePawns().catch(() => {
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
                    report="overdue-pawns"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    :show-dates="false"
                    :subtitle="$t('reports.overdueSubtitle')"
                    :title="$t('reports.overdueTitle')"
                >
                    <template #default="{ formatAmount: money }">
                        <v-card elevation="2">
                            <v-table v-if="reportsStore.overduePawns?.rows.length" density="comfortable">
                                <thead>
                                    <tr>
                                        <th>{{ $t('pawns.pawnNo') }}</th>
                                        <th>{{ $t('reports.customer') }}</th>
                                        <th>{{ $t('common.phone') }}</th>
                                        <th>{{ $t('pawns.dueDate') }}</th>
                                        <th class="text-end">{{ $t('reports.daysOverdue') }}</th>
                                        <th class="text-end">{{ $t('reports.totalPayable') }}</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in reportsStore.overduePawns.rows"
                                        :key="row.id"
                                    >
                                        <td>
                                            <router-link
                                                class="text-decoration-none font-weight-medium"
                                                :to="{ name: 'pawn-profile', params: { id: row.id } }"
                                            >
                                                {{ row.pawn_no }}
                                            </router-link>
                                        </td>
                                        <td>{{ row.customer.name || '—' }}</td>
                                        <td>{{ row.customer.phone || '—' }}</td>
                                        <td class="text-no-wrap">{{ row.due_date }}</td>
                                        <td class="text-end">
                                            <v-chip color="error" size="small" variant="tonal">
                                                {{ row.days_overdue }}
                                            </v-chip>
                                        </td>
                                        <td class="text-end font-weight-medium">
                                            {{ money(row.total_payable) }}
                                        </td>
                                        <td class="text-end">
                                            <v-btn
                                                size="small"
                                                variant="text"
                                                :to="{ name: 'pawn-profile', params: { id: row.id } }"
                                            >
                                                {{ $t('reports.open') }}
                                            </v-btn>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>

                            <v-card-text v-else class="text-medium-emphasis">
                                {{ $t('reports.noOverduePawns') }}
                            </v-card-text>
                        </v-card>
                    </template>
                </ReportPage>
            </v-col>
        </v-row>
    </v-container>
</template>
