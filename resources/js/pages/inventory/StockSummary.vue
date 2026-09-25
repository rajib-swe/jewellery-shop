<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInventoryStore } from '../../stores/inventory'
import { useLocaleStore } from '../../stores/locale'
import { useWeightFormatter } from '../../utils/format'

const router = useRouter()
const inventoryStore = useInventoryStore()
const localeStore = useLocaleStore()
const { formatWeight } = useWeightFormatter()
const errorMessage = computed(() => inventoryStore.error)

const karatLabel = (value) => `${value}K`

async function load() {
    inventoryStore.clearError()

    try {
        await inventoryStore.fetchSummary(true)
    } catch {
        return
    }
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('inventory.itemsSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">{{ $t('inventory.summaryTitle') }}</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('inventory.summaryIntro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        prepend-icon="mdi-package-variant-closed"
                        variant="outlined"
                        @click="router.push({ name: 'items' })"
                    >
                        {{ $t('inventory.itemsTitle') }}
                    </v-btn>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                    closable
                    @click:close="inventoryStore.clearError"
                >
                    {{ errorMessage }}
                </v-alert>
            </v-col>

            <v-col cols="12" sm="6" md="4">
                <v-card color="primary" variant="tonal">
                    <v-card-text>
                        <v-icon icon="mdi-package-variant-closed" size="30" />
                        <div class="text-caption mt-3">{{ $t('inventory.totalItems') }}</div>
                        <div class="text-h4 font-weight-bold">{{ inventoryStore.summary.total_items }}</div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" md="4">
                <v-card color="secondary" variant="tonal">
                    <v-card-text>
                        <v-icon icon="mdi-scale-balance" size="30" />
                        <div class="text-caption mt-3">{{ $t('inventory.totalNetWeight') }}</div>
                        <div class="text-h4 font-weight-bold">{{ formatWeight(inventoryStore.summary.total_net_weight) }}</div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="4">
                <v-card variant="outlined">
                    <v-card-text class="d-flex align-center ga-3">
                        <v-icon color="primary" icon="mdi-chart-donut" size="30" />
                        <div>
                            <div class="text-caption">{{ $t('inventory.totalItems') }}</div>
                            <div class="text-subtitle-1 font-weight-bold">{{ $t('options.inStock') }}</div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12">
                <v-card elevation="2">
                    <v-card-item>
                        <v-card-title class="text-h6">{{ $t('inventory.netWeight') }}</v-card-title>
                        <v-card-subtitle>{{ $t('inventory.summaryIntro') }}</v-card-subtitle>
                    </v-card-item>
                    <v-progress-linear v-if="inventoryStore.loadingSummary" indeterminate />
                    <v-table class="d-none d-sm-table">
                        <thead>
                            <tr>
                                <th>{{ $t('inventory.karat') }}</th>
                                <th class="text-right">{{ $t('inventory.itemCount') }}</th>
                                <th class="text-right">{{ $t('inventory.netWeight') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in inventoryStore.summary.by_karat" :key="row.karat">
                                <td>{{ karatLabel(row.karat) }}</td>
                                <td class="text-right">{{ row.item_count }}</td>
                                <td class="text-right font-weight-medium">{{ formatWeight(row.total_net_weight) }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                    <v-list v-if="inventoryStore.summary.by_karat.length" class="d-sm-none">
                        <v-list-item
                            v-for="row in inventoryStore.summary.by_karat"
                            :key="row.karat"
                            :title="karatLabel(row.karat)"
                        >
                            <template #append>
                                <div class="text-right">
                                    <div class="font-weight-bold">{{ formatWeight(row.total_net_weight) }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ row.item_count }} {{ $t('inventory.totalItems') }}
                                    </div>
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
