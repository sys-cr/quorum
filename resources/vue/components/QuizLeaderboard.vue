<template>
    <section class="quorum--leaderboard" :aria-label="t('quiz.leaderboardTitle')">
        <h2 class="quorum--leaderboard-title">{{ t('quiz.leaderboardTitle') }}</h2>

        <p v-if="loading" class="quorum--leaderboard-empty" role="status">{{ t('quiz.leaderboardLoading') }}</p>
        <p v-else-if="error" class="quorum--leaderboard-empty" role="alert">{{ t('quiz.leaderboardError') }}</p>
        <p v-else-if="entries.length === 0" class="quorum--leaderboard-empty">{{ t('quiz.leaderboardEmpty') }}</p>

        <ol v-else class="quorum--leaderboard-list">
            <li
                v-for="entry in entries"
                :key="entry.rank + entry.nickname"
                :class="`is-rank-${Math.min(entry.rank, 4)}`"
            >
                <!-- Rank communicated threefold: medal icon (top 3) +
                     number + points text — never color alone (HC requirement). -->
                <span class="quorum--leaderboard-rank" aria-hidden="true">{{ medalFor(entry.rank) }}</span>
                <span class="quorum--leaderboard-place">{{ t('quiz.rank', { rank: entry.rank }) }}</span>
                <span class="quorum--leaderboard-name">{{ entry.nickname }}</span>
                <span class="quorum--leaderboard-score">{{ t('quiz.points', { n: entry.score }, entry.score) }}</span>
            </li>
        </ol>
    </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

/**
 * Pseudonymous quiz leaderboard (shared: polls app after answering, presenter
 * on the projector). Shows only freely chosen nicknames + points — the data
 * comes from the anonymous `GET /api/leaderboard/{token}` (no real names, no
 * IDs).
 */

defineProps({
    entries: { type: Array,   default: () => [] },
    loading: { type: Boolean, default: false },
    error:   { type: Boolean, default: false },
})

const { t } = useI18n()

const MEDALS = { 1: '🥇', 2: '🥈', 3: '🥉' }
const medalFor = (rank) => MEDALS[rank] ?? '·'
</script>

<style scoped lang="scss">
.quorum--leaderboard {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm, 0.5rem);
}
.quorum--leaderboard-title {
    margin: 0;
    font-size: var(--quorum-text-md, 1.1rem);
    font-weight: 600;
}
.quorum--leaderboard-empty {
    margin: 0;
    color: var(--quorum-muted, #69767f);
}
.quorum--leaderboard-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;

    li {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.45rem 0.7rem;
        border: 1px solid var(--quorum-border, #d8d8d8);
        border-radius: var(--quorum-radius, 0.25rem);
        background: var(--quorum-bg, #fff);
    }
    li.is-rank-1 { border-inline-start: 6px solid var(--quorum-petrol, #0e817b); font-weight: 700; }
    li.is-rank-2 { border-inline-start: 6px solid var(--quorum-green, #6ead10); }
    li.is-rank-3 { border-inline-start: 6px solid var(--quorum-magenta, #bf215c); }
}
.quorum--leaderboard-rank  { inline-size: 1.6rem; text-align: center; }
.quorum--leaderboard-place { color: var(--quorum-muted, #69767f); font-variant-numeric: tabular-nums; }
.quorum--leaderboard-name  {
    flex: 1;
    overflow-wrap: anywhere;
}
.quorum--leaderboard-score {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

@media (forced-colors: active) {
    .quorum--leaderboard-list li { border-color: CanvasText; }
}
</style>
