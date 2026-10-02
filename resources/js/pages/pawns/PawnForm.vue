<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useOptionLabels } from '../../constants/options'
import { useInventoryStore } from '../../stores/inventory'
import { useLocaleStore } from '../../stores/locale'
import { usePawnsStore } from '../../stores/pawns'
import { useSettingsStore } from '../../stores/settings'
import { useCurrency, useWeightFormatter } from '../../utils/format'

const router = useRouter()
const localeStore = useLocaleStore()
const inventoryStore = useInventoryStore()
const pawnsStore = usePawnsStore()
const settingsStore = useSettingsStore()
const { karatOptions } = useOptionLabels()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()
const customer = ref(null)
const categories = ref([])
const errorMessage = ref('')
const today = new Date().toISOString().slice(0, 10)
const form = reactive({
    date: today,
    due_date: '',
    principal: '',
    interest_rate: '',
    notes: '',
    items: [blankItem()],
})

function blankItem() {
    return {
        description: '',
        karat: 22,
        gross_weight: '',
        stone_weight: '0.000',
        estimated_value: '',
        category_id: null,
    }
}

const maxLtvPercentage = computed(() => Number(settingsStore.settings.pawn_max_ltv_percentage || 0))
const termDays = computed(() => Number(settingsStore.settings.pawn_term_days || 30))
const pledgedValue = computed(() => form.items.reduce(
    (total, item) => total + (Number(item.estimated_value) || 0),
    0,
))
const maxPrincipal = computed(() => Math.floor(pledgedValue.value * maxLtvPercentage.value / 100 * 100) / 100)
const loanToValue = computed(() => {
    if (pledgedValue.value <= 0) {
        return '0.00'
    }

    return (Number(form.principal || 0) / pledgedValue.value * 100).toFixed(2)
})
const totalNetWeight = computed(() => form.items.reduce(
    (total, item) => total + Math.max(0, (Number(item.gross_weight) || 0) - (Number(item.stone_weight) || 0)),
    0,
))

function money(value) {
    const amount = Number(value || 0)

    return `${currencySymbol.value}${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

function addItem() {
    form.items.push(blankItem())
}

function removeItem(index) {
    form.items.splice(index, 1)
}

function clearForm() {
    customer.value = null
    Object.assign(form, {
        date: today,
        due_date: '',
        principal: '',
        interest_rate: '',
        notes: '',
        items: [blankItem()],
    })
    errorMessage.value = ''
}

function validate() {
    if (!customer.value) {
        return localeStore.t('customers.pickerLabel')
    }

    if (!(Number(form.interest_rate) > 0)) {
        return localeStore.t('pawns.interestRateRequired')
    }

    if (!(Number(form.principal) > 0)) {
        return localeStore.t('pawns.principalRequired')
    }

    if (!form.items.length) {
        return localeStore.t('pawns.needOneItem')
    }

    if (form.items.some((item) => !item.description.trim() || !(Number(item.gross_weight) > 0))) {
        return localeStore.t('pawns.descriptionRequired')
    }

    return null
}

async function submit() {
    errorMessage.value = validate()

    if (errorMessage.value) {
        return
    }

    const payload = new FormData()
    payload.append('customer_id', String(customer.value.id))
    payload.append('date', form.date)
    payload.append('principal', Number(form.principal).toFixed(2))
    payload.append('interest_rate', Number(form.interest_rate).toFixed(2))

    if (form.due_date) {
        payload.append('due_date', form.due_date)
    }

    if (form.notes.trim()) {
        payload.append('notes', form.notes.trim())
    }

    form.items.forEach((item, index) => {
        payload.append(`items[${index}][description]`, item.description.trim())
        payload.append(`items[${index}][karat]`, String(item.karat))
        payload.append(`items[${index}][gross_weight]`, Number(item.gross_weight).toFixed(3))
        payload.append(`items[${index}][stone_weight]`, Number(item.stone_weight || 0).toFixed(3))
        payload.append(`items[${index}][estimated_value]`, Number(item.estimated_value || 0).toFixed(2))

        if (item.category_id) {
            payload.append(`items[${index}][category_id]`, String(item.category_id))
        }
    })

    try {
        const pawn = await pawnsStore.savePawn(payload)

        router.push({ name: 'pawn-profile', params: { id: pawn.id } })
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? pawnsStore.error
            ?? localeStore.t('pawns.saveFailed')
    }
}

async function load() {
    try {
        const settings = await settingsStore.fetchSettings()
        const rate = Number(settings.default_pawn_interest_rate || 0)

        form.interest_rate = rate > 0 ? String(rate) : '2.00'
    } catch {
        form.interest_rate = form.interest_rate || '2.00'
    }

    try {
        categories.value = await inventoryStore.fetchCategories()
    } catch {
        categories.value = []
    }
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row justify="center">
            <v-col cols="12" lg="9">
                <div class="d-flex align-center ga-3 mb-6">
                    <v-btn
                        :aria-label="$t('common.back')"
                        icon="mdi-arrow-left"
                        variant="text"
                        @click="router.push({ name: 'pawns' })"
                    />
                    <div>
                        <v-card-subtitle>{{ $t('pawns.listTitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold pa-0">
                            {{ $t('pawns.newTitle') }}
                        </v-card-title>
                    </div>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    closable
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                    @click:close="errorMessage = ''"
                >
                    {{ errorMessage }}
                </v-alert>

                <v-form @submit.prevent="submit">
                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('pawns.summary') }}
                        </v-card-title>
                        <v-card-text>
                            <v-row>
                                <v-col cols="12">
                                    <CustomerPicker v-model="customer" :label="$t('customers.pickerLabel')" />
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field
                                        v-model="form.date"
                                        :label="$t('pawns.pawnDate')"
                                        type="date"
                                        variant="outlined"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field
                                        v-model="form.principal"
                                        :label="$t('pawns.principal')"
                                        min="0.01"
                                        step="0.01"
                                        :suffix="currencySymbol"
                                        type="number"
                                        variant="outlined"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field
                                        v-model="form.interest_rate"
                                        :label="$t('pawns.interestRate')"
                                        min="0.01"
                                        step="0.01"
                                        :suffix="$t('settings.perMonth')"
                                        type="number"
                                        variant="outlined"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field
                                        v-model="form.due_date"
                                        :label="`${$t('pawns.dueDate')} (${termDays}d)`"
                                        type="date"
                                        variant="outlined"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.notes"
                                        :label="$t('common.notes')"
                                        rows="2"
                                        variant="outlined"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>

                    <v-card class="mb-6" elevation="2">
                        <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                            <span>{{ $t('pawns.pledgedItems') }}</span>
                            <v-btn
                                color="primary"
                                prepend-icon="mdi-plus"
                                size="small"
                                variant="text"
                                @click="addItem"
                            >
                                {{ $t('pawns.addItem') }}
                            </v-btn>
                        </v-card-title>
                        <v-card-text>
                            <div class="pawn-items-scroll">
                                <v-table density="comfortable">
                                    <thead>
                                        <tr>
                                            <th style="width: 260px">{{ $t('pawns.itemDescription') }}</th>
                                            <th style="width: 120px">{{ $t('inventory.karat') }}</th>
                                            <th style="width: 150px">{{ $t('inventory.grossWeight') }}</th>
                                            <th style="width: 150px">{{ $t('inventory.stoneWeight') }}</th>
                                            <th style="width: 160px">{{ $t('pawns.estimatedValue') }}</th>
                                            <th style="width: 190px">{{ $t('inventory.category') }}</th>
                                            <th style="width: 60px" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td>
                                                <v-text-field
                                                    v-model="item.description"
                                                    :label="$t('pawns.itemDescription')"
                                                    density="compact"
                                                    variant="outlined"
                                                />
                                            </td>
                                            <td>
                                                <v-select
                                                    v-model="item.karat"
                                                    :items="karatOptions"
                                                    density="compact"
                                                    variant="outlined"
                                                />
                                            </td>
                                            <td>
                                                <v-text-field
                                                    v-model="item.gross_weight"
                                                    density="compact"
                                                    min="0.001"
                                                    step="0.001"
                                                    type="number"
                                                    variant="outlined"
                                                />
                                            </td>
                                            <td>
                                                <v-text-field
                                                    v-model="item.stone_weight"
                                                    density="compact"
                                                    min="0"
                                                    step="0.001"
                                                    type="number"
                                                    variant="outlined"
                                                />
                                            </td>
                                            <td>
                                                <v-text-field
                                                    v-model="item.estimated_value"
                                                    density="compact"
                                                    min="0.01"
                                                    step="0.01"
                                                    :suffix="currencySymbol"
                                                    type="number"
                                                    variant="outlined"
                                                />
                                            </td>
                                            <td>
                                                <v-select
                                                    v-model="item.category_id"
                                                    clearable
                                                    density="compact"
                                                    :items="categories"
                                                    item-title="name"
                                                    item-value="id"
                                                    :label="$t('inventory.selectCategory')"
                                                    variant="outlined"
                                                />
                                            </td>
                                            <td>
                                                <v-btn
                                                    :aria-label="$t('pawns.removeItem')"
                                                    color="error"
                                                    density="compact"
                                                    icon="mdi-delete-outline"
                                                    size="small"
                                                    variant="text"
                                                    @click="removeItem(index)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </div>

                            <v-alert
                                v-if="!form.items.length"
                                class="mt-4"
                                color="info"
                                density="comfortable"
                                variant="tonal"
                            >
                                {{ $t('pawns.noItems') }}
                            </v-alert>
                        </v-card-text>
                    </v-card>

                    <v-card elevation="2">
                        <v-card-text>
                            <v-row dense>
                                <v-col cols="12" sm="6" md="3">
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('pawns.estimatedValue') }}
                                    </div>
                                    <div class="text-h6 font-weight-medium">
                                        {{ money(pledgedValue) }}
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('settings.pawnMaxLtv') }}
                                    </div>
                                    <div class="text-h6 font-weight-medium">{{ maxLtvPercentage }}%</div>
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <div class="text-caption text-medium-emphasis">LTV</div>
                                    <div
                                        class="text-h6 font-weight-medium"
                                        :class="{ 'text-error': Number(loanToValue) > maxLtvPercentage }"
                                    >
                                        {{ loanToValue }}%
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('inventory.netWeight') }}
                                    </div>
                                    <div class="text-h6 font-weight-medium">
                                        {{ formatWeight(totalNetWeight) }}
                                    </div>
                                </v-col>
                            </v-row>

                            <v-alert
                                v-if="maxPrincipal > 0 && Number(form.principal) > maxPrincipal"
                                class="mt-4"
                                color="warning"
                                density="comfortable"
                                variant="tonal"
                            >
                                {{ $t('settings.pawnMaxLtvHint') }} ({{ money(maxPrincipal) }})
                            </v-alert>
                        </v-card-text>

                        <v-card-actions class="pa-4 pt-0">
                            <v-btn variant="text" @click="clearForm">{{ $t('sales.clearForm') }}</v-btn>
                            <v-spacer />
                            <v-btn
                                color="primary"
                                :loading="pawnsStore.saving"
                                size="large"
                                type="submit"
                            >
                                {{ $t('pawns.addPawn') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-form>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
/* The pledged item grid carries seven inputs per row. `table-layout: auto` lets
   the browser negotiate the declared column widths against each cell's
   min-content, which collapses the description column and leaves Vuetify
   ellipsizing the karat and weight values. A fixed layout honours the widths
   as written, and the floor plus scroll keeps them usable on narrow screens. */
.pawn-items-scroll {
    overflow-x: auto;

    table {
        min-width: 1080px;
        table-layout: fixed;
    }

    :deep(.v-field) {
        /* Fixed layout hands each cell a hard width; without this the outlined
           field can still be clipped rather than ellipsized. */
        min-width: 0;
    }

    :deep(.v-field__input) {
        min-width: 0;
    }
}
</style>
