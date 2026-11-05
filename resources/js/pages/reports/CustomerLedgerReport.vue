<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { listCustomers } from '../../api/customers'
import { useReportsStore } from '../../stores/reports'
import { useLocaleStore } from '../../stores/locale'

const reportsStore = useReportsStore()
const localeStore = useLocaleStore()
const reportRef = ref(null)
const selected = ref(null)
const customers = ref([])
const ledger = computed(() => reportsStore.customerLedger)
const customerItems = computed(() => [
    { title: localeStore.t('reports.selectCustomer'), value: null },
    ...customers.value.map((customer) => ({
        title: `${customer.name} · ${customer.phone}`,
        value: customer.id,
    })),
])

async function loadCustomers() {
    try {
        const response = await listCustomers({ per_page: 100 })

        customers.value = response.data
    } catch {
        customers.value = []
    }
}

function load() {
    if (selected.value === null) {
        return
    }

    const params = reportRef.value?.query() ?? {}

    reportsStore.fetchCustomerLedger(selected.value, params).catch(() => {
        // The alert above the table shows the message.
    })
}

watch(selected, load)

onMounted(loadCustomers)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <ReportPage
                    ref="reportRef"
                    report="customer-ledger"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    :params="{ customer: selected }"
                    :subtitle="$t('reports.customerSubtitle')"
                    :title="$t('reports.customerTitle')"
                >
                    <template #default="{ formatAmount: money, formatWeight }">
                        <v-card class="mb-6" elevation="2">
                            <v-card-text>
                                <v-select
                                    v-model="selected"
                                    :items="customerItems"
                                    :label="$t('nav.customers')"
                                    prepend-inner-icon="mdi-account-outline"
                                    variant="outlined"
                                />
                            </v-card-text>
                        </v-card>

                        <v-card v-if="ledger" elevation="2">
                            <v-card-text>
                                <v-row>
                                    <v-col cols="6" md="3">
                                        <div class="text-caption">
                                            {{ $t('reports.openingBalance') }}
                                        </div>
                                        <div class="text-h6 font-weight-medium">
                                            {{ money(ledger.opening_balance) }}
                                        </div>
                                    </v-col>
                                    <v-col cols="6" md="3">
                                        <div class="text-caption">
                                            {{ $t('reports.totalSales') }}
                                        </div>
                                        <div class="text-h6 font-weight-medium">
                                            {{ money(ledger.total_sales) }}
                                        </div>
                                    </v-col>
                                    <v-col cols="6" md="3">
                                        <div class="text-caption">
                                            {{ $t('common.paid') }}
                                        </div>
                                        <div class="text-h6 font-weight-medium">
                                            {{ money(ledger.total_paid) }}
                                        </div>
                                    </v-col>
                                    <v-col cols="6" md="3">
                                        <div class="text-caption">
                                            {{ $t('common.due') }}
                                        </div>
                                        <div
                                            class="text-h6 font-weight-bold"
                                            :class="{ 'text-error': Number(ledger.closing_balance) > 0 }"
                                        >
                                            {{ money(ledger.closing_balance) }}
                                        </div>
                                    </v-col>
                                </v-row>
                            </v-card-text>

                            <v-table v-if="ledger.transactions.length" density="comfortable">
                                <thead>
                                    <tr>
                                        <th>{{ $t('common.date') }}</th>
                                        <th>{{ $t('reports.type') }}</th>
                                        <th>{{ $t('common.reference') }}</th>
                                        <th>{{ $t('reports.weight') }}</th>
                                        <th class="text-end">{{ $t('reports.debit') }}</th>
                                        <th class="text-end">{{ $t('reports.credit') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in ledger.transactions"
                                        :key="`${row.kind}-${row.id}`"
                                    >
                                        <td class="text-no-wrap">{{ row.date }}</td>
                                        <td>
                                            <v-chip
                                                :color="row.kind === 'sale' ? 'primary' : 'success'"
                                                size="small"
                                                variant="tonal"
                                            >
                                                {{ $t(row.kind === 'sale' ? 'nav.sales' : 'sales.payments') }}
                                            </v-chip>
                                        </td>
                                        <td>
                                            <router-link
                                                v-if="row.kind === 'sale'"
                                                class="text-decoration-none"
                                                :to="{ name: 'sale-profile', params: { id: row.id } }"
                                            >
                                                {{ row.reference }}
                                            </router-link>
                                            <span v-else>{{ row.reference || '—' }}</span>
                                        </td>
                                        <td class="text-end">
                                            {{ row.weight ? formatWeight(row.weight) : '—' }}
                                        </td>
                                        <td class="text-end">
                                            <span v-if="Number(row.debit) > 0">
                                                {{ money(row.debit) }}
                                            </span>
                                            <span v-else class="text-medium-emphasis">—</span>
                                        </td>
                                        <td class="text-end">
                                            <span
                                                v-if="Number(row.credit) > 0"
                                                class="font-weight-medium text-success"
                                            >
                                                {{ money(row.credit) }}
                                            </span>
                                            <span v-else class="text-medium-emphasis">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4">{{ $t('common.total') }}</th>
                                        <th class="text-end">{{ money(ledger.total_sales) }}</th>
                                        <th class="text-end text-success">
                                            {{ money(ledger.total_paid) }}
                                        </th>
                                    </tr>
                                </tfoot>
                            </v-table>

                            <v-card-text v-else class="text-medium-emphasis">
                                {{ $t('reports.noLedgerRows') }}
                            </v-card-text>
                        </v-card>
                    </template>
                </ReportPage>
            </v-col>
        </v-row>
    </v-container>
</template>
