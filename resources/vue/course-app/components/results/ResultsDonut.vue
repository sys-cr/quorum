<template>
    <div ref="rootEl" class="quorum--results quorum--results-donut">
        <Doughnut :data="chartData" :options="chartOptions" :plugins="localPlugins" :aria-label="ariaSummary" />
        <table class="quorum--visually-hidden">
            <caption>{{ ariaSummary }}</caption>
            <thead>
                <tr><th scope="col">{{ t('results.tableOption') }}</th><th scope="col">{{ t('results.tableVotes') }}</th></tr>
            </thead>
            <tbody>
                <tr v-for="opt in options" :key="opt.id">
                    <td>{{ opt.label }}{{ opt.correct ? t('results.correctSuffix') : '' }}</td>
                    <td>{{ counts[opt.id] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Doughnut } from 'vue-chartjs'
import {
    Chart as ChartJS,
    DoughnutController, ArcElement, Tooltip, Legend,
} from 'chart.js'
import {
    segmentColors, isHighContrast, prefersReducedMotion,
    LABEL_OVERLAY_LIGHT, LABEL_OVERLAY_DARK, chartFg,
} from './chartTokens.js'

ChartJS.register(DoughnutController, ArcElement, Tooltip, Legend)

/**
 * Inline plugin: draws the percentage into each segment. In HC this is the only
 * reliable way to distinguish segments, so it's always active there. Optional
 * otherwise.
 */
const segmentLabelsPlugin = {
    id: 'segmentLabels',
    afterDatasetsDraw(chart, _args, opts) {
        if (!opts.enabled) return
        const { ctx } = chart
        const meta   = chart.getDatasetMeta(0)
        const data   = chart.data.datasets[0]?.data ?? []
        const total  = data.reduce((a, b) => a + b, 0) || 1
        ctx.save()
        ctx.fillStyle    = opts.color || LABEL_OVERLAY_DARK
        ctx.font         = `${opts.fontWeight || 700} ${opts.fontSize || 13}px sans-serif`
        ctx.textAlign    = 'center'
        ctx.textBaseline = 'middle'
        meta.data.forEach((arc, i) => {
            const value = data[i] || 0
            if (value === 0) return
            const pct = Math.round((value / total) * 100)
            const { x, y } = arc.tooltipPosition()
            ctx.fillText(`${pct} %`, x, y)
        })
        ctx.restore()
    },
}
ChartJS.register(segmentLabelsPlugin)

/**
 * Inline plugin: in addition to the pull-out, draws a ✓ into the correct
 * answer's segment. Reinforces dual coding (shape + symbol): never mark by
 * position or color alone. Runs in all modes; in HC the ✓ sits above the
 * percentage label.
 *
 * Opts:
 *   - `correct`: boolean array (parallel to dataset.data); true = draw ✓
 *   - `pctEnabled`: when true (HC), the ✓ shifts up slightly so it doesn't
 *     overlap the already-drawn percentage
 */
/* IMPORTANT: do NOT ChartJS.register(correctMarkPlugin). The plugin is bound
   chart-locally via `:plugins="[correctMarkPlugin]"` on the <Doughnut> tag,
   because Chart.js v4 doesn't reliably pass per-chart options to globally
   registered plugins without a `defaults` block — chart-local binding is more
   robust here. */
const correctMarkPlugin = {
    id: 'correctMark',
    afterDatasetsDraw(chart, _args, opts) {
        if (!opts?.correct?.some?.(Boolean)) return
        const { ctx } = chart
        const meta    = chart.getDatasetMeta(0)
        const size    = opts.fontSize   || 28
        const weight  = opts.fontWeight || 900
        const fill    = opts.color      || LABEL_OVERLAY_LIGHT
        const stroke  = opts.stroke     || LABEL_OVERLAY_DARK
        ctx.save()
        ctx.font         = `${weight} ${size}px "Lato", "Helvetica Neue", Arial, sans-serif`
        ctx.textAlign    = 'center'
        ctx.textBaseline = 'middle'
        meta.data.forEach((arc, i) => {
            if (!opts.correct[i]) return
            // tooltipPosition is centered on the arc — i.e. inside the slice for
            // a pulled-out segment; for very small segments it shifts inward, which is fine.
            const pos = typeof arc.tooltipPosition === 'function'
                ? arc.tooltipPosition()
                : { x: arc.x ?? 0, y: arc.y ?? 0 }
            const x        = pos.x
            const y        = pos.y + (opts.pctEnabled ? -16 : 0)   // HC: above the percentage label
            // Thin dark halo makes the ✓ visible on any segment background
            ctx.lineWidth   = 3
            ctx.strokeStyle = stroke
            ctx.strokeText('✓', x, y)
            ctx.fillStyle   = fill
            ctx.fillText('✓', x, y)
        })
        ctx.restore()
    },
}

const { t } = useI18n()

const props = defineProps({
    options: { type: Array,  required: true },
    counts:  { type: Object, default: () => ({}) },
})

const rootEl  = ref(null)
const palette = computed(() => segmentColors(rootEl.value))
const hcMode  = computed(() => isHighContrast())

// Chart-local plugin list (instead of a global ChartJS.register call)
const localPlugins = [correctMarkPlugin]

const chartData = computed(() => ({
    labels:   props.options.map(o => o.label + (o.correct ? ' ✓' : '')),
    datasets: [{
        data:            props.options.map(o => props.counts[o.id] ?? 0),
        backgroundColor: props.options.map((o, i) => palette.value[i % palette.value.length]),
        borderColor:     'transparent',
        borderWidth:     0,
        // The correct answer is highlighted by pulling it out instead of a
        // border: `offset` pushes the segment radially outward, `weight` makes
        // it proportionally a bit larger.
        offset:          props.options.map(o => o.correct ? 18 : 0),
        weight:          props.options.map(o => o.correct ? 1.18 : 1),
        // Slight border radius on the segment edges makes the pulled-out slice
        // look more organic (the donut stays closed otherwise).
        borderRadius:    props.options.map(o => o.correct ? 6 : 0),
    }],
}))

const chartOptions = computed(() => ({
    responsive:          true,
    maintainAspectRatio: false,
    cutout:              '55%',
    animation:           prefersReducedMotion() ? false : { duration: 250 },
    plugins:             {
        // Legend labels follow the theme foreground.
        legend:        { position: 'bottom', labels: { boxWidth: 14, color: chartFg(), font: { size: 14, weight: '500' } } },
        // Datalabels always in HC, not otherwise (tooltips suffice).
        segmentLabels: { enabled: hcMode.value, color: LABEL_OVERLAY_LIGHT, fontSize: 14, fontWeight: 700 },
        // ✓ on the correct answer — always active when marked.
        correctMark:   {
            correct:    props.options.map(o => !!o.correct),
            pctEnabled: hcMode.value,
            color:      LABEL_OVERLAY_LIGHT,
            fontSize:   24,
            fontWeight: 800,
        },
    },
}))

const ariaSummary = computed(() => {
    const items = props.options.map(o => {
        const n = props.counts[o.id] ?? 0
        const correct = o.correct ? t('results.correctSuffixShort') : ''
        return `${o.label}${correct}: ${t('results.votesUnit', { n }, n)}`
    }).join(', ')
    return t('results.donutSummary', { items })
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
