<template>
    <section class="quorum-presenter__stage">
        <p class="quorum-presenter__question">{{ poll.question }}</p>

        <div class="quorum-presenter__chart">
            <Suspense>
                <ResultsContainer
                    :options="chartOptions"
                    :counts="liveCounts"
                    :persistence-key="`quorum.presenter.${poll.id}`"
                />
                <template #fallback>
                    <p class="quorum-presenter__loading">{{ t('presenter.loadingChart') }}</p>
                </template>
            </Suspense>
        </div>

        <p class="quorum-presenter__sample">
            {{ t('presenter.responses', total, { n: total }) }}
        </p>
    </section>
</template>

<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Fullscreen stage for a single poll in the presenter.
 *
 * Question as plaintext (`pre-line` for line breaks), below it the chart via
 * `<ResultsContainer>` (lazy via `defineAsyncComponent`, shares the chunk with
 * the course-app via Vite code splitting).
 *
 * Live counts arrive via a locally managed EventSource per `poll.id` — on
 * switching the active poll we close the old stream and open a new one. The
 * course-app composable is deliberately NOT used because it is a global Pinia
 * store and reset/re-subscribe in the presenter would be messy; a slim local
 * implementation is cleaner here.
 */

const props = defineProps({
    poll: { type: Object, required: true },
})

const { t } = useI18n()

// Lazy: chart bundle loads only when the presenter app runs.
const ResultsContainer = defineAsyncComponent(() =>
    import('../../course-app/components/results/ResultsContainer.vue')
)

const liveCounts = ref({ ...(props.poll.initial_counts ?? {}) })
let evtSource = null
let pollTimer = null
let reconnectTimer = null
let watchdogTimer = null
/* First-data watchdog: Stud.IP behind nginx/FPM buffers long-lived responses,
   so the EventSource reports `open` (HTTP 200) but never delivers an event —
   `error` never fires, the fallback does not kick in, the counter stays at 0.
   If no event arrives within WATCHDOG_MS, degrade to polling. */
const WATCHDOG_MS = 4000

/* Reconnect strategy: exponential backoff (1 s → 2 s → 4 s → 8 s → 16 s → 30 s),
   switching permanently to polling only after two consecutive failures — a
   single transient SSE error must not degrade the stream for good. */
const BACKOFF_STEPS_MS = [1000, 2000, 4000, 8000, 16000, 30000]
const MAX_FAILS_BEFORE_FALLBACK = 2
let consecutiveFails = 0

const chartOptions = computed(() =>
    (props.poll.options ?? []).map(o => ({ id: o.id, label: o.label }))
)
const total = computed(() =>
    Object.values(liveCounts.value).reduce((a, b) => a + (Number(b) || 0), 0)
)

const root      = typeof document !== 'undefined'
    ? document.getElementById('quorum-presenter-app')
    : null
const pluginUrl = root?.dataset?.pluginUrl ?? ''

const start = (pollId) => {
    stop()
    consecutiveFails = 0
    if (!pluginUrl) return
    startSse(pollId)
}

const startSse = (pollId) => {
    const url = `${pluginUrl}/api/stream/${encodeURIComponent(pollId)}`
    if (!('EventSource' in window)) {
        startPolling(pollId)
        return
    }
    try {
        evtSource = new EventSource(url, { withCredentials: true })
        const clearWatchdog = () => {
            if (watchdogTimer) { clearTimeout(watchdogTimer); watchdogTimer = null }
        }
        watchdogTimer = setTimeout(() => {
            // Connection open but silent (proxy buffering) → polling.
            stop()
            startPolling(pollId)
        }, WATCHDOG_MS)
        evtSource.addEventListener('counts', (e) => {
            clearWatchdog()
            consecutiveFails = 0
            try { liveCounts.value = JSON.parse(e.data) } catch { /* ignore */ }
        })
        evtSource.addEventListener('heartbeat', () => { clearWatchdog(); consecutiveFails = 0 })
        evtSource.addEventListener('error', () => {
            consecutiveFails += 1
            stop()
            if (consecutiveFails >= MAX_FAILS_BEFORE_FALLBACK) {
                // Switch permanently to polling
                startPolling(pollId)
                return
            }
            const delay = BACKOFF_STEPS_MS[Math.min(consecutiveFails - 1, BACKOFF_STEPS_MS.length - 1)]
            reconnectTimer = setTimeout(() => startSse(pollId), delay)
        })
    } catch {
        startPolling(pollId)
    }
}
const startPolling = (pollId) => {
    const url = `${pluginUrl}/api/stream/${encodeURIComponent(pollId)}`
    const tick = async () => {
        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
            if (res.ok) liveCounts.value = await res.json()
        } catch { /* ignore */ }
    }
    tick()
    pollTimer = setInterval(tick, 3000)
}
const stop = () => {
    if (evtSource) { evtSource.close(); evtSource = null }
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
    if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null }
    if (watchdogTimer) { clearTimeout(watchdogTimer); watchdogTimer = null }
}

watch(() => props.poll.id, (id) => {
    liveCounts.value = {}
    start(id)
}, { immediate: true })

onBeforeUnmount(stop)
</script>

<style scoped lang="scss">
.quorum-presenter__stage {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    gap: 1rem;
    min-block-size: 0;
    /* Grid items default to min-width:auto — without an explicit
       `min-inline-size: 0` the Chart.js canvas (intrinsic width) pushes the
       stage past the viewport and the whole page scrolls horizontally on
       phones. */
    min-inline-size: 0;
    inline-size: 100%;
    /* Reading-width limit for the question; no full-width text block on a
       projector. */
    max-inline-size: min(100%, 1280px);
    margin-inline: auto;
}
.quorum-presenter__question {
    margin: 0;
    font-size: clamp(1.2rem, 1.4vw + 0.7rem, 2.6rem);
    font-weight: 600;
    line-height: 1.25;
    white-space: pre-line;
    color: inherit;
    overflow-wrap: anywhere;
}
.quorum-presenter__chart {
    align-self: stretch;
    min-block-size: 0;
    /* Part of the min-width chain against horizontal overflow (see stage). */
    min-inline-size: 0;
    max-inline-size: 100%;
    /* ResultsContainer has its own wrapper which looks oversized in the
       presenter; override it away so only the chart shows at full height. */
    :deep(.quorum--results-container) {
        background: transparent;
        border: 0;
        box-shadow: none;
        padding: 0;
        /* The flex-column container must adopt the cell height, otherwise the
           chart's `block-size: 100%` below collapses to content height and a
           large empty area remains. */
        block-size: 100%;
    }
    /* Chart container fills the stage cell instead of forcing its clamped
       height. */
    :deep(.quorum--results) {
        /* In the flex-column container the chart grows into the remaining
           space (the toolbar row above keeps its content height). */
        flex: 1 1 auto;
        block-size: auto;
        min-block-size: 200px;
        max-inline-size: 100%;
    }
}
.quorum-presenter__sample {
    margin: 0;
    text-align: end;
    font-size: clamp(0.85rem, 0.5vw + 0.7rem, 1.05rem);
    opacity: 0.7;
}
.quorum-presenter__loading {
    text-align: center;
    opacity: 0.6;
    margin: 2rem;
}

@media (max-width: 480px) {
    .quorum-presenter__stage { gap: 0.6rem; }
    .quorum-presenter__chart :deep(.quorum--results) { min-block-size: 160px; }
}
@media (orientation: landscape) and (max-height: 480px) {
    .quorum-presenter__stage { gap: 0.4rem; }
    .quorum-presenter__question { font-size: clamp(1rem, 1vw + 0.6rem, 1.4rem); }
    .quorum-presenter__chart :deep(.quorum--results) { min-block-size: 120px; }
    .quorum-presenter__sample { display: none; }
}
</style>
