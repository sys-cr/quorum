<template>
    <div ref="rootEl" class="quorum--results quorum--results-cloud" :aria-label="ariaSummary">
        <canvas ref="canvasEl" class="quorum--cloud-canvas" :aria-label="ariaSummary"></canvas>
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import WordCloud from 'wordcloud'
import { segmentColors, isHighContrast, prefersReducedMotion } from './chartTokens.js'

/**
 * Word cloud rendered via wordcloud2.js (timdream/wordcloud2.js, MIT). Packs
 * words into oval clusters with random rotation (0° / 90°) and size
 * proportional to frequency.
 *
 * Default visualization for free-text answers; less useful for closed MC
 * questions with 3–5 options, so it's a toggle option, not the default.
 *
 * Aurora accent per word; in high-contrast grayscale (`grayscalePalette`),
 * no rotation (readability), and no hover effect.
 */

const { t } = useI18n()

const props = defineProps({
    options: { type: Array,  required: true },
    counts:  { type: Object, default: () => ({}) },
    /** Min/max font size in pixels — wordcloud2 works in px. */
    minSize: { type: Number, default: 14 },
    maxSize: { type: Number, default: 72 },
    /** Power exponent for the size curve (>1 emphasizes frequent terms more). */
    sizeExponent: { type: Number, default: 1.3 },
})

const canvasEl = ref(null)
const rootEl   = ref(null)

const palette = computed(() => segmentColors(rootEl.value))
const hcMode  = computed(() => isHighContrast())

/**
 * Frequency mapping: per word, size + weight derived from its count.
 *
 *   - **Ratio**: `(count - min) / (max - min)` — normalized to the actual
 *     frequency range. With a single term or all-equal counts → ratio = 1 for
 *     all (same size).
 *   - **Size**: `min + ratio^exponent * (max - min)`. Exponent > 1 (default
 *     1.3) makes the frequency spread more visible — the most frequent term
 *     clearly dominates while rare ones stay visible. Linear (exp = 1) is too flat.
 *   - **Weight**: ratio → 400/500/600/700/900 (5 quartile-based levels) so
 *     frequent words become not just larger but bolder.
 *
 * wordcloud2 expects `[ ['word', weight], ... ]` where `weight` here is the
 * pixel size. The font-weight is supplied separately via the
 * `fontWeight(word, weight)` callback, which wordcloud2 calls per word passing
 * the pixel size as the `weight` argument.
 */
const sizedList = computed(() => {
    const counts = props.options
        .map(o => ({ id: o.id, label: o.label, count: props.counts[o.id] ?? 0 }))
        .filter(x => x.count > 0)
    if (counts.length === 0) return []

    const min   = Math.min(...counts.map(x => x.count))
    const max   = Math.max(...counts.map(x => x.count))
    const range = Math.max(1, max - min)   // all-equal case is handled via allEqual below
    const allEqual = min === max

    return counts.map(({ label, count }) => {
        const ratio = allEqual ? 1 : (count - min) / range
        const size  = props.minSize + Math.pow(ratio, props.sizeExponent) * (props.maxSize - props.minSize)
        return [label, size]
    })
})

/* Pixel size → font-weight level. Thresholds are quantiles of the configured
   size range for a consistent visual impression (Q1/Q2/Q3/Q4 → 400/500/600/700,
   top → 900). */
const weightForSize = (size) => {
    const range = props.maxSize - props.minSize
    if (range <= 0) return '600'
    const norm = (size - props.minSize) / range    // 0..1
    if (norm < 0.20) return '400'
    if (norm < 0.45) return '500'
    if (norm < 0.70) return '600'
    if (norm < 0.90) return '700'
    return '900'
}

/* Stable color assignment: the order in props.options determines the accent —
   preserved across re-renders even when new counts re-pack the cloud. */
const colorFor = (word) => {
    const idx = props.options.findIndex(o => o.label === word)
    return palette.value[idx % palette.value.length]
}

const render = () => {
    const canvas = canvasEl.value
    if (!canvas || sizedList.value.length === 0) return

    /* Couple canvas size to the parent element; otherwise wordcloud2 uses
       default pixels and the image becomes blurry. */
    const rect = canvas.getBoundingClientRect()
    const dpr  = window.devicePixelRatio || 1
    canvas.width  = Math.max(300, Math.floor(rect.width  * dpr))
    canvas.height = Math.max(200, Math.floor(rect.height * dpr))

    WordCloud(canvas, {
        list:        sizedList.value,
        gridSize:    Math.round(8 * dpr),
        weightFactor: dpr,                              // scales with DPR (sharp on HiDPI)
        fontFamily:  'Lato, "Helvetica Neue", Arial, sans-serif',
        fontWeight:  (_word, weight) => weightForSize(weight),
        color:       (word) => colorFor(word),
        backgroundColor: 'transparent',
        /* Rotation: only 0° or 90° — no diagonals. Keeps the classic word-cloud
           look while preserving readability. HC: 0 (all horizontal). */
        rotateRatio:    hcMode.value ? 0 : 0.35,
        rotationSteps:  2,
        minRotation:   -Math.PI / 2,
        maxRotation:    Math.PI / 2,
        shape:       'circle',
        ellipticity: 0.7,
        shrinkToFit: true,
        drawOutOfBound: false,
        wait:        prefersReducedMotion() ? 0 : 0,    // wordcloud2 animates only when wait > 0
    })
}

/* Debounce resize: a full wordcloud reflow is expensive but resize fires
   rapidly. Couple the re-render to a requestAnimationFrame tick and drop
   intermediate events, so at most one reflow runs per frame. */
let rafId = null
const scheduleRender = () => {
    if (rafId !== null) return
    rafId = requestAnimationFrame(() => {
        rafId = null
        render()
    })
}

onMounted(() => {
    render()
    window.addEventListener('resize', scheduleRender)
})
onBeforeUnmount(() => {
    window.removeEventListener('resize', scheduleRender)
    if (rafId !== null) cancelAnimationFrame(rafId)
})

watch([sizedList, palette, hcMode], render, { deep: true })

const ariaSummary = computed(() => {
    const items = props.options.map(o => {
        const n = props.counts[o.id] ?? 0
        return `${o.label}: ${t('results.mentionsUnit', { n }, n)}`
    }).join(', ')
    return t('results.cloudSummary', { items })
})
</script>

<style scoped>
.quorum--results-cloud {
    block-size: clamp(220px, 35vh, 400px);
    inline-size: 100%;
    color: var(--quorum-fg);
    position: relative;
}

.quorum--cloud-canvas {
    inline-size: 100%;
    block-size: 100%;
    display: block;
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
