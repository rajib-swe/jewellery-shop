<script>
let sharedIframe = null
let sharedFooter = null
let scriptPromise = null
</script>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ANA_IN_GRAMS, ROTI_IN_GRAMS, VORI_IN_GRAMS } from '../utils/weight'

const props = defineProps({
    mode: {
        type: String,
        default: 'widget',
    },
})

const container = ref(null)
const tableRows = ref([])
const tableDate = ref('')
const isReferenceTable = computed(() => props.mode === 'table')
const moneyFormatter = new Intl.NumberFormat('bn-BD', { maximumFractionDigits: 0 })
const dateFormatter = new Intl.DateTimeFormat('bn-BD', { dateStyle: 'long' })
const banglaDigits = {
    '০': '0',
    '১': '1',
    '২': '2',
    '৩': '3',
    '৪': '4',
    '৫': '5',
    '৬': '6',
    '৭': '7',
    '৮': '8',
    '৯': '9',
}

function moveSharedNodes() {
    if (!container.value) {
        return
    }

    if (sharedIframe) {
        container.value.appendChild(sharedIframe)
    }

    if (sharedFooter) {
        container.value.appendChild(sharedFooter)
    }
}

function captureSharedNodes() {
    const iframe = container.value?.querySelector('iframe')

    if (iframe) {
        sharedIframe = iframe
        sharedFooter = iframe.nextElementSibling ?? sharedFooter
    }
}

function parsePrice(value) {
    const normalized = value
        .replace(/[০-৯]/g, (digit) => banglaDigits[digit])
        .replace(/,/g, '')
    const match = normalized.match(/\d+(?:\.\d+)?/)

    return match ? Number(match[0]) : 0
}

function formatMoney(value) {
    return moneyFormatter.format(value)
}

function updateReferenceRows() {
    if (!isReferenceTable.value) {
        return
    }

    const table = sharedIframe?.contentDocument?.querySelector('table')
    const rows = table ? [...table.querySelectorAll('tbody tr')] : []

    if (rows.length === 0) {
        window.setTimeout(updateReferenceRows, 50)
        return
    }

    tableRows.value = rows.map((row) => {
        const cells = [...row.querySelectorAll('td')]
        const gram = parsePrice(cells[1]?.textContent ?? '')

        return {
            name: cells[0]?.textContent?.trim() ?? '',
            gram,
            bhori: gram * VORI_IN_GRAMS,
            ana: gram * ANA_IN_GRAMS,
            roti: gram * ROTI_IN_GRAMS,
        }
    })
    tableDate.value = dateFormatter.format(new Date())
}

function normalizePriceTable() {
    moveSharedNodes()

    const iframe = sharedIframe

    if (!iframe) {
        return
    }

    iframe.setAttribute('scrolling', 'no')
    iframe.style.display = 'block'
    iframe.style.width = '100%'
    iframe.style.maxWidth = '100%'

    const iframeDocument = iframe.contentDocument
    const documentBody = iframeDocument?.body

    if (!iframeDocument || !documentBody) {
        window.setTimeout(normalizePriceTable, 50)
        return
    }

    iframeDocument.documentElement.style.overflowX = 'hidden'
    iframeDocument.documentElement.style.width = '100%'
    iframeDocument.documentElement.style.maxWidth = '100%'
    documentBody.style.overflowX = 'hidden'
    documentBody.style.overflowY = 'auto'
    documentBody.style.margin = '0'
    documentBody.style.width = '100%'
    documentBody.style.maxWidth = '100%'
    documentBody.style.minWidth = '0'
    documentBody.style.boxSizing = 'border-box'

    if (!iframeDocument.getElementById('goldr-responsive-style')) {
        const style = iframeDocument.createElement('style')
        style.id = 'goldr-responsive-style'
        style.textContent = 'html, body { overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; } *, *::before, *::after { box-sizing: border-box; } table { width: 100% !important; table-layout: fixed !important; } th, td { overflow-wrap: anywhere; word-break: break-word; }'
        iframeDocument.head?.appendChild(style)
    }

    updateReferenceRows()
}

function renderPriceTable() {
    moveSharedNodes()
    window.GoldrPriceTable_render?.()
    window.setTimeout(() => {
        captureSharedNodes()
        normalizePriceTable()
    }, 0)
}

function loadScript() {
    if (scriptPromise) {
        return scriptPromise
    }

    const existingScript = document.getElementById('goldr-price-script')

    if (existingScript) {
        const scriptIsReady = existingScript.dataset.loaded === 'true'
            || typeof window.GoldrPriceTable_render === 'function'

        scriptPromise = scriptIsReady
            ? Promise.resolve()
            : new Promise((resolve) => existingScript.addEventListener('load', resolve, { once: true }))

        return scriptPromise
    }

    scriptPromise = new Promise((resolve) => {
        const script = document.createElement('script')
        script.id = 'goldr-price-script'
        script.src = 'https://www.goldr.org/price.js?gttm'
        script.async = true
        script.addEventListener('load', () => {
            script.dataset.loaded = 'true'
            resolve()
        }, { once: true })
        document.head.appendChild(script)
    })

    return scriptPromise
}

onMounted(async () => {
    moveSharedNodes()
    await loadScript()
    renderPriceTable()
})
</script>

<template>
    <div class="gold-price-wrapper">
        <div
            id="priceTable"
            ref="container"
            aria-hidden="true"
            class="gold-price-source"
            :class="{ 'gold-price-source-hidden': isReferenceTable }"
        />

        <div v-if="isReferenceTable" class="reference-price-table">
            <div class="reference-table-head">
                <h3>
                    সর্বশেষ {{ tableDate || 'আজকের' }} বাংলাদেশ জুয়েলার্স অ্যাসোসিয়েশন (বাজুস) নির্ধারিত স্বর্ণের দামের তালিকা
                </h3>
                <p>Bangladesh Jewellers Association (BAJUS) gold price in Bangladesh today by bhori, gram, ana and rati.</p>
            </div>

            <div v-if="tableRows.length" class="reference-table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">স্বর্ণের ধরন</th>
                            <th scope="col">প্রতি গ্রাম</th>
                            <th scope="col">প্রতি ভরি</th>
                            <th scope="col">প্রতি আনা</th>
                            <th scope="col">প্রতি রতি</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tableRows" :key="row.name">
                            <th scope="row">{{ row.name }}</th>
                            <td>৳ {{ formatMoney(row.gram) }}</td>
                            <td>৳ {{ formatMoney(row.bhori) }}</td>
                            <td>৳ {{ formatMoney(row.ana) }}</td>
                            <td>৳ {{ formatMoney(row.roti) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="reference-table-loading">
                Loading live prices…
            </div>

            <div class="reference-table-source">দামের তথ্যসূত্র: গোল্ডআর · বাজার দাম</div>
        </div>
    </div>
</template>

<style scoped>
.gold-price-wrapper,
.gold-price-source,
.reference-price-table {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.gold-price-source {
    overflow: hidden;
    position: relative;
    z-index: 0;
    contain: layout paint;
}

.gold-price-source :deep(iframe) {
    display: block;
    width: 100% !important;
    max-width: 100%;
    border: 0;
}

.gold-price-source-hidden {
    display: none;
}

.reference-price-table {
    overflow: hidden;
    border: 1px solid rgb(138 106 50 / 18%);
    border-radius: 16px;
    background: #fff;
}

.reference-table-head {
    padding: 22px 24px 18px;
    border-bottom: 1px solid rgb(138 106 50 / 14%);
}

.reference-table-head h3 {
    margin: 0;
    color: #2e2a24;
    font-size: clamp(1.2rem, 2.4vw, 1.7rem);
    line-height: 1.45;
}

.reference-table-head p {
    margin: 8px 0 0;
    color: #6f6659;
    font-size: 0.9rem;
    line-height: 1.6;
}

.reference-table-scroll {
    overflow-x: auto;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
}

.reference-price-table table {
    width: 100%;
    min-width: 680px;
    border-collapse: collapse;
    color: #2e2a24;
    font-size: 0.98rem;
}

.reference-price-table th,
.reference-price-table td {
    padding: 15px 18px;
    border-bottom: 1px solid rgb(138 106 50 / 12%);
    white-space: nowrap;
}

.reference-price-table thead {
    background: #fbf7ee;
    color: #8a6a32;
}

.reference-price-table thead th:not(:first-child),
.reference-price-table tbody td {
    text-align: center;
}

.reference-price-table th:first-child {
    text-align: left;
    font-weight: 600;
}

.reference-price-table tbody tr:last-child th,
.reference-price-table tbody tr:last-child td {
    border-bottom: 0;
}

.reference-table-loading {
    padding: 28px 20px;
    color: #80786d;
    text-align: center;
}

.reference-table-source {
    padding: 11px 18px;
    border-top: 1px solid rgb(138 106 50 / 14%);
    color: #80786d;
    font-size: 0.78rem;
    text-align: center;
}

@media (max-width: 600px) {
    .reference-table-head {
        padding: 18px 16px 15px;
    }

    .reference-price-table th,
    .reference-price-table td {
        padding: 13px 14px;
        font-size: 0.88rem;
    }
}
</style>
