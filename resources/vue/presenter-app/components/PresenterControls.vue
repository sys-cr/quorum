<template>
    <nav class="quorum-presenter__controls" :aria-label="t('presenter.controlsAria')">
        <button
            v-if="hasPrev || hasNext"
            type="button"
            class="button quorum-presenter__nav"
            :disabled="!hasPrev"
            :aria-label="t('presenter.prev')"
            @click="$emit('prev')"
        >
            ←
        </button>

        <button
            type="button"
            class="button quorum-presenter__toggle"
            :class="{ 'is-running': running }"
            :aria-label="running ? t('presenter.stop') : t('presenter.start')"
            @click="$emit('toggle')"
        >
            <span class="quorum-presenter__toggle-dot" aria-hidden="true"></span>
            {{ running ? t('presenter.stop') : t('presenter.start') }}
        </button>

        <button
            v-if="hasPrev || hasNext"
            type="button"
            class="button quorum-presenter__nav"
            :disabled="!hasNext"
            :aria-label="t('presenter.next')"
            @click="$emit('next')"
        >
            →
        </button>

        <button
            v-if="hasNext"
            type="button"
            class="button quorum-presenter__advance"
            :aria-label="t('presenter.advance')"
            @click="$emit('advance')"
        >
            {{ t('presenter.advance') }}
        </button>

        <span class="quorum-presenter__controls-spacer"></span>

        <button
            v-if="canShowLeaderboard"
            type="button"
            class="button"
            :aria-pressed="leaderboardOpen ? 'true' : 'false'"
            :class="{ active: leaderboardOpen }"
            :aria-label="t('presenter.leaderboard')"
            @click="$emit('leaderboard')"
        >
            {{ t('presenter.leaderboard') }}
        </button>

        <button
            v-if="canShowQr"
            type="button"
            class="button"
            :aria-label="t('presenter.qrShow')"
            @click="$emit('qr')"
        >
            {{ t('presenter.qrShow') }}
        </button>

        <button
            type="button"
            class="button"
            :aria-label="isFullscreen ? t('presenter.fullscreenExit') : t('presenter.fullscreenEnter')"
            @click="$emit('fullscreen')"
        >
            {{ isFullscreen ? t('presenter.fullscreenExit') : t('presenter.fullscreenEnter') }}
        </button>

        <button
            type="button"
            class="button"
            @click="$emit('leave')"
        >
            {{ t('presenter.leave') }}
        </button>
    </nav>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
    running:      { type: Boolean, default: false },
    hasPrev:      { type: Boolean, default: false },
    hasNext:      { type: Boolean, default: false },
    isFullscreen: { type: Boolean, default: false },
    canShowQr:    { type: Boolean, default: false },
    // Leaderboard toggle, only visible for quiz questions.
    canShowLeaderboard: { type: Boolean, default: false },
    leaderboardOpen:    { type: Boolean, default: false },
})
defineEmits(['prev', 'next', 'advance', 'toggle', 'fullscreen', 'leave', 'qr', 'leaderboard'])

const { t } = useI18n()
</script>

<style scoped lang="scss">
/* Buttons follow the Stud.IP convention (`.button` from the core or the
   standalone partial `_studip-buttons.scss`). Only layout and the few
   presenter specifics live here (square navigation, toggle as the filled
   main action with a status dot). */
.quorum-presenter__controls {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    padding-block: 0.4rem;
    flex-wrap: wrap;
    justify-content: center;
    /* Order stays logical (Prev, Toggle, Next, Spacer, Sec1, Sec2);
       wrapping on mobile breaks after the spacer. */
}

/* Prev/next: square (core counterpart: `button.button.btn-icon--only`
   resets min-width) — 44 px edge length = touch target. */
.quorum-presenter__nav {
    min-inline-size: 44px;
    inline-size: 44px;
    block-size: 44px;
    padding: 0;
    font-size: 1.3rem;
    line-height: 1;
}

/* Voting toggle = main action: filled instead of outlined so the state is
   recognizable on the projector from the back row. The status is communicated
   threefold: color + dot icon + text. */
.quorum-presenter__toggle {
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    background: var(--quorum-petrol, #0e817b);
    border-color: var(--quorum-petrol, #0e817b);
    color: #fff;
}
.quorum-presenter__toggle:hover,
.quorum-presenter__toggle:active {
    background: color-mix(in srgb, var(--quorum-petrol, #0e817b) 85%, #000);
    border-color: color-mix(in srgb, var(--quorum-petrol, #0e817b) 85%, #000);
    color: #fff;
}
.quorum-presenter__toggle.is-running {
    background: var(--quorum-error, #d60000);
    border-color: var(--quorum-error, #d60000);
}
.quorum-presenter__toggle.is-running:hover,
.quorum-presenter__toggle.is-running:active {
    background: color-mix(in srgb, var(--quorum-error, #d60000) 85%, #000);
    border-color: color-mix(in srgb, var(--quorum-error, #d60000) 85%, #000);
}

.quorum-presenter__toggle-dot {
    inline-size: 0.6rem;
    block-size: 0.6rem;
    border-radius: 50%;
    background: currentColor;
    box-shadow: 0 0 0 0 currentColor;
}
.quorum-presenter__toggle.is-running .quorum-presenter__toggle-dot {
    animation: quorum-presenter-pulse 1.4s infinite;
}
@keyframes quorum-presenter-pulse {
    /* Glow in error-red instead of white — visible on the light theme as in
       dark mode (the "voting running" toggle is red; the ring radiates onto
       the page). */
    0%   { box-shadow: 0 0 0 0   color-mix(in srgb, var(--quorum-error, #d60000) 65%, transparent); }
    70%  { box-shadow: 0 0 0 12px color-mix(in srgb, var(--quorum-error, #d60000) 0%, transparent); }
    100% { box-shadow: 0 0 0 0   color-mix(in srgb, var(--quorum-error, #d60000) 0%, transparent); }
}

/* "Start next question" — flow control of the collection: finishes the
   running question and starts the next one in a single click. Outline
   variant in petrol so it reads as a sibling of the main toggle action
   without competing with it. */
.quorum-presenter__advance {
    color: var(--quorum-petrol, #0e817b);
    border-color: var(--quorum-petrol, #0e817b);
    font-weight: 600;
}
.quorum-presenter__advance:hover,
.quorum-presenter__advance:active {
    background: color-mix(in srgb, var(--quorum-petrol, #0e817b) 12%, transparent);
    color: var(--quorum-petrol, #0e817b);
    border-color: var(--quorum-petrol, #0e817b);
}

.quorum-presenter__controls-spacer { flex: 1; }

@media (max-width: 600px) {
    /* Drop the spacer, buttons wrap naturally */
    .quorum-presenter__controls-spacer { display: none; }
    /* Toggle on its own full-width row so the main action is always present
       and not wedged between the arrows. */
    .quorum-presenter__toggle {
        flex-basis: 100%;
        order: 1;            /* render first, before the arrows */
        padding-block: 0.7rem;
        font-size: 1rem;
    }
    .quorum-presenter__nav { order: 2; }
    /* Secondary buttons (QR, fullscreen, leave) share the last row.
       `white-space: normal` overrides the core look's nowrap — otherwise
       "Leave presenter" runs out of the viewport at 375 px. */
    .quorum-presenter__controls > .button:not(.quorum-presenter__toggle):not(.quorum-presenter__nav) {
        order: 3;
        min-inline-size: 0;
        flex: 1 1 auto;
        white-space: normal;
        line-height: 1.2;
    }
}

/* Very small height (rotated phone): reduce spacing, toggle stays the only
   large element. */
@media (orientation: landscape) and (max-height: 480px) {
    .quorum-presenter__controls { gap: 0.35rem; padding-block: 0.2rem; }
    .quorum-presenter__nav { min-inline-size: 38px; inline-size: 38px; block-size: 38px; font-size: 1.1rem; }
}

@media (prefers-reduced-motion: reduce) {
    .quorum-presenter__toggle .quorum-presenter__toggle-dot { animation: none; }
}

@media (prefers-contrast: more), (forced-colors: active) {
    .quorum-presenter__toggle { background: ButtonFace; color: ButtonText; border-color: ButtonText; }
}
</style>
