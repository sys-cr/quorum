<template>
    <!-- Outline pattern: rounded frame around the real card. Makes the Δ
         display a visual bridge between the two compare sides. -->
    <section class="quorum--compare-delta-frame">
        <div class="quorum--compare-delta-inner">
            <header class="quorum--compare-delta-head">
                <span class="quorum--compare-delta-label" aria-hidden="true">Δ</span>
                <h2 class="quorum--compare-delta-title">{{ t('compare.deltaHeading') }}</h2>
            </header>
            <hr class="quorum--aurora-divider" aria-hidden="true">
            <table class="quorum--compare-delta" :aria-label="t('compare.deltaHeading')">
                <thead>
                    <tr>
                        <th scope="col">{{ t('compare.tableOption') }}</th>
                        <th scope="col">{{ t('compare.tableBefore') }}</th>
                        <th scope="col">{{ t('compare.tableAfter') }}</th>
                        <th scope="col" class="quorum--compare-delta-th-delta">{{ t('compare.tableDelta') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="opt in options" :key="opt.id">
                        <th scope="row" class="quorum--compare-delta-row-label">{{ opt.label }}</th>
                        <td class="quorum--compare-delta-before">
                            <span class="quorum--delta-num">{{ countOf(countsFrom, opt.id) }}</span>
                            <span class="quorum--delta-pct">{{ pctOf(countsFrom, opt.id) }} %</span>
                        </td>
                        <td class="quorum--compare-delta-after">
                            <span class="quorum--delta-num">{{ countOf(countsTo, opt.id) }}</span>
                            <span class="quorum--delta-pct">{{ pctOf(countsTo, opt.id) }} %</span>
                        </td>
                        <td
                            class="quorum--compare-delta-cell"
                            :class="`quorum--delta-${dirOf(opt.id)}`"
                            :aria-label="ariaFor(opt.id)"
                        >
                            <!-- Pill as inline wrapper around icon + number, so it
                                 is exactly as wide as its content and the text can't
                                 overflow even in narrow Δ columns. -->
                            <span class="quorum--compare-delta-pill">
                                <span class="quorum--delta-icon" aria-hidden="true">{{ iconFor(opt.id) }}</span>
                                <span class="quorum--delta-pp">{{ formatPp(deltaOf(opt.id)) }}</span>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Δ table between two rounds of a peer-instruction comparison. Per answer shows:
 * count + percent of the previous round, count + percent of the current round,
 * and the Δ cell with arrow icon + percentage-point difference.
 *
 * Accessibility:
 *   - arrow icon AND number in the Δ cell (never color alone)
 *   - aria-label states the direction verbally
 *   - table has an aria-label from i18n
 *   - sign classes for CSS, reduced to system color keywords under
 *     `forced-colors: active`.
 */
const props = defineProps({
    options:    { type: Array,  required: true },
    countsFrom: { type: Object, required: true },
    countsTo:   { type: Object, required: true },
})

const { t } = useI18n()

const totalFrom = computed(() => sum(props.countsFrom))
const totalTo   = computed(() => sum(props.countsTo))

const sum = (counts) => Object.values(counts ?? {}).reduce((a, n) => a + (Number(n) || 0), 0)
const countOf = (counts, id) => Number(counts?.[id] ?? 0)
const pctOf = (counts, id) => {
    const total = sum(counts)
    if (total <= 0) return 0
    return Math.round((countOf(counts, id) / total) * 100)
}
const deltaOf = (id) => {
    // Percentage-point difference, rounded to whole numbers.
    const before = totalFrom.value > 0 ? (countOf(props.countsFrom, id) / totalFrom.value) * 100 : 0
    const after  = totalTo.value   > 0 ? (countOf(props.countsTo,   id) / totalTo.value)   * 100 : 0
    return Math.round(after - before)
}
const dirOf = (id) => {
    const d = deltaOf(id)
    if (d > 0) return 'up'
    if (d < 0) return 'down'
    return 'flat'
}
const iconFor = (id) => ({ up: '▲', down: '▼', flat: '–' }[dirOf(id)])
const formatPp = (pp) => {
    if (pp > 0) return `+${pp} pp`
    if (pp < 0) return `−${Math.abs(pp)} pp`   // U+2212 true minus for readability
    return '0 pp'
}
const ariaFor = (id) => {
    const d = deltaOf(id)
    if (d > 0) return t('compare.deltaUp',   { pp: d })
    if (d < 0) return t('compare.deltaDown', { pp: Math.abs(d) })
    return t('compare.deltaFlat')
}
</script>

<style scoped lang="scss">
/* Outline wrapper: flat frame around the real card. Spans the Δ display
 * between the two compare sides. */
.quorum--compare-delta-frame {
    /* Container-query anchor: child adjustments react to the actual frame
     * width, not the viewport — more robust with multiple compare sides
     * side by side on desktop. */
    container-type: inline-size;
    container-name: cmpdelta;

    background: var(--quorum-bg);
    border: 1px solid var(--quorum-border);
    padding: 0;
    border-radius: var(--quorum-radius);
    box-shadow: 0 1px 4px color-mix(in srgb, var(--quorum-fg) 12%, transparent);
    /* Clip the frame corners, otherwise content (table with long labels, Δ
     * pill) spills past the rounded edges. `min-inline-size: 0` releases the
     * grid default `min-width: auto` that would grow the container past the
     * column width. */
    overflow: hidden;
    min-inline-size: 0;
}

.quorum--compare-delta-inner {
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    border-radius: var(--quorum-radius);
    padding: var(--quorum-space-md);
    /* Horizontal scroll as a fallback when the container is so narrow the
     * table doesn't fit even with `table-layout: fixed`. */
    overflow-x: auto;
    min-inline-size: 0;
}

/* Compact mode: below 400px container width, shrink padding, Δ marker pill,
 * and cell padding so everything fits in the narrow side card. */
@container cmpdelta (max-width: 400px) {
    .quorum--compare-delta-inner {
        padding: var(--quorum-space-sm);
    }
    .quorum--compare-delta-head .quorum--compare-delta-label {
        inline-size: 1.6rem;
        block-size: 1.6rem;
        font-size: 0.875rem;
    }
    .quorum--compare-delta-head .quorum--compare-delta-title {
        font-size: var(--quorum-text-sm);
    }
    .quorum--aurora-divider {
        block-size: 2px;
        margin: var(--quorum-space-xs) 0 var(--quorum-space-sm);
    }
    .quorum--compare-delta th,
    .quorum--compare-delta td {
        padding: var(--quorum-space-xs) var(--quorum-space-sm);
    }
    .quorum--compare-delta thead th {
        font-size: 0.7rem;
        letter-spacing: 0.02em;
    }
    /* Before/after: stack % below the count, giving the pill more room. */
    .quorum--compare-delta-before .quorum--delta-pct,
    .quorum--compare-delta-after .quorum--delta-pct {
        display: block;
        margin-inline-start: 0;
    }
    .quorum--compare-delta-pill {
        padding: 0.2em 0.55em;
        gap: 0.25em;
        font-size: 0.85rem;
    }
}

/* Header with Δ marker on the left and title. */
.quorum--compare-delta-head {
    display: flex;
    align-items: center;
    gap: var(--quorum-space-sm);
    margin-block-end: var(--quorum-space-xs);

    .quorum--compare-delta-label {
        display: inline-grid;
        place-items: center;
        inline-size: 2rem;
        block-size: 2rem;
        border-radius: 50%;
        font-weight: 700;
        font-size: 1rem;
        color: #fff;
        background: var(--quorum-brand);           /* solid brand color */
        line-height: 1;
    }

    .quorum--compare-delta-title {
        margin: 0;
        font-size: var(--quorum-text-md);
        font-weight: 600;
        color: var(--quorum-fg);
    }
}

/* Aurora divider like the page header. 3px gradient bar between title and
 * table. */
.quorum--aurora-divider {
    border: 0;
    block-size: 3px;
    background: var(--quorum-aurora);
    border-radius: 999px;
    margin: var(--quorum-space-sm) 0 var(--quorum-space-md);
}

/* The data table. Petrol tint in the header, thin row borders, hover highlight.
 *
 * `table-layout: fixed` forces columns into the available container instead of
 * growing to content — prevents overflow in narrow compare sides. Counterpart:
 * `overflow-wrap: anywhere` on the answer labels for long words. */
.quorum--compare-delta {
    inline-size: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    background: transparent;
    color: var(--quorum-fg);

    /* Column distribution: answer 32%, before/after 18% each, Δ pill 32%.
     * The Δ column needs the most room because it holds a pill with border +
     * padding that would otherwise overflow a tight column. */
    thead th:nth-child(1) { inline-size: 32%; }
    thead th:nth-child(2),
    thead th:nth-child(3) { inline-size: 18%; }
    thead th:nth-child(4) { inline-size: 32%; }

    th, td {
        /* Narrow cell padding so columns don't wrap in 400px tables. */
        padding: var(--quorum-space-xs) var(--quorum-space-sm);
        text-align: start;
        border-block-end: 1px solid color-mix(in srgb, var(--quorum-petrol) 12%, var(--quorum-border));
        font-weight: 400;
        overflow-wrap: anywhere;
        vertical-align: middle;
    }
    thead th {
        font-weight: 600;
        /* Subtler header: smaller font, no letter-spacing stretch — otherwise
         * the labels are too wide for the narrow column. */
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0;
        color: color-mix(in srgb, var(--quorum-petrol) 75%, var(--quorum-fg));
        background: color-mix(in srgb, var(--quorum-petrol) 6%, var(--quorum-bg));
        border-block-end: 2px solid color-mix(in srgb, var(--quorum-petrol) 30%, var(--quorum-border));
        padding-block: var(--quorum-space-sm);
    }
    tbody th[scope="row"] {
        font-weight: 500;
    }
    tbody tr {
        transition: background 120ms ease;
    }
    tbody tr:hover {
        background: color-mix(in srgb, var(--quorum-petrol) 5%, transparent);
    }
    tbody tr:last-child th,
    tbody tr:last-child td {
        border-block-end: 0;
    }
}

/* Δ column header gets a light magenta tint — contrast to the petrol header
 * tint, signaling the left-to-right reading direction. */
.quorum--compare-delta-th-delta {
    background: color-mix(in srgb, var(--quorum-magenta) 6%, var(--quorum-bg)) !important;
    color: color-mix(in srgb, var(--quorum-magenta) 75%, var(--quorum-fg)) !important;
    border-block-end-color: color-mix(in srgb, var(--quorum-magenta) 30%, var(--quorum-border)) !important;
}

.quorum--compare-delta-before,
.quorum--compare-delta-after {
    display: table-cell;
    font-variant-numeric: tabular-nums;

    .quorum--delta-num { font-weight: 600; }
    .quorum--delta-pct {
        margin-inline-start: 0.4em;
        color: var(--quorum-muted);
    }
}

/* Δ cell: accent pill instead of status tokens (status colors are reserved for
 * real status communication, not direction hints).
 *   up   → petrol  (ascending, toward the correct answer)
 *   down → magenta (descending, away from the answer)
 *   flat → muted   (no movement, no emphasis)
 *
 * Pill as an `inline-flex` wrapper around icon + number — the pill box is always
 * exactly as wide as its content, so the text can't overflow. */
.quorum--compare-delta-cell {
    --pill: var(--quorum-acc, var(--quorum-petrol));
    font-variant-numeric: tabular-nums;
    font-weight: 600;

    /* Right-align cell content — the pill should dock at the column's right
     * edge, not float in the middle. */
    text-align: end;

    &.quorum--delta-up   { --pill: var(--quorum-petrol); }
    &.quorum--delta-down { --pill: var(--quorum-magenta); }
    &.quorum--delta-flat {
        --pill: var(--quorum-muted);
        font-weight: 500;
    }

    /* When the column is too narrow the pill may wrap instead of overflow. */
}

/* The pill itself — bubble pattern: tinted background via `color-mix` + colored
 * border, rounded. `inline-flex` makes the pill width depend on its content.
 *
 * `box-sizing: border-box` + `max-inline-size: 100%` keep the pill from ever
 * exceeding its table column. */
.quorum--compare-delta-pill {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3em;
    padding: 0.2em 0.6em;
    border-radius: 999px;
    background: color-mix(in srgb, var(--pill) 14%, transparent);
    border: 1px solid color-mix(in srgb, var(--pill) 40%, transparent);
    color: var(--pill);
    white-space: nowrap;
    line-height: 1.2;
    /* Hard limit against column overflow: the pill must never exceed the Δ
     * column. `min-inline-size: 0` releases the flex default. */
    max-inline-size: 100%;
    min-inline-size: 0;
    font-size: 0.875rem;
    font-variant-numeric: tabular-nums;
}

.quorum--compare-delta-pill .quorum--delta-icon {
    display: inline-block;
    line-height: 1;
    flex: 0 0 auto;
}

.quorum--compare-delta-pill .quorum--delta-pp {
    /* On extremely narrow columns allow clipping instead of overflowing the
     * pill; the number is truncated only on absurdly narrow columns (< 60px). */
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Mobile: on very narrow container widths, set the before/after columns more
 * compact to avoid horizontal overflow. */
@media (max-width: 380px) {
    .quorum--compare-delta th,
    .quorum--compare-delta td {
        padding: var(--quorum-space-xs) var(--quorum-space-sm);
    }
    .quorum--compare-delta-before .quorum--delta-pct,
    .quorum--compare-delta-after .quorum--delta-pct {
        display: block;
        margin-inline-start: 0;
    }
}

/* Dark mode: darker inner card, subtler petrol tint. */
@media (prefers-color-scheme: dark) {
    .quorum--compare-delta thead th {
        background: color-mix(in srgb, var(--quorum-petrol) 12%, var(--quorum-bg));
    }
    .quorum--compare-delta-th-delta {
        background: color-mix(in srgb, var(--quorum-magenta) 12%, var(--quorum-bg)) !important;
    }
}
:global(.theme-dark) {
    .quorum--compare-delta thead th {
        background: color-mix(in srgb, var(--quorum-petrol) 12%, var(--quorum-bg));
    }
    .quorum--compare-delta-th-delta {
        background: color-mix(in srgb, var(--quorum-magenta) 12%, var(--quorum-bg)) !important;
    }
}

/* High-contrast: plain black on white. The outline becomes a simple box and
 * the pill background disappears — arrow icon + number carry the information. */
@media (prefers-contrast: more) {
    .quorum--compare-delta-frame {
        background: var(--quorum-bg);
        border: 2px solid var(--quorum-fg);
        box-shadow: none;
    }
    .quorum--compare-delta-head .quorum--compare-delta-label {
        background: var(--quorum-fg);
    }
    .quorum--aurora-divider {
        background: var(--quorum-fg);
        block-size: 2px;
    }
    .quorum--compare-delta th,
    .quorum--compare-delta td { border-block-end-width: 2px; }
    /* In HC the pills become plain boxes without a colored tint — arrow +
     * number carry the information. */
    .quorum--compare-delta-pill {
        background: transparent;
        border-color: var(--quorum-fg);
        color: var(--quorum-fg);
    }
}

@media (forced-colors: active) {
    .quorum--compare-delta-frame {
        background: Canvas;
        border: 2px solid CanvasText;
        box-shadow: none;
    }
    .quorum--compare-delta-head .quorum--compare-delta-label {
        background: CanvasText;
        color: Canvas;
    }
    .quorum--aurora-divider { background: CanvasText; }
    .quorum--compare-delta-pill {
        background: Canvas;
        border-color: CanvasText;
        color: CanvasText;
        forced-color-adjust: none;
    }
}
</style>
