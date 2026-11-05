<script setup>
import { computed, ref, watch } from 'vue'
import { reportExportUrl, reportPrintUrl } from '../api/reports'
import { useLocaleStore } from '../stores/locale'
import { useCurrency, formatAmount, useWeightFormatter } from '../utils/format'

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    report: { type: String, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    groupBy: { type: String, default: '' },
    extraQuery: { type: Object, default: () => ({}) },
    showDates: { type: Boolean, default: true },
    showGroupBy: { type: Boolean, default: false },
    /** Extra query parameters a report needs, e.g. a customer id. */
    params: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['change'])

const localeStore = useLocaleStore()
const currencySymbol = useCurrency()
const { formatWeight } = useWeightFormatter()
const groupOptions = computed(() => [
    { title: localeStore.t('reports.byDay'), value: 'day' },
    { title: localeStore.t('reports.byMonth'), value: 'month' },
])

function today() {
    return new Date().toISOString().slice(0, 10)
}

function start() {
    const date = new Date()
    date.setDate(date.getDate() - 29)

    return date.toISOString().slice(0, 10)
}

const range = ref({ from: props.from || start(), to: props.to || today() })
const groupBy = ref(props.groupBy || 'day')

watch(() => [props.from, props.to], ([nextFrom, nextTo]) => {
    if (nextFrom || nextTo) {
        range.value = { from: nextFrom || start(), to: nextTo || today() }
    }
})

/**
 * The query a report is loaded and exported with, so the table, the spreadsheet
 * and the printout can never be looking at different windows.
 */
function query() {
    return {
        from: range.value.from || null,
        to: range.value.to || null,
        group_by: props.showGroupBy ? groupBy.value : null,
        ...props.params,
        ...props.extraQuery,
    }
}

function apply() {
    emit('change', query())
}

function clearRange() {
    range.value = { from: start(), to: today() }
    apply()
}

function printReport() {
    window.open(reportPrintUrl(props.report, query()), '_blank')
}

function exportReport() {
    window.open(reportExportUrl(props.report, query()), '_blank')
}

defineExpose({ query })
</script>

<template>
    <div>
        <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
            <div>
                <v-card-subtitle>{{ $t('reports.subtitle') }}</v-card-subtitle>
                <v-card-title class="text-h4 font-weight-bold">{{ title }}</v-card-title>
                <v-card-text v-if="subtitle" class="text-medium-emphasis pa-0 mt-1">
                    {{ subtitle }}
                </v-card-text>
            </div>

            <div class="d-flex flex-wrap ga-2">
                <v-btn
                    prepend-icon="mdi-printer-outline"
                    variant="outlined"
                    @click="printReport"
                >
                    {{ $t('reports.print') }}
                </v-btn>
                <v-btn
                    prepend-icon="mdi-microsoft-excel"
                    variant="outlined"
                    @click="exportReport"
                >
                    {{ $t('reports.exportExcel') }}
                </v-btn>
            </div>
        </div>

        <v-card class="mb-6" elevation="2">
            <v-card-text>
                <v-row align="center">
                    <v-col v-if="showDates" cols="12" sm="6" md="3">
                        <v-text-field
                            v-model="range.from"
                            :label="$t('common.from')"
                            type="date"
                            variant="outlined"
                        />
                    </v-col>
                    <v-col v-if="showDates" cols="12" sm="6" md="3">
                        <v-text-field
                            v-model="range.to"
                            :label="$t('common.to')"
                            type="date"
                            variant="outlined"
                        />
                    </v-col>
                    <v-col v-if="showGroupBy" cols="12" sm="6" md="3">
                        <v-select
                            v-model="groupBy"
                            :items="groupOptions"
                            :label="$t('reports.groupBy')"
                            variant="outlined"
                        />
                    </v-col>
                    <v-col class="d-flex ga-2" cols="12" md="3">
                        <v-btn color="primary" :loading="loading" @click="apply">
                            {{ $t('reports.apply') }}
                        </v-btn>
                        <v-btn v-if="showDates" variant="text" @click="clearRange">
                            {{ $t('reports.last30') }}
                        </v-btn>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <v-alert
            v-if="error"
            class="mb-6"
            closable
            color="error"
            density="comfortable"
            type="error"
            variant="tonal"
        >
            {{ error }}
        </v-alert>

        <v-progress-linear v-if="loading" class="mb-2" indeterminate />

        <slot
            :currency-symbol="currencySymbol"
            :format-weight="formatWeight"
            :format-amount="(value) => formatAmount(value, currencySymbol)"
            :query="query"
        />
    </div>
</template>
