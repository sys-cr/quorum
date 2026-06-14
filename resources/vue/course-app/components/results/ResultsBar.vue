<template>
    <div ref="rootEl" class="quorum--results quorum--results-bar">
        <Bar :data="chartData" :options="chartOptions" :aria-label="ariaSummary" />
        <table class="quorum--visually-hidden">
            <caption>{{ ariaSummary }}</caption>
            <thead>
                <tr><th scope="col">{{ t('results.tableOption') }}</th><th scope="col">{{ t('results.tableVotes') }}</th></tr>
            </thead>
            <tbody>
                <tr v-for="opt in options" :key="opt.id">
                    <td>{{ opt.label }}</td>
                    <td>{{ counts[opt.id] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Bar } from 'vue-chartjs'
import {
    Chart as ChartJS,
    BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend,
} from 'chart.js'
import {
    auroraColors, isHighContrast, prefersReducedMotion, LABEL_OVERLAY_DARK,
    chartFg, chartGrid,
} from './chartTokens.js'

ChartJS.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const { t } = useI18n()

const props = defineProps({
    options:     { type: Array,   required: true },
    counts:      { type: Object,  default: () => ({}) },
    orientation: { type: String,  default: 'vertical', validator: v => ['vertical', 'horizontal'].includes(v) },
})

/* Track container width live — below ~480px a vertical bar with longer labels
   can't stay readable (column width ≈ 80–100px). On such containers the
   orientation flips to horizontal: labels then wrap to multiple lines left of
   the bars, where there is far more horizontal space. */
const rootEl       = ref(null)
const containerW   = ref(Number.POSITIVE_INFINITY)   // start generous so
                                                       // SSR/tests don't override the desired mode
const NARROW_PX    = 480
let resizeObs      = null

onMounted(() => {
    if (!rootEl.value || typeof ResizeObserver === 'undefined') return
    resizeObs = new ResizeObserver(([entry]) => {
        containerW.value = entry.contentRect.width
    })
    resizeObs.observe(rootEl.value)
})
onBeforeUnmount(() => { resizeObs?.disconnect() })

const effectiveVertical = computed(() => {
    if (props.orientation !== 'vertical') return false
    return containerW.value >= NARROW_PX
})

/* HC rule: high-contrast has no colors. In a bar chart the bar height already
   differentiates, so a single black fill is most readable. Outside HC the
   Aurora avatar palette rotates cyclically through the options. */
const palette = computed(() => isHighContrast() ? null : auroraColors(rootEl.value))

const chartData = computed(() => ({
    labels:   props.options.map(o => o.label),
    datasets: [{
        label:           t('results.tableVotes'),
        data:            props.options.map(o => props.counts[o.id] ?? 0),
        backgroundColor: palette.value === null
            ? LABEL_OVERLAY_DARK
            : props.options.map((_, i) => palette.value[i % palette.value.length]),
        borderWidth:     0,
        borderRadius:    4,
    }],
}))

/* Tick/legend fonts noticeably larger than the Chart.js default (12px) so axis
   labels stay readable even in a small chart container — Stud.IP's default Lato
   has a fairly narrow x-height. */
const TICK_FONT = { size: 14, weight: '500' }

/* Long labels are NOT rotated (unreadable on mobile) but wrapped to multiple
   lines. Word-by-word split, ~14 chars per line. Chart.js renders a string
   array as a multi-line tick label. */
const wrapLabel = (label, maxChars = 14) => {
    const words = String(label).split(/\s+/)
    const lines = []
    let buf = ''
    for (const w of words) {
        if (!buf) { buf = w; continue }
        if (buf.length + 1 + w.length <= maxChars) buf += ' ' + w
        else { lines.push(buf); buf = w }
    }
    if (buf) lines.push(buf)
    return lines
}

const chartOptions = computed(() => {
    const isVertical = effectiveVertical.value
    /* Theme foreground for tick labels — resolved from the current document
       style at chart-build time. */
    const tickColor = chartFg()
    const gridColor = chartGrid()
    return {
        responsive:        true,
        maintainAspectRatio: false,
        indexAxis:         isVertical ? 'x' : 'y',
        animation:         prefersReducedMotion() ? false : { duration: 250 },
        layout:            { padding: { bottom: 4, left: 4, right: 4 } },
        plugins:           {
            legend: { display: false },
        },
        scales: {
            x: {
                ticks: {
                    precision: 0,
                    font:        TICK_FONT,
                    color:       tickColor,
                    autoSkip:    false,
                    maxRotation: 0,                     // never rotate
                    minRotation: 0,
                    /* Index axis (vertical bar): multi-line category labels.
                       Value axis (horizontal bar): default numeric ticks. */
                    callback:    isVertical
                        ? function (value) { return wrapLabel(this.getLabelForValue(value)) }
                        : undefined,
                },
                grid:   { display: false },
                border: { color: gridColor },
            },
            y: {
                ticks: {
                    precision: 0,
                    font:        TICK_FONT,
                    color:       tickColor,
                    autoSkip:    false,
                    /* Horizontal bar: index on Y → multi-line labels there */
                    callback:    !isVertical
                        ? function (value) { return wrapLabel(this.getLabelForValue(value)) }
                        : undefined,
                },
                grid:   { color: gridColor },
                border: { color: gridColor },
                beginAtZero: true,
            },
        },
    }
})

const ariaSummary = computed(() => {
    const items = props.options.map(o => {
        const n = props.counts[o.id] ?? 0
        return `${o.label}: ${t('results.votesUnit', { n }, n)}`
    }).join(', ')
    return t('results.barSummary', { items })
})
</script>

<style scoped>
.quorum--results {
    /* Slightly taller than the other chart types: multi-line tick labels need
       room below/left, otherwise the bars shrink toward 0 on mobile. */
    block-size: clamp(280px, 42vh, 440px);
    inline-size: 100%;
    color: var(--quorum-fg);
}
.quorum--visually-hidden {
    position: absolute;
    inline-size: 1px; block-size: 1px;
    padding: 0; margin: -1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}
</style>
