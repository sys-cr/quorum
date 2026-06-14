<template>
    <main class="quorum-presenter" :class="{ 'is-fullscreen': isFullscreen }">
        <header class="quorum-presenter__bar">
            <h1 class="quorum-presenter__title">{{ store.collection?.name }}</h1>
            <p class="quorum-presenter__position" v-if="store.total > 1">
                {{ store.positionLabel }}
            </p>
        </header>

        <p v-if="store.lastError" class="quorum-presenter__error" role="alert">
            {{ t('presenter.toggleError') }}
        </p>

        <PresenterLeaderboard
            v-if="store.currentPoll && leaderboardOpen && isQuizPoll"
            :token="store.currentPoll.token"
        />
        <PresenterStage v-else-if="store.currentPoll" :poll="store.currentPoll" />
        <p v-else class="quorum-presenter__empty">{{ t('presenter.empty') }}</p>

        <PresenterControls
            :running="store.isCurrentRunning"
            :hasPrev="store.hasPrev"
            :hasNext="store.hasNext"
            :isFullscreen="isFullscreen"
            :canShowQr="canShowQr"
            :canShowLeaderboard="isQuizPoll"
            :leaderboardOpen="leaderboardOpen"
            @prev="store.prev"
            @next="store.next"
            @advance="store.advanceToNext"
            @toggle="store.toggleCurrentVoting"
            @fullscreen="toggleFullscreen"
            @leave="store.leave"
            @qr="openQr"
            @leaderboard="leaderboardOpen = !leaderboardOpen"
        />

        <p class="quorum-presenter__shortcuts" aria-hidden="true">
            <kbd>←</kbd> <kbd>→</kbd> {{ t('presenter.shortcutNav') }} ·
            <kbd>{{ t('presenter.kbdSpace') }}</kbd> {{ t('presenter.shortcutToggle') }} ·
            <kbd>N</kbd> {{ t('presenter.shortcutAdvance') }} ·
            <kbd>L</kbd> {{ t('presenter.shortcutLeaderboard') }} ·
            <kbd>F</kbd> {{ t('presenter.shortcutFullscreen') }} ·
            <kbd>Q</kbd> {{ t('presenter.shortcutQr') }} ·
            <kbd>Esc</kbd> {{ t('presenter.shortcutLeave') }}
        </p>

        <QrCodeDialog
            v-if="qrOpen && store.currentPoll?.join_url"
            :poll-title="store.currentPoll.question"
            :poll-url="store.currentPoll.join_url"
            @close="qrOpen = false"
        />
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePresenterStore } from '../stores/usePresenterStore.js'
import PresenterStage    from './PresenterStage.vue'
import PresenterControls from './PresenterControls.vue'
import PresenterLeaderboard from './PresenterLeaderboard.vue'
import QrCodeDialog      from '@/components/QrCodeDialog.vue'

/**
 * Root of presenter mode.
 *
 * Responsibilities:
 *   - Hydration from the mount element (`data-*` + JSON script)
 *   - Fullscreen API (`document.requestFullscreen()` with Webkit fallback)
 *   - Keyboard shortcuts: ←/→ navigation, Space toggle, `F` fullscreen,
 *     Esc to leave fullscreen or quit the presenter
 *   - Full-page theming via the `quorum-presenter` class, independent of the
 *     browser theme
 *
 * Live counts run per stage component (`PresenterStage` opens a stream for the
 * active poll id); on switching to the next question the stream is closed and
 * reopened for the new poll.
 */

const { t } = useI18n()
const store = usePresenterStore()
const isFullscreen = ref(false)

// Auto-hide the transient toggle error after a short time (projector view).
let errorTimer = null
watch(() => store.lastError, (err) => {
    if (errorTimer) { clearTimeout(errorTimer); errorTimer = null }
    if (err) {
        errorTimer = setTimeout(() => { store.lastError = null }, 4000)
    }
})
const qrOpen = ref(false)
// Leaderboard stage (key L / button) — only for quiz questions.
const leaderboardOpen = ref(false)
const isQuizPoll = computed(() => !!store.currentPoll?.quiz_mode)

// Offer QR only when the current poll has a join_url target.
const canShowQr = computed(() => !!store.currentPoll?.join_url)
const openQr = () => { if (canShowQr.value) qrOpen.value = true }

onMounted(() => {
    const root = typeof document !== 'undefined'
        ? document.getElementById('quorum-presenter-app')
        : null
    store.hydrateFromMount(root)
    document.addEventListener('keydown', handleKeydown)
    document.addEventListener('fullscreenchange', updateFullscreenState)
    document.addEventListener('webkitfullscreenchange', updateFullscreenState)
})
onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)
    document.removeEventListener('fullscreenchange', updateFullscreenState)
    document.removeEventListener('webkitfullscreenchange', updateFullscreenState)
})

const handleKeydown = (e) => {
    // Ignore input fields — the presenter has none, but the defensive check
    // is cheap.
    const t = e.target
    if (t instanceof HTMLInputElement || t instanceof HTMLTextAreaElement) return
    // Do NOT intercept Space on a focused button/link — there Space should
    // activate the focused element (native semantics), not the global voting
    // toggle.
    if (e.key === ' ' && (t instanceof HTMLButtonElement || t instanceof HTMLAnchorElement)) return

    if (e.key === 'ArrowRight') { e.preventDefault(); store.next() }
    else if (e.key === 'ArrowLeft')  { e.preventDefault(); store.prev() }
    else if (e.key === ' ')          { e.preventDefault(); store.toggleCurrentVoting() }
    else if (e.key === 'n' || e.key === 'N') { e.preventDefault(); store.advanceToNext() }
    else if (e.key === 'l' || e.key === 'L') {
        e.preventDefault()
        if (isQuizPoll.value) leaderboardOpen.value = !leaderboardOpen.value
    }
    else if (e.key === 'f' || e.key === 'F') { e.preventDefault(); toggleFullscreen() }
    else if (e.key === 'q' || e.key === 'Q') {
        e.preventDefault()
        if (canShowQr.value) qrOpen.value = !qrOpen.value
    }
    else if (e.key === 'Escape') {
        // QR dialog takes precedence: close the dialog first, otherwise leave
        // the presenter. (The dialog also self-closes via its own
        // @keydown.esc — here we mirror that into root state.)
        if (qrOpen.value) { qrOpen.value = false }
        // In fullscreen the browser exits FS automatically on Esc. Otherwise
        // treat Esc as "leave presenter".
        else if (!isFullscreen.value) store.leave()
    }
}

const updateFullscreenState = () => {
    isFullscreen.value = !!(document.fullscreenElement || document.webkitFullscreenElement)
}

const toggleFullscreen = async () => {
    try {
        if (isFullscreen.value) {
            const exit = document.exitFullscreen?.bind(document) || document.webkitExitFullscreen?.bind(document)
            if (exit) await exit()
        } else {
            const el  = document.documentElement
            const req = el.requestFullscreen?.bind(el) || el.webkitRequestFullscreen?.bind(el)
            if (req) await req()
        }
    } catch {
        // No crash if the browser blocks fullscreen — the button stays
        // visible and the user can retry.
    }
}
</script>

<style lang="scss">
/* Theming on the body because the presenter is a full page (no PageLayout
   frame). Background/text come from the design tokens (`_studip-tokens.scss`),
   so the presenter inherits the same accessibility as the other apps instead
   of a hardcoded theme. `100dvh` (dynamic viewport) renders correctly on iOS
   Safari with the address bar shown; `100vh` remains as fallback. */
body.quorum-presenter-body {
    margin: 0;
    background: var(--quorum-bg, #ffffff);
    color: var(--quorum-fg, #28497c);
    font-family: 'Lato', 'Helvetica Neue', Arial, sans-serif;
    min-block-size: 100vh;
    min-block-size: 100dvh;
}
.quorum-presenter {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto auto;
    min-block-size: 100vh;
    min-block-size: 100dvh;
    /* Safety net against horizontal scrolling — the min-width chain sits in
       stage/chart; this catches outliers (e.g. a lazily loaded chart canvas
       before its first resize). */
    max-inline-size: 100vw;
    overflow-x: clip;
    padding: 1.5rem 2rem;
    gap: 1rem;
    /* Box-sizing so padding does not cause overflow */
    box-sizing: border-box;
}
.quorum-presenter__bar {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;          /* when tight: position drops to a second line */
    border-block-end: 3px solid;
    border-image: linear-gradient(90deg,
        var(--quorum-petrol),
        var(--quorum-green),
        var(--quorum-magenta),
        var(--quorum-brand),
        var(--quorum-dark-violet)
    ) 1;
    padding-block-end: 0.6rem;
    min-inline-size: 0;        /* allows shrinking for ellipsis */
}
.quorum-presenter__title {
    margin: 0;
    font-size: clamp(1rem, 1vw + 0.8rem, 1.6rem);
    font-weight: 700;
    color: inherit;
    /* Title ellipsis instead of wrapping or overflowing */
    flex: 1 1 auto;
    min-inline-size: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.quorum-presenter__position {
    margin: 0;
    font-size: clamp(0.85rem, 0.5vw + 0.7rem, 1.1rem);
    opacity: 0.7;
    color: inherit;
    flex: 0 0 auto;
}
.quorum-presenter__error {
    margin: 0;
    padding: var(--quorum-space-xs, 0.4rem) var(--quorum-space-md, 1rem);
    border: 1px solid var(--quorum-error, #d60000);
    border-radius: var(--quorum-radius, 0.25rem);
    background: color-mix(in srgb, var(--quorum-error, #d60000) 12%, var(--quorum-bg, #fff));
    color: var(--quorum-error, #d60000);
    text-align: center;
    font-weight: 600;
}
.quorum-presenter__empty {
    align-self: center;
    justify-self: center;
    font-size: clamp(1rem, 1vw + 0.7rem, 1.4rem);
    opacity: 0.7;
    text-align: center;
    padding-inline: 1rem;
}
.quorum-presenter__shortcuts {
    margin: 0;
    text-align: center;
    font-size: 0.85rem;
    opacity: 0.5;
    kbd {
        display: inline-block;
        padding: 0.1em 0.4em;
        margin: 0 0.1em;
        font-family: ui-monospace, Menlo, Consolas, monospace;
        /* Token-tinted: dark tint on light theme, light tint in dark mode —
           adapts automatically. */
        background: color-mix(in srgb, var(--quorum-fg, #28497c) 10%, transparent);
        border-radius: 4px;
    }
}

/* Tablet + phone landscape: drop the keyboard hint (touch devices have no
   keyboard, so it is noise). */
@media (max-width: 768px) {
    .quorum-presenter__shortcuts { display: none; }
}

/* Phone portrait: tighter padding, but everything stays present and operable
   (emergency control via phone). */
@media (max-width: 480px) {
    .quorum-presenter { padding: 0.75rem 0.9rem; gap: 0.6rem; }
    .quorum-presenter__bar { padding-block-end: 0.4rem; gap: 0.4rem 0.75rem; }
}

/* Landscape with small height (e.g. rotated phone): reduce padding so the
   chart area can still breathe. */
@media (orientation: landscape) and (max-height: 480px) {
    .quorum-presenter { padding: 0.5rem 0.9rem; gap: 0.4rem; }
}

@media (prefers-reduced-motion: reduce) {
    .quorum-presenter * { transition: none !important; animation: none !important; }
}
</style>
