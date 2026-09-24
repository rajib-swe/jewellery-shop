<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useInventoryStore } from '../../stores/inventory'

const router = useRouter()
const inventoryStore = useInventoryStore()
const errorMessage = ref('')
const loading = ref(false)

const labels = computed(() => inventoryStore.items)

function formatWeight(value) {
    return `${Number(value || 0).toFixed(3)} g`
}

async function load() {
    loading.value = true
    errorMessage.value = ''

    try {
        await inventoryStore.fetchItems({
            status: 'in_stock',
            per_page: 100,
        }, true)
    } catch {
        errorMessage.value = inventoryStore.error ?? 'Unable to load labels.'
    } finally {
        loading.value = false
    }
}

function printPage() {
    window.print()
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>Gold inventory</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">Item labels</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            Print compact tag and barcode labels for in-stock items.
                        </v-card-text>
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="router.push({ name: 'items' })">
                            Items
                        </v-btn>
                        <v-btn color="primary" prepend-icon="mdi-printer-outline" @click="printPage">
                            Print labels
                        </v-btn>
                    </div>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                >
                    {{ errorMessage }}
                </v-alert>
            </v-col>

            <v-col cols="12">
                <v-progress-linear v-if="loading" indeterminate />
                <v-row v-if="labels.length" class="label-grid" dense>
                    <v-col v-for="item in labels" :key="item.id" cols="12" sm="6" md="4" lg="3">
                        <v-card class="label-card" variant="outlined">
                            <v-card-text>
                                <div class="d-flex justify-space-between align-start ga-2">
                                    <div>
                                        <div class="text-overline">Jewellery item</div>
                                        <div class="text-h6 font-weight-bold">{{ item.name }}</div>
                                    </div>
                                    <v-chip color="primary" size="small" variant="tonal">{{ item.karat }}K</v-chip>
                                </div>
                                <v-divider class="my-3" />
                                <div class="d-flex justify-space-between text-body-2">
                                    <span class="text-medium-emphasis">Tag</span>
                                    <strong>{{ item.tag_no }}</strong>
                                </div>
                                <div class="d-flex justify-space-between text-body-2 mt-1">
                                    <span class="text-medium-emphasis">Weight</span>
                                    <strong>{{ formatWeight(item.net_weight) }}</strong>
                                </div>
                                <div class="d-flex justify-space-between text-body-2 mt-1">
                                    <span class="text-medium-emphasis">Category</span>
                                    <span>{{ item.category.name }}</span>
                                </div>
                                <div v-if="item.barcode" class="barcode mt-4 text-center">
                                    <v-icon icon="mdi-barcode" size="42" />
                                    <div class="text-caption font-weight-medium">{{ item.barcode }}</div>
                                </div>
                                <div v-else class="text-caption text-medium-emphasis text-center mt-4">
                                    No barcode assigned
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
                <v-empty-state
                    v-else-if="!loading"
                    icon="mdi-printer-outline"
                    title="No in-stock labels"
                    text="Add an in-stock item before printing labels."
                />
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.label-card {
    min-height: 230px;
    break-inside: avoid;
}

.barcode {
    letter-spacing: 0.08em;
}

@media print {
    :global(.v-navigation-drawer),
    :global(.v-app-bar),
    .v-btn {
        display: none !important;
    }

    :global(.v-main) {
        padding: 0 !important;
    }

    .v-container {
        max-width: none !important;
        padding: 0 !important;
    }

    .label-grid {
        margin: -4px !important;
    }
}
</style>
