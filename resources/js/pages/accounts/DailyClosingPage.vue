<script setup>
import { computed, onMounted, ref } from 'vue'
import { getDaySummary } from '../../api/accounts'
import { useAccountsStore } from '../../stores/accounts'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { useCurrency, formatAmount } from '../../utils/format'

const authStore = useAuthStore()
const accountsStore = useAccountsStore()
const localeStore = useLocaleStore()
const currencySymbol = useCurrency()
const canClose = computed(() => authStore.can('close accounts'))
const today = new Date().toISOString().slice(0, 10)
const date = ref(today)
const preview = ref(null)
const loading = ref(false)
const errorMessage = ref('')
const note = ref('')
const closing = computed(() => accountsStore.closings)
const existingClosing = computed(() => closing.value.find((row) => row.date === date.value) ?? null)
const isLocked = computed(() => Boolean(existingClosing.value?.is_locked))
const expected = computed(() => {
    if (!preview.value) {
        return '0.00'
    }

    const opening = Number(preview.value.opening_balance)
    const inTotal = Number(preview.value.total_in)
    const outTotal = Number(preview.value.total_out)

    return (opening + inTotal - outTotal).toFixed(2)
})

function money(value) {
    return formatAmount(value, currencySymbol.value)
}

function methodTitle(method) {
    return localeStore.t(`options.method${method.charAt(0).toUpperCase()}${method.slice(1)}`)
}

function closingTitle(row) {
    return localeStore.t('accounts.closedTitle', {
        date: row.date,
        name: row.closed_by?.name ?? '',
    })
}

async function load() {
    errorMessage.value = ''

    if (!date.value) {
        preview.value = null
        return
    }

    loading.value = true

    try {
        preview.value = await getDaySummary(date.value)
        await accountsStore.fetchClosings(true)
    } catch (error) {
        preview.value = null
        errorMessage.value = error.response?.data?.message ?? localeStore.t('accounts.loadFailed')
    } finally {
        loading.value = false
    }
}

async function closeTheDay() {
    errorMessage.value = ''

    try {
        await accountsStore.closeTheDay({ date: date.value, note: note.value.trim() || null })
        note.value = ''
        await load()
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? accountsStore.error
            ?? localeStore.t('accounts.closeFailed')
    }
}

async function reopenTheDay() {
    errorMessage.value = ''

    try {
        await accountsStore.reopenTheDay(existingClosing.value.id)
        await load()
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? accountsStore.error
            ?? localeStore.t('accounts.reopenFailed')
    }
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="mb-6">
                    <v-card-subtitle>{{ $t('accounts.listSubtitle') }}</v-card-subtitle>
                    <v-card-title class="text-h4 font-weight-bold">
                        {{ $t('accounts.closingTitle') }}
                    </v-card-title>
                    <v-card-text class="text-medium-emphasis pa-0 mt-1">
                        {{ $t('accounts.closingIntro') }}
                    </v-card-text>
                </div>

                <v-card class="mb-6" elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="date"
                                    :label="$t('common.date')"
                                    prepend-inner-icon="mdi-calendar"
                                    type="date"
                                    variant="outlined"
                                    @update:model-value="load"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

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

                <v-progress-linear v-if="loading" indeterminate />

                <v-row v-if="preview">
                    <v-col cols="6" md="3">
                        <v-card color="primary" variant="tonal">
                            <v-card-text>
                                <div class="text-caption">{{ $t('accounts.opening') }}</div>
                                <div class="text-h5 font-weight-bold">
                                    {{ money(preview.opening_balance) }}
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-card color="success" variant="tonal">
                            <v-card-text>
                                <div class="text-caption">{{ $t('accounts.in') }}</div>
                                <div class="text-h5 font-weight-bold">{{ money(preview.total_in) }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-card color="error" variant="tonal">
                            <v-card-text>
                                <div class="text-caption">{{ $t('accounts.out') }}</div>
                                <div class="text-h5 font-weight-bold">{{ money(preview.total_out) }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-card color="secondary" variant="tonal">
                            <v-card-text>
                                <div class="text-caption">{{ $t('accounts.closing') }}</div>
                                <div class="text-h5 font-weight-bold">{{ money(expected) }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-card class="h-100" elevation="2">
                            <v-card-title class="text-subtitle-1 font-weight-medium">
                                {{ $t('accounts.methodBreakdown') }}
                            </v-card-title>
                            <v-table v-if="preview.by_method.length" density="comfortable">
                                <thead>
                                    <tr>
                                        <th>{{ $t('common.method') }}</th>
                                        <th class="text-end">{{ $t('accounts.in') }}</th>
                                        <th class="text-end">{{ $t('accounts.out') }}</th>
                                        <th class="text-end">{{ $t('accounts.net') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in preview.by_method" :key="row.method">
                                        <td>{{ methodTitle(row.method) }}</td>
                                        <td class="text-end">{{ money(row.total_in) }}</td>
                                        <td class="text-end">{{ money(row.total_out) }}</td>
                                        <td class="text-end font-weight-medium">{{ money(row.net) }}</td>
                                    </tr>
                                </tbody>
                            </v-table>
                            <v-card-text v-else class="text-medium-emphasis">
                                {{ $t('accounts.noTransactions') }}
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-card class="h-100" elevation="2">
                            <v-card-title class="text-subtitle-1 font-weight-medium">
                                {{ $t('accounts.dayActions') }}
                            </v-card-title>
                            <v-card-text>
                                <v-alert
                                    v-if="isLocked"
                                    class="mb-4"
                                    color="warning"
                                    density="comfortable"
                                    variant="tonal"
                                >
                                    {{ $t('accounts.lockedBody', {
                                        name: existingClosing?.closed_by?.name ?? '',
                                        at: existingClosing?.closed_at ?? '',
                                    }) }}
                                </v-alert>
                                <v-alert v-else class="mb-4" color="info" density="comfortable" variant="tonal">
                                    {{ $t('accounts.closeBody') }}
                                </v-alert>

                                <template v-if="canClose">
                                    <v-textarea
                                        v-if="!isLocked"
                                        v-model="note"
                                        :label="$t('common.notes')"
                                        rows="2"
                                        variant="outlined"
                                    />

                                    <div class="d-flex flex-wrap ga-2 mt-4">
                                        <v-btn
                                            v-if="!isLocked"
                                            color="primary"
                                            :loading="accountsStore.saving"
                                            prepend-icon="mdi-lock-check"
                                            @click="closeTheDay"
                                        >
                                            {{ $t('accounts.closeDay') }}
                                        </v-btn>
                                        <v-btn
                                            v-else
                                            color="warning"
                                            :loading="accountsStore.saving"
                                            prepend-icon="mdi-lock-open-variant"
                                            @click="reopenTheDay"
                                        >
                                            {{ $t('accounts.reopenDay') }}
                                        </v-btn>
                                    </div>
                                </template>

                                <v-alert
                                    v-else
                                    class="mt-4"
                                    color="warning"
                                    density="comfortable"
                                    variant="tonal"
                                >
                                    {{ $t('accounts.noClosePermission') }}
                                </v-alert>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-card class="mt-6" elevation="2">
                    <v-card-title class="text-subtitle-1 font-weight-medium">
                        {{ $t('accounts.closingHistory') }}
                    </v-card-title>
                    <v-table v-if="closing.length" density="comfortable">
                        <thead>
                            <tr>
                                <th>{{ $t('common.date') }}</th>
                                <th class="text-end">{{ $t('accounts.opening') }}</th>
                                <th class="text-end">{{ $t('accounts.in') }}</th>
                                <th class="text-end">{{ $t('accounts.out') }}</th>
                                <th class="text-end">{{ $t('accounts.closing') }}</th>
                                <th>{{ $t('common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in closing" :key="row.id">
                                <td class="text-no-wrap">{{ row.date }}</td>
                                <td class="text-end">{{ money(row.opening_balance) }}</td>
                                <td class="text-end">{{ money(row.total_in) }}</td>
                                <td class="text-end">{{ money(row.total_out) }}</td>
                                <td class="text-end font-weight-medium">{{ money(row.closing_balance) }}</td>
                                <td>
                                    <v-chip
                                        :color="row.is_locked ? 'success' : 'warning'"
                                        size="small"
                                        variant="tonal"
                                    >
                                        {{ row.is_locked ? $t('accounts.closed') : $t('accounts.open') }}
                                    </v-chip>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ row.closed_by?.name ?? row.reopened_by?.name ?? '' }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                    <v-card-text v-else class="text-medium-emphasis">
                        {{ $t('accounts.noClosings') }}
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
