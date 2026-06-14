<template>
    <div class="quorum--results quorum--results-bubble">
        <Bubble :data="chartData" :options="chartOptions" :aria-label="ariaSummary" />
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
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Bubble } from 'vue-chartjs'
import {
    Chart as ChartJS,
    BubbleController, PointElement, LinearScale, Tooltip,
} from 'chart.js'
import {
    segmentColors, isHighContrast, prefersReducedMotion,
    LABEL_OVERLAY_LIGHT, chartFg,
} from './chartTokens.js'

ChartJS.register(BubbleController, PointElement, LinearScale, Tooltip)

/**
 * Inline plugin: draws the vote count directly into each bubble. Essential in
 * HC (grayscale palette weakens color as a differentiator); optional otherwise.
 */
const bubbleLabelsPlugin = {
    id: 'bubbleLabels',
    afterDatasetsDraw(chart, _args, opts) {
        if (!opts.enabled) return
        const { ctx } = chart
        ctx.save()
        ctx.fillStyle    = opts.color || LABEL_OVERLAY_LIGHT
        ctx.font         = `${opts.fontWeight || 700} ${opts.fontSize || 12}px sans-serif`
        ctx.textAlign    = 'center'
        ctx.textBaseline = 'middle'
        chart.data.datasets.forEach((ds, dsIdx) => {
            const meta = chart.getDatasetMeta(dsIdx)
            const value = (opts.values?.[dsIdx]) ?? 0
            if (value === 0) return
            meta.data.forEach(point => {
                ctx.fillText(String(value), point.x, point.y)
            })
        })
        ctx.restore()
    },
}
ChartJS.register(bubbleLabelsPlugin)

const { t } = useI18n()

/**
 * Bubble chart — one bubble per option, X axis = order, Y axis fixed at 1,
 * bubble size = vote count. Suited for distribution displays.
 */
const props = defineProps({
    options: { type: Array,  required: true },
    counts:  { type: Object, default: () => ({}) },
})

const palette = computed(() => segmentColors())
const hcMode  = computed(() => isHighContrast())

const maxCount = computed(() =>
    Math.max(1, ...props.options.map(o => props.counts[o.id] ?? 0)),
)

const chartData = computed(() => ({
    datasets: props.options.map((o, i) => {
        const count = props.counts[o.id] ?? 0
        return {
            label:           o.label,
            data:            [{ x: i + 1, y: 1, r: 6 + (count / maxCount.value) * 28 }],
            backgroundColor: palette.value[i % palette.value.length],
            borderWidth:     0,
        }
    }),
}))

const chartOptions = computed(() => ({
    responsive:          true,
    maintainAspectRatio: false,
    animation:           prefersReducedMotion() ? false : { duration: 250 },
    plugins:             {
        legend:  { position: 'bottom', labels: { boxWidth: 14, color: chartFg(), font: { size: 14, weight: '500' } } },
        tooltip: { callbacks: { label: (ctx) => {
            const opt = props.options[ctx.datasetIndex]
            const n   = props.counts[opt.id] ?? 0
            return `${opt.label}: ${t('results.votesUnit', { n }, n)}`
        }}},
        // In HC: vote count drawn into the bubble — differentiation independent
        // of color (grayscale palette weakens color separation).
        bubbleLabels: {
            enabled: hcMode.value,
            values:  props.options.map(o => props.counts[o.id] ?? 0),
            color:   LABEL_OVERLAY_LIGHT,
            fontSize: 13,
            fontWeight: 700,
        },
    },
    scales: {
        x: { ticks: { display: false }, grid: { display: false }, min: 0, max: props.options.length + 1 },
        y: { ticks: { display: false }, grid: { display: false }, min: 0.5, max: 1.5 },
    },
}))

const ariaSummary = computed(() => {
    const items = props.options.map(o => {
        const n = props.counts[o.id] ?? 0
        return `${o.label}: ${t('results.votesUnit', { n }, n)}`
    }).join(', ')
    return t('results.bubbleSummary', { items })
})
</script>

<style scoped>
.quorum--results {
    block-size: clamp(220px, 35vh, 400px);
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
