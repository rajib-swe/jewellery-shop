<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import ReportPage from '../../components/ReportPage.vue'
import { listSuppliers } from '../../api/suppliers'
import { useReportsStore } from '../../stores/reports'
import { useLocaleStore } from '../../stores/locale'

const reportsStore = useReportsStore()
const localeStore = useLocaleStore()
const selected = ref(null)
const suppliers = ref([])
const ledger = computed(() => reportsStore.supplierLedger)
const supplierItems = computed(() => [
    { title: localeStore.t('reports.selectSupplier'), value: null },
    ...suppliers.value.map((supplier) => ({
        title: `${supplier.name} · ${supplier.code}`,
        value: supplier.id,
    })),
])

// The supplier picker reuses the existing suppliers list rather than the
// supplier report, so the dropdown and the ledger page cannot disagree.
async function loadSuppliers() {
    try {
        const response = await listSuppliers({ per_page: 100 })

        suppliers.value = response.data
    } catch {
        suppliers.value = []
    }
}

function load() {
    if (selected.value === null) {
        return
    }

    reportsStore.fetchSupplierLedger(selected.value).catch(() => {
        // The alert above the table shows the message.
    })
}

watch(selected, load)

onMounted(loadSuppliers)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <ReportPage
                    report="supplier-ledger"
                    :error="reportsStore.error"
                    :loading="reportsStore.loading"
                    :params="{ supplier: selected }"
                    :show-dates="false"
                    :subtitle="$t('reports.supplierSubtitle')"
                    :title="$t('reports.supplierTitle')"
                >
                    <template #default="{ formatAmount: money }">
                        <v-card class="mb-6" elevation="2">
                            <v-card-text>
                                <v-select
                                    v-model="selected"
                                    :items="supplierItems"
                                    :label="$t('nav.suppliers')"
                                    prepend-inner-icon="mdi-store-account-outline"
                                    variant="outlined"
                                />
                            </v-card-text>
                        </v-card>

                        <v-card v-if="ledger" elevation="2">
                            <v-card-text>
                                <v-row>
                                    <v-col cols="12" md="4">
                                        <div class="text-caption">
                                            {{ $t('suppliers.totalPurchases') }}
                                        </div>
                                        <div class="text-h6 font-weight-medium">
                                            {{ money(ledger.total_purchases) }}
                                        </div>
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <div class="text-caption">
                                            {{ $t('suppliers.totalPayments') }}
                                        </div>
                                        <div class="text-h6 font-weight-medium">
                                            {{ money(ledger.total_payments) }}
                                        </div>
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <div class="text-caption">
                                            {{ $t('suppliers.balance') }}
                                        </div>
                                        <div
                                            class="text-h6 font-weight-bold"
                                            :class="{ 'text-error': Number(ledger.balance) > 0 }"
                                        >
                                            {{ money(ledger.balance) }}
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
                                                :color="row.kind === 'purchase' ? 'primary' : 'success'"
                                                size="small"
                                                variant="tonal"
                                            >
                                                {{ $t(row.kind === 'purchase' ? 'options.ledgerRowPurchase' : 'options.ledgerRowPayment') }}
                                            </v-chip>
                                        </td>
                                        <td>{{ row.reference || '—' }}</td>
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
                                        <th colspan="3">{{ $t('common.total') }}</th>
                                        <th class="text-end">{{ money(ledger.total_purchases) }}</th>
                                        <th class="text-end text-success">
                                            {{ money(ledger.total_payments) }}
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
