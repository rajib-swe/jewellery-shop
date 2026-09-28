<script setup>
import { computed } from 'vue'
import { useLocaleStore } from '../stores/locale'
import { useCurrency, formatAmount } from '../utils/format'

const props = defineProps({
    rows: { type: Array, required: true },
    height: { type: Number, default: 180 },
})

const localeStore = useLocaleStore()
const currencySymbol = useCurrency()

/**
 * A dependency free bar chart.
 *
 * The project has no charting library and the plan does not add one, so the
 * 30 day trend is drawn as plain SVG rather than pulling in a dependency for
 * one view. Every bar is a real element with a tooltip, so the chart is
 * readable with a mouse and with a keyboard.
 */
const bars = computed(() => {
    const values = props.rows.map((row) => Number(row.total || 0))
    const peak = Math.max(...values, 0)
    const width = Math.max(props.rows.length, 1)
    const slot = 100 / width

    return props.rows.map((row, index) => {
        const value = Number(row.total || 0)
        // A zero day still gets a hairline so the axis reads as continuous.
        const share = peak > 0 ? value / peak : 0

        return {
            date: row.date,
            value,
            count: row.count,
            x: index * slot,
            width: slot,
            height: Math.max(share * 100, value > 0 ? 2 : 0.5),
            label: `${formatAmount(row.total, currencySymbol.value)} · ${row.date}`,
        }
    })
})

const total = computed(() => props.rows.reduce((sum, row) => sum + Number(row.total || 0), 0))
const count = computed(() => props.rows.reduce((sum, row) => sum + row.count, 0))

function shortDate(date) {
    return date.slice(5)
}
</script>

<template>
    <div>
        <div class="d-flex flex-wrap ga-6 mb-3">
            <div>
                <div class="text-caption text-medium-emphasis">
                    {{ $t('reports.chartTotal') }}
                </div>
                <div class="text-h6 font-weight-bold">{{ formatAmount(total, currencySymbol) }}</div>
            </div>
            <div>
                <div class="text-caption text-medium-emphasis">
                    {{ $t('reports.chartInvoices') }}
                </div>
                <div class="text-h6 font-weight-bold">{{ count }}</div>
            </div>
        </div>

        <svg
            class="trend-chart"
            preserveAspectRatio="none"
            role="img"
            :viewBox="`0 0 100 100`"
            :style="{ height: `${height}px` }"
            :aria-label="$t('reports.salesTrend')"
        >
            <line
                class="trend-axis"
                x1="0"
                x2="100"
                y1="100"
                y2="100"
            />
            <rect
                v-for="bar in bars"
                :key="bar.date"
                class="trend-bar"
                :fill="bar.value > 0 ? 'rgb(var(--v-theme-primary))' : 'rgba(128,128,128,0.18)'"
                :x="bar.x + bar.width * 0.15"
                :y="100 - bar.height"
                :width="bar.width * 0.7"
                :height="bar.height"
            >
                <title>{{ bar.label }}</title>
            </rect>
        </svg>

        <div class="d-flex justify-space-between text-caption text-medium-emphasis mt-1">
            <span v-for="row in [rows[0], rows[rows.length - 1]].filter(Boolean)" :key="row.date">
                {{ shortDate(row.date) }}
            </span>
        </div>

        <v-alert v-if="!count" class="mt-3" color="info" density="compact" variant="tonal">
            {{ $t('reports.noTrendData', { days: rows.length }) }}
        </v-alert>
    </div>
</template>

<style scoped>
.trend-chart {
    display: block;
    overflow: visible;
    width: 100%;
}

.trend-bar {
    transition: opacity 120ms ease-in-out;
}

.trend-bar:hover {
    opacity: 0.75;
}

.trend-axis {
    stroke: rgba(128, 128, 128, 0.35);
    stroke-width: 0.4;
}
</style>
