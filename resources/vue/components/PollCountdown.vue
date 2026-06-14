<template>
    <!--
      Live region: aria-live="polite" announces remaining time without
      interrupting other announcements. Status is never color-only — the
      remaining time is always present as text + icon (⏱ / ⏰).
    -->
    <div
        v-if="hasLimit"
        class="quorum-countdown"
        :class="{ 'is-expired': isExpired, 'is-urgent': isUrgent && !isExpired }"
        role="timer"
        aria-live="polite"
        aria-atomic="true"
        :aria-label="ariaLabel"
    >
        <span class="quorum-countdown-icon" aria-hidden="true">{{ isExpired ? '⏰' : '⏱' }}</span>
        <span class="quorum-countdown-text">{{ displayText }}</span>
    </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Poll timer countdown.
 *
 * Authoritative time is the SERVER clock, not the (possibly wrong) client
 * clock. The backend delivers `remaining_seconds` relative to the server clock
 * at response time. On mount we capture a monotonic reference
 * (`performance.now()`) and count down from it:
 *
 *     deadlineMono = performance.now()/1000 + remainingSeconds
 *     remaining    = deadlineMono − performance.now()/1000
 *
 * `performance.now()` is monotonic and immune to system-clock jumps (NTP
 * corrections, manual time changes), hence more robust than `Date.now()`.
 * Sub-second network latency between response and mount is negligible at
 * one-second resolution.
 *
 * For absolute math pass `expiresAt` + `serverNow` instead:
 *     offset    = serverNow − Date.now()/1000     (server minus client)
 *     remaining = expiresAt − (Date.now()/1000 + offset)
 */
const props = defineProps({
    // Server-provided remaining seconds relative to the server clock. null = no limit.
    remainingSeconds: { type: Number, default: null },
    // Optional, for absolute math (Unix seconds).
    expiresAt:        { type: Number, default: null },
    serverNow:        { type: Number, default: null },
    // Below this remaining time (seconds) the countdown counts as "urgent".
    urgentThreshold:  { type: Number, default: 30 },
})

const emit = defineEmits(['expired'])

const { t } = useI18n()

const nowMono = () =>
    (typeof performance !== 'undefined' && typeof performance.now === 'function')
        ? performance.now() / 1000
        : Date.now() / 1000

// Initial remaining time: prefer remainingSeconds, else compute absolutely.
const initialRemaining = () => {
    if (props.remainingSeconds !== null && props.remainingSeconds !== undefined) {
        return props.remainingSeconds
    }
    if (props.expiresAt !== null && props.serverNow !== null) {
        const offset = props.serverNow - Date.now() / 1000
        return props.expiresAt - (Date.now() / 1000 + offset)
    }
    return null
}

// Monotonic deadline + remaining are set at setup time so the first
// (synchronous) render is already correct and does not flash "expired" for a
// frame.
const _start = initialRemaining()
const hasLimit = _start !== null
const deadlineMono = ref(hasLimit ? nowMono() + _start : null)
const remaining    = ref(hasLimit ? _start : 0)
let timer = null

const isExpired = computed(() => remaining.value <= 0)
const isUrgent  = computed(() => remaining.value <= props.urgentThreshold)

// mm:ss format of the remaining time (clamped to 0).
const formatted = computed(() => {
    const total = Math.max(0, Math.ceil(remaining.value))
    const m = Math.floor(total / 60)
    const s = total % 60
    return `${m}:${String(s).padStart(2, '0')}`
})

const displayText = computed(() =>
    isExpired.value ? t('countdown.expired') : t('countdown.remaining', { time: formatted.value }),
)

// Screen-reader label — announce per-second and pluralized when time is short.
const ariaLabel = computed(() => {
    if (isExpired.value) return t('countdown.expired')
    const total = Math.max(0, Math.ceil(remaining.value))
    if (total < 60) {
        return t('countdown.remainingSeconds', total, { named: { n: total } })
    }
    return `${t('countdown.label')}: ${t('countdown.remaining', { time: formatted.value })}`
})

const tick = () => {
    if (deadlineMono.value === null) return
    remaining.value = deadlineMono.value - nowMono()
    if (remaining.value <= 0) {
        remaining.value = 0
        stop()
        emit('expired')
    }
}

const stop = () => {
    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
}

onMounted(() => {
    if (!hasLimit) return        // No limit → no countdown, no timer.
    if (remaining.value <= 0) {
        // Already expired on mount → report once, no interval.
        remaining.value = 0
        emit('expired')
        return
    }
    timer = setInterval(tick, 1000)
})

onBeforeUnmount(stop)
</script>

<style scoped>
.quorum-countdown {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-block-size: 44px;            /* tappable/visible at 375px */
    padding: 0.4rem 0.75rem;
    border-radius: var(--quorum-radius);
    border: 2px solid var(--quorum-accent, currentColor);
    background: color-mix(in srgb, var(--quorum-accent, currentColor) 8%, var(--quorum-bg, transparent));
    color: var(--quorum-fg, inherit);
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    line-height: 1.2;
}

/* Urgent: text + icon remain in addition to color. */
.quorum-countdown.is-urgent {
    border-color: var(--quorum-warning, var(--quorum-error, currentColor));
    background: color-mix(in srgb, var(--quorum-warning, var(--quorum-error, currentColor)) 14%, var(--quorum-bg, transparent));
}

.quorum-countdown.is-expired {
    border-color: var(--quorum-error, currentColor);
    background: color-mix(in srgb, var(--quorum-error, currentColor) 14%, var(--quorum-bg, transparent));
    color: var(--quorum-fg, inherit);
}

.quorum-countdown-icon {
    font-size: 1.1em;
}

@media (prefers-contrast: more) {
    .quorum-countdown {
        border-width: 3px;
    }
}

@media (forced-colors: active) {
    .quorum-countdown {
        border-color: currentColor;
    }
}
</style>
