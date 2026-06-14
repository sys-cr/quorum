<template>
    <section class="quorum--heatmap">
        <p v-if="isEmpty" class="quorum--heatmap-empty">{{ $t('matrixHeatmap.empty') }}</p>
        <div v-else class="quorum--heatmap-scroll">
            <table class="quorum--heatmap-table">
                <caption class="visually-hidden">{{ $t('matrixHeatmap.caption', { question }) }}</caption>
                <thead>
                    <tr>
                        <th scope="col" class="quorum--heatmap-th quorum--heatmap-row-th">
                            {{ $t('matrixHeatmap.rowHeader') }}
                        </th>
                        <th
                            v-for="s in scale"
                            :key="s.id"
                            scope="col"
                            class="quorum--heatmap-th"
                        >{{ s.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id">
                        <th scope="row" class="quorum--heatmap-row-label">{{ row.label }}</th>
                        <td
                            v-for="s in scale"
                            :key="s.id"
                            class="quorum--heatmap-cell"
                            :style="`--heat: ${heatRatio(row.id, s.id)}`"
                            :aria-label="`${row.label} / ${s.label}: ${countOf(row.id, s.id)} (${pctOf(row.id, s.id)}%)`"
                        >
                            <span class="quorum--heatmap-count">{{ countOf(row.id, s.id) }}</span>
                            <span class="quorum--heatmap-pct">{{ pctOf(row.id, s.id) }}%</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    question: { type: String, required: true },
    rows:     { type: Array, required: true },
    scale:    { type: Array, required: true },
    // counts: { [rowId]: { [scaleId]: number } }
    counts:   { type: Object, default: () => ({}) },
})

const isEmpty = computed(() => {
    return !Object.values(props.counts).some(row => Object.values(row).some(v => v > 0))
})

function countOf(rowId, scaleId) {
    return props.counts[rowId]?.[scaleId] ?? 0
}

function rowTotal(rowId) {
    const row = props.counts[rowId] ?? {}
    return Object.values(row).reduce((s, v) => s + v, 0)
}

function pctOf(rowId, scaleId) {
    const total = rowTotal(rowId)
    if (total === 0) return 0
    return Math.round((countOf(rowId, scaleId) / total) * 100)
}

const globalMax = computed(() => {
    let max = 1
    for (const rowCounts of Object.values(props.counts)) {
        for (const v of Object.values(rowCounts)) {
            if (v > max) max = v
        }
    }
    return max
})

function heatRatio(rowId, scaleId) {
    return countOf(rowId, scaleId) / globalMax.value
}
</script>

<style scoped>
.quorum--heatmap {
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    padding: 1rem;
}

.quorum--heatmap-empty {
    color: var(--quorum-muted);
    font-size: 0.875rem;
}

.quorum--heatmap-scroll {
    overflow-x: auto;
}

.quorum--heatmap-table {
    border-collapse: collapse;
    inline-size: 100%;
    font-size: 0.875rem;
}

.quorum--heatmap-th {
    padding: 0.5rem 0.75rem;
    font-weight: 600;
    text-align: center;
    border-block-end: 2px solid var(--quorum-border);
    white-space: nowrap;
}

.quorum--heatmap-row-th {
    text-align: start;
}

.quorum--heatmap-row-label {
    padding: 0.5rem 0.75rem;
    font-weight: 500;
    text-align: start;
    border-inline-end: 1px solid var(--quorum-border);
    max-inline-size: 14rem;
    word-break: break-word;
    hyphens: auto;
}

.quorum--heatmap-cell {
    padding: 0.4rem 0.5rem;
    text-align: center;
    vertical-align: middle;
    /* Heat colour: petrol at full intensity, transparent at 0 */
    background: color-mix(in srgb, var(--quorum-petrol) calc(var(--heat, 0) * 60%), var(--quorum-bg));
    border: 1px solid var(--quorum-border);
    min-inline-size: 3rem;
}

.quorum--heatmap-count {
    display: block;
    font-weight: 700;
    font-size: 1rem;
    line-height: 1.2;
}

.quorum--heatmap-pct {
    display: block;
    font-size: 0.75rem;
    color: var(--quorum-muted);
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (prefers-contrast: more) {
    .quorum--heatmap-cell {
        border-width: 2px;
    }
    .quorum--heatmap-pct {
        color: var(--quorum-fg);
    }
}

@media (forced-colors: active) {
    .quorum--heatmap-cell {
        background: Canvas;
        border-color: ButtonText;
    }
}
</style>
