<template>
    <form class="quorum--matrix" @submit.prevent="onSubmit">
        <div class="quorum--matrix-inner">
            <h1 class="polls-headline polls-question">{{ question }}</h1>
            <p class="quorum--matrix-hint">{{ $t('matrix.choose') }}</p>
            <div class="quorum--matrix-table" role="group" :aria-label="$t('matrix.choose')">
                <!-- Scale header -->
                <div class="quorum--matrix-scale-header" :style="`--cols: ${scale.length}`">
                    <span class="quorum--matrix-row-spacer" aria-hidden="true" />
                    <span
                        v-for="s in scale"
                        :key="s.id"
                        class="quorum--matrix-scale-label"
                    >{{ s.label }}</span>
                </div>
                <!-- Rows -->
                <div
                    v-for="(row, ri) in rows"
                    :key="row.id"
                    class="quorum--matrix-row"
                    :style="`--cols: ${scale.length}`"
                    role="radiogroup"
                    :aria-label="$t('matrix.rowLabel', { row: ri + 1 }) + ': ' + row.label"
                >
                    <span class="quorum--matrix-row-label">{{ row.label }}</span>
                    <button
                        v-for="(s, si) in scale"
                        :key="s.id"
                        ref="cellEls"
                        type="button"
                        class="quorum--matrix-cell"
                        :class="{ 'is-selected': choices[row.id] === s.id }"
                        role="radio"
                        :aria-checked="choices[row.id] === s.id"
                        :aria-label="row.label + ': ' + s.label"
                        :tabindex="rovingTabindex(row.id, si)"
                        :disabled="disabled"
                        @click="select(row.id, s.id)"
                        @keydown="onCellKeydown($event, ri, si)"
                    >
                        <span class="quorum--matrix-cell-dot" aria-hidden="true" />
                    </button>
                </div>
            </div>
            <p v-if="showError" class="quorum--matrix-error" role="alert">
                {{ $t('matrix.missingRows') }}
            </p>
            <button type="submit" class="button" :disabled="disabled">{{ $t('matrix.submit') }}</button>
        </div>
    </form>
</template>

<script setup>
import { nextTick, reactive, ref } from 'vue'

const props = defineProps({
    question: { type: String, required: true },
    rows:     { type: Array, required: true },
    scale:    { type: Array, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const choices = reactive({})
const showError = ref(false)

// Template ref collects all cells in DOM order (row-major):
// index = ri * scale.length + si.
const cellEls = ref([])

const select = (rowId, scaleId) => {
    if (props.disabled) return
    choices[rowId] = scaleId
}

/**
 * Roving tabindex: per row (radiogroup) only ONE cell is Tab-reachable — the
 * selected one, otherwise the first. Within the row the arrow keys navigate.
 */
const rovingTabindex = (rowId, si) => {
    const selectedIdx = props.scale.findIndex(s => s.id === choices[rowId])
    const activeIdx   = selectedIdx >= 0 ? selectedIdx : 0
    return si === activeIdx ? 0 : -1
}

const focusCell = (ri, si) => {
    nextTick(() => {
        const el = cellEls.value[ri * props.scale.length + si]
        el?.focus()
    })
}

/**
 * Arrow-key handler per WAI-ARIA radio pattern: Left/Up selects the previous
 * option in the row, Right/Down the next (with wrap). Selection follows focus,
 * like native radio buttons.
 */
const onCellKeydown = (event, ri, si) => {
    if (props.disabled) return
    const last = props.scale.length - 1
    let next = null
    switch (event.key) {
        case 'ArrowRight':
        case 'ArrowDown':
            next = si >= last ? 0 : si + 1
            break
        case 'ArrowLeft':
        case 'ArrowUp':
            next = si <= 0 ? last : si - 1
            break
        default:
            return
    }
    event.preventDefault()
    const row = props.rows[ri]
    if (row) select(row.id, props.scale[next].id)
    focusCell(ri, next)
}

function onSubmit() {
    if (props.disabled) return
    const allAnswered = props.rows.every(r => choices[r.id] !== undefined)
    if (!allAnswered) {
        showError.value = true
        return
    }
    showError.value = false
    emit('submit', { choices: { ...choices } })
}
</script>

<style scoped>
.quorum--matrix {
    /* Flat bordered frame. */
    background: var(--quorum-bg);
    border: 1px solid var(--quorum-border);
    padding: 0;
    border-radius: var(--quorum-radius);
    overflow: hidden;
    box-shadow: 0 1px 3px color-mix(in srgb, var(--quorum-fg) 12%, transparent);
    max-width: 640px;
    margin-inline: auto;
}

.quorum--matrix-inner {
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    padding: 1.25rem;
    border-radius: var(--quorum-radius);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.polls-headline {
    font-size: clamp(1.15rem, 1rem + 1.2vw, 1.5rem);
    line-height: 1.3;
    margin: 0;
    hyphens: auto;
    overflow-wrap: break-word;
    text-wrap: balance;
}

.quorum--matrix-hint {
    font-size: 0.875rem;
    color: var(--quorum-muted);
    margin: 0;
}

.quorum--matrix-table {
    overflow-x: auto;
}

.quorum--matrix-scale-header,
.quorum--matrix-row {
    display: grid;
    /* first column = row label, remaining = scale cells */
    grid-template-columns: minmax(8rem, 1fr) repeat(var(--cols), minmax(44px, 1fr));
    gap: 2px;
    align-items: center;
}

.quorum--matrix-scale-header {
    margin-block-end: 4px;
}

.quorum--matrix-row-spacer {
    /* empty cell above the row-label column */
}

.quorum--matrix-scale-label {
    font-size: 0.75rem;
    text-align: center;
    padding-inline: 2px;
    word-break: break-word;
    hyphens: auto;
    color: var(--quorum-muted);
}

.quorum--matrix-row-label {
    font-size: 0.875rem;
    padding-inline-end: 0.5rem;
    overflow-wrap: break-word;
    hyphens: auto;
}

.quorum--matrix-cell {
    min-width: 44px;  /* touch target ≥ 44×44 px */
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
    cursor: pointer;
    transition: border-color 120ms ease, background 120ms ease;
}

.quorum--matrix-cell:hover {
    border-color: var(--quorum-accent);
    background: color-mix(in srgb, var(--quorum-accent) 8%, var(--quorum-bg));
}

.quorum--matrix-cell.is-selected {
    border-color: var(--quorum-accent);
    background: color-mix(in srgb, var(--quorum-accent) 16%, var(--quorum-bg));
}

.quorum--matrix-cell:focus-visible {
    outline: var(--quorum-focus-ring);
    outline-offset: 2px;
}

.quorum--matrix-cell-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid var(--quorum-border);
    background: transparent;
}

.quorum--matrix-cell.is-selected .quorum--matrix-cell-dot {
    background: var(--quorum-accent);
    border-color: var(--quorum-accent);
}

.quorum--matrix-error {
    font-size: 0.875rem;
    color: var(--quorum-error);
    font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
    .quorum--matrix-cell {
        transition: none;
    }
}

@media (prefers-contrast: more) {
    .quorum--matrix-cell {
        border-width: 3px;
    }
    .quorum--matrix-cell.is-selected {
        border-width: 4px;
    }
}

@media (forced-colors: active) {
    .quorum--matrix-cell {
        border-color: ButtonText;
    }
    .quorum--matrix-cell.is-selected {
        background: Highlight;
        border-color: Highlight;
    }
    .quorum--matrix-cell.is-selected .quorum--matrix-cell-dot {
        background: HighlightText;
        border-color: HighlightText;
    }
}
</style>
