<template>
    <section class="quorum--compare quorum--container" :aria-label="t('compare.heading')">
        <header class="quorum--compare-header">
            <h1 class="quorum--compare-title">{{ t('compare.heading') }}</h1>
            <!-- Plaintext question; pre-line preserves line breaks. No v-html. -->
            <p v-if="store.root" class="quorum--compare-question">{{ store.root.question }}</p>
            <hr class="quorum--aurora-divider" aria-hidden="true">
        </header>

        <StudipMessageBox v-if="store.isLoading" type="info" :hide-close="true">
            {{ t('compare.loading') }}
        </StudipMessageBox>

        <StudipMessageBox v-else-if="store.hasError" type="error" :hide-close="true">
            <p>{{ t('compare.errorLoading') }}</p>
            <button type="button" class="button" @click="reload">
                {{ t('compare.retry') }}
            </button>
        </StudipMessageBox>

        <div v-else-if="store.isReady && store.rounds.length === 0" class="quorum--hero-empty">
            <p class="quorum--empty-title">{{ t('compare.empty') }}</p>
            <hr class="quorum--aurora-divider" aria-hidden="true">
            <p class="quorum--compare-empty-hint">{{ t('compare.emptyHint') }}</p>
        </div>

        <div v-else-if="store.isReady" class="quorum--compare-grid">
            <template v-for="(poll, idx) in store.allPolls" :key="poll.id">
                <section
                    class="quorum--compare-side"
                    :style="{ '--quorum-acc': accentFor(idx) }"
                >
                    <header class="quorum--compare-side-head">
                        <!-- Round pill, consistent with the Δ pill in CompareDelta. -->
                        <span class="quorum--compare-side-round-marker" aria-hidden="true">
                            {{ idx + 1 }}
                        </span>
                        <span class="quorum--compare-side-round">
                            {{ t('compare.roundLabel', { round: idx + 1 }) }}
                        </span>
                        <!-- Status pill: running uses the status token, ended the
                             side accent. -->
                        <span
                            class="quorum--compare-side-status"
                            :data-status="poll.is_active ? 'running' : 'ended'"
                        >
                            {{ poll.is_active ? t('compare.statusActive') : t('compare.statusEnded') }}
                        </span>
                    </header>

                    <hr class="quorum--aurora-divider quorum--aurora-divider--side" aria-hidden="true">

                    <p class="quorum--compare-side-size">
                        {{ t('compare.sampleSize', store.totals[poll.id] ?? 0, { n: store.totals[poll.id] ?? 0 }) }}
                    </p>

                    <div data-testid="round-results" class="quorum--compare-side-results">
                        <Suspense v-if="(store.totals[poll.id] ?? 0) > 0">
                            <ResultsContainer
                                :options="poll.options"
                                :counts="poll.counts ?? {}"
                                :persistence-key="`quorum.compare.${poll.id}`"
                            />
                        </Suspense>
                        <p v-else class="quorum--compare-side-empty">{{ t('compare.noVotes') }}</p>
                    </div>
                </section>

                <!-- A delta table between each pair of consecutive sides.
                     N polls → N-1 deltas. -->
                <CompareDelta
                    v-if="idx < store.allPolls.length - 1"
                    :options="store.root.options"
                    :counts-from="poll.counts ?? {}"
                    :counts-to="store.allPolls[idx + 1].counts ?? {}"
                />
            </template>
        </div>
    </section>
</template>

<script setup>
import { defineAsyncComponent, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompareChainStore } from '../stores/useCompareChainStore.js'
import CompareDelta from './CompareDelta.vue'

/**
 * Workplace compare full page (peer instruction).
 *
 * Loads the root + all follow-up polls from `GET /api/compare_chain/{rootId}`
 * and renders one side card per round with `ResultsContainer` (lazy-loaded —
 * keeps Chart.js out of the initial bundle). Between the sides a `CompareDelta`
 * table with arrow icon + percentage-point value.
 *
 * Accent rotation: petrol/magenta/brand/dark-violet (same pattern as
 * `WorkplaceIndex`).
 */

// Lazy-load: keeps the Chart.js chunk out of the initial bundle.
const ResultsContainer = defineAsyncComponent(() =>
    import('../../course-app/components/results/ResultsContainer.vue'),
)

const { t } = useI18n()
const store = useCompareChainStore()

const accentColors = [
    'var(--quorum-petrol)',
    'var(--quorum-magenta)',
    'var(--quorum-brand)',
    'var(--quorum-dark-violet)',
]
const accentFor = (i) => accentColors[i % accentColors.length]

const reload = () => {
    const root = document.getElementById('quorum-workplace-app')
    const pluginUrl = root?.dataset.pluginUrl ?? ''
    const rootId    = root?.dataset.rootId ?? ''
    return store.fetch(pluginUrl, rootId)
}

onMounted(() => {
    const root = document.getElementById('quorum-workplace-app')
    const pluginUrl = root?.dataset.pluginUrl ?? ''
    const rootId    = root?.dataset.rootId ?? ''
    if (rootId) store.fetch(pluginUrl, rootId)
})
</script>

<style scoped lang="scss">
.quorum--compare {
    padding-block: var(--quorum-space-md);
    color: var(--quorum-fg);
    background: var(--quorum-bg);
}

.quorum--compare-header {
    margin-block-end: var(--quorum-space-md);

    .quorum--compare-title {
        font-size: var(--quorum-text-xl);
        margin-block-end: var(--quorum-space-sm);
        color: var(--quorum-fg);
    }
}

/* Plaintext question with line breaks. */
.quorum--compare-question {
    white-space: pre-line;
    font-size: var(--quorum-text-md);
    color: var(--quorum-fg);
    margin-block-end: var(--quorum-space-sm);
    /* Long words break instead of bursting the container — `pre-line` keeps
     * manual line breaks, `anywhere` adds soft breaks for overlong words. */
    overflow-wrap: anywhere;
}

.quorum--compare-empty-hint {
    color: var(--quorum-muted);
    font-size: var(--quorum-text-sm);
}

/* Within a side card: thin Aurora divider between header (round marker +
 * status) and body. 2px instead of 3px to keep the overall weight light. */
.quorum--aurora-divider--side {
    block-size: 2px;
    margin: 0;
}

/* Card grid like WorkplaceIndex — `auto-fit minmax(min(280px, 100%), 1fr)`
   stacks on mobile without a hard breakpoint. `> *` gets `min-inline-size: 0`,
   otherwise the grid default `min-width: auto` makes items as wide as their
   content and frames spill past the column width. */
.quorum--compare-grid {
    display: grid;
    gap: var(--quorum-space-md);
    grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr));
    align-items: start;

    > * {
        min-inline-size: 0;
    }
}

.quorum--compare-side {
    /* Container-query anchor: side-card-internal adjustments react to the card
     * width, not the viewport — robust with multiple sides on desktop and a
     * single side on 375px mobile. */
    container-type: inline-size;
    container-name: cmpside;

    --quorum-acc: var(--quorum-petrol);
    border: 1px solid var(--quorum-border);
    border-inline-start: 8px solid var(--quorum-acc);
    padding: var(--quorum-space-md);
    border-radius: var(--quorum-radius);
    background: color-mix(in srgb, var(--quorum-acc) 7%, var(--quorum-bg));
    box-shadow: 0 1px 4px color-mix(in srgb, var(--quorum-acc) 18%, transparent);
    color: var(--quorum-fg);
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
    /* Clip card edges — otherwise the chart canvas spills past the border
     * radius on narrow sides. `min-inline-size: 0` for grid children. */
    overflow: hidden;
    min-inline-size: 0;
}

/* Compact mode: on a very narrow side card everything shrinks slightly so the
 * round marker doesn't burst the header and card content doesn't spill past the
 * border-radius edges. */
@container cmpside (max-width: 400px) {
    .quorum--compare-side {
        padding: var(--quorum-space-sm);
        gap: var(--quorum-space-xs);
    }
    .quorum--compare-side-head {
        gap: var(--quorum-space-xs);
    }
    .quorum--compare-side-head .quorum--compare-side-round-marker {
        inline-size: 1.6rem;
        block-size: 1.6rem;
        font-size: 0.875rem;
    }
    .quorum--compare-side-head .quorum--compare-side-round {
        font-size: var(--quorum-text-sm);
    }
    .quorum--compare-side-head .quorum--compare-side-status {
        padding: 0.2em 0.55em;
        font-size: 0.75rem;
    }
    .quorum--compare-side-results {
        padding: var(--quorum-space-xs);
        min-block-size: 200px;
    }
}

.quorum--compare-side-head {
    display: flex;
    align-items: center;
    gap: var(--quorum-space-sm);
    flex-wrap: wrap;

    /* Round marker pill (like the Δ marker in CompareDelta). */
    .quorum--compare-side-round-marker {
        flex: 0 0 auto;
        display: inline-grid;
        place-items: center;
        inline-size: 2rem;
        block-size: 2rem;
        border-radius: 50%;
        font-weight: 700;
        font-size: 1rem;
        line-height: 1;
        color: #fff;
        background: var(--quorum-brand);           /* solid brand color */
    }

    .quorum--compare-side-round {
        font-weight: 600;
        font-size: var(--quorum-text-md);
        color: var(--quorum-acc);
        flex: 1 1 auto;
        /* `min-inline-size: 0` releases the flex default `auto` that would grow
         * the container past the available width, so long i18n strings wrap cleanly. */
        min-inline-size: 0;
        overflow-wrap: anywhere;
    }

    /* Status pill, like the Δ pills in CompareDelta. Ended = side accent,
     * running = status token (info), since it's real status communication, not
     * decorative like the Δ direction. */
    .quorum--compare-side-status {
        flex: 0 0 auto;
        font-size: var(--quorum-text-sm);
        font-weight: 500;
        padding: 0.25em 0.75em;
        border-radius: 999px;
        background: color-mix(in srgb, var(--quorum-acc) 14%, var(--quorum-bg));
        border: 1px solid color-mix(in srgb, var(--quorum-acc) 40%, transparent);
        color: color-mix(in srgb, var(--quorum-acc) 75%, var(--quorum-fg));

        &[data-status="running"] {
            background: color-mix(in srgb, var(--quorum-info) 18%, var(--quorum-bg));
            border-color: color-mix(in srgb, var(--quorum-info) 55%, transparent);
            color: color-mix(in srgb, var(--quorum-info) 60%, var(--quorum-fg));
        }
    }
}

.quorum--compare-side-size {
    color: var(--quorum-muted);
    font-size: var(--quorum-text-sm);
    font-variant-numeric: tabular-nums;
}

.quorum--compare-side-results {
    background: var(--quorum-bg);
    border-radius: var(--quorum-radius);
    padding: var(--quorum-space-sm);
    min-block-size: 220px;
    /* Charts have an intrinsic minimum width — scroll horizontally on very
     * narrow sides instead of bursting the card. */
    min-inline-size: 0;
    overflow-x: auto;
}

.quorum--compare-side-empty {
    color: var(--quorum-muted);
    text-align: center;
    padding-block: var(--quorum-space-md);
}

/* Hover highlight on the side card — like the hover pattern in CompareDelta.
 * Raises the tint slightly so cards lift on hover. */
.quorum--compare-side:hover {
    background: color-mix(in srgb, var(--quorum-acc) 11%, var(--quorum-bg));
    box-shadow: 0 2px 8px color-mix(in srgb, var(--quorum-acc) 24%, transparent);
}

@media (prefers-contrast: more) {
    .quorum--compare-side { border-width: 2px; }
    .quorum--compare-side-head .quorum--compare-side-round-marker {
        background: var(--quorum-fg);
    }
}

@media (forced-colors: active) {
    .quorum--compare-side {
        background: Canvas;
        border-color: CanvasText;
        box-shadow: none;
    }
    .quorum--compare-side-head .quorum--compare-side-round-marker {
        background: CanvasText;
        color: Canvas;
    }
    .quorum--compare-side-head .quorum--compare-side-status {
        background: Canvas;
        border-color: CanvasText;
        color: CanvasText;
        forced-color-adjust: none;
    }
}
</style>
