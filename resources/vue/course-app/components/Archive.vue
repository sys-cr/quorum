<template>
    <article class="quorum--archive">
        <header>
            <h1>{{ t('archive.heading') }}</h1>
        </header>

        <ul v-if="rows.length > 0" class="quorum--cards quorum--archive-votings">
            <li
                v-for="row in rows"
                :key="row.id"
                class="quorum--card quorum--archive-card"
                :class="pollTypeAccentClass(row.type)"
            >
                <header class="quorum--card-header">
                    <a
                        :href="resultsUrl(row.id)"
                        class="quorum--card-title quorum--archive-link"
                    >
                        {{ titleOf(row) }}
                    </a>
                    <span class="quorum--bubble">
                        <span aria-hidden="true">{{ row.response_count ?? 0 }}</span>
                        <span class="quorum--visually-hidden">
                            {{ t('archive.responsesAria', { n: row.response_count ?? 0 }, row.response_count ?? 0) }}
                        </span>
                    </span>
                </header>
                <p class="quorum--card-meta">
                    <time :datetime="isoOf(row.mkdate)">{{ formatDate(row.mkdate) }}</time>
                    <template v-if="typeLabel(row.type)">
                        <span aria-hidden="true"> · </span>
                        <span class="quorum--card-type">{{ typeLabel(row.type) }}</span>
                    </template>
                    <template v-if="row.quiz_mode">
                        <span aria-hidden="true"> · </span>
                        <span class="quorum--card-quiz"><span aria-hidden="true">🏆</span> {{ t('quiz.badge') }}</span>
                    </template>
                </p>
            </li>
        </ul>

        <div v-else class="quorum--archive-empty quorum--hero-empty">
            <p class="quorum--empty-title">{{ t('archive.empty') }}</p>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVotingStore } from '../stores/useVotingStore.js'
import { pollTypeLabel } from '@/pollTypeLabel.js'
import { pollTypeAccentClass } from '@/pollTypeAccent.js'

const { t, locale } = useI18n()
const typeLabel     = (type) => pollTypeLabel(t, type)
const store         = useVotingStore()

// Finished polls only (is_active === false), newest first (by mkdate).
const rows = computed(() => [...store.finishedVotings].sort((a, b) => (b.mkdate ?? 0) - (a.mkdate ?? 0)))

const titleOf = (poll) => poll?.question ?? ''

// Results detail: the same list/detail view as on the workplace
// (`workplace/results`), within the course frame (cid) — one results view for
// both contexts. Base URL + cid from the mount `<div>` (#quorum-course-app).
// Optionally append cid to a path — extracted so `resultsUrl` stays under the
// McCabe limit.
const withCid = (path, cid) => (cid ? `${path}?cid=${encodeURIComponent(cid)}` : path)
const resultsUrl = (id) => {
    const r = typeof window !== 'undefined' ? document.getElementById('quorum-course-app') : null
    const u = r?.dataset?.pluginUrl ?? ''
    if (!u) return ''
    const base = u.endsWith('/') ? u : `${u}/`
    return `${base}${withCid(`workplace/results/${encodeURIComponent(id)}`, r?.dataset?.cid ?? '')}`
}

// Quorum returns mkdate as a Unix timestamp (seconds).
const toDate = (mkdate) => {
    if (mkdate == null) return null
    const ms = typeof mkdate === 'number' ? mkdate * 1000 : Date.parse(mkdate)
    return Number.isNaN(ms) ? null : new Date(ms)
}
const isoOf = (mkdate) => toDate(mkdate)?.toISOString() ?? ''
const formatDate = (mkdate) => {
    const d = toDate(mkdate)
    if (!d) return ''
    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }).format(d)
}
</script>

<style scoped lang="scss">
/* Card grid: 1 column on mobile, multi-column via `auto-fill` from 600px up.
   Same pattern as SurveysIndex. */
.quorum--archive {
    /* Horizontal padding comes from the app container — only vertical spacing here. */
    padding-block: var(--quorum-space-md);

    h1 {
        font-size: var(--quorum-text-xl);
        margin-block-end: var(--quorum-space-md);
        color: var(--quorum-fg);
    }
}

.quorum--cards {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: var(--quorum-space-md);
    /* Mobile = 1 column (auto-fill with min(280px, 100%) handles this without a
       media query); fluid multi-column on desktop. */
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
}

.quorum--card {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
    padding: var(--quorum-space-md);
    border: 1px solid var(--quorum-border);
    border-inline-start: 8px solid var(--quorum-acc, var(--quorum-border));
    border-radius: var(--quorum-radius);
    background: color-mix(in srgb, var(--quorum-acc, var(--quorum-border)) 7%, var(--quorum-bg));
    box-shadow: 0 1px 4px color-mix(in srgb, var(--quorum-acc, var(--quorum-border)) 18%, transparent);
    color: var(--quorum-fg);
    overflow-wrap: anywhere;
}

.quorum--card-header {
    display: flex;
    align-items: flex-start;
    gap: var(--quorum-space-md);
    justify-content: space-between;
}

.quorum--card-title {
    font-weight: 600;
    font-size: var(--quorum-text-md);
    line-height: 1.3;
    flex: 1 1 auto;
    min-inline-size: 0;
}

.quorum--card-meta {
    margin: 0;
    color: var(--quorum-muted);
    font-size: var(--quorum-text-sm);
}
.quorum--card-quiz { font-weight: 700; color: var(--quorum-fg); }

.quorum--archive-link {
    display: inline-block;
    min-block-size: var(--quorum-button-min-size);
    line-height: var(--quorum-button-min-size);
    color: var(--quorum-link);
    text-decoration: underline;

    &:visited {
        color: var(--quorum-link-visited);
    }

    &:focus-visible {
        outline: var(--quorum-focus-ring);
        outline-offset: 2px;
    }
}

.quorum--bubble {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-inline-size: 2rem;
    min-block-size: 2rem;
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    /* Reference --quorum-acc directly (card accent, set by .acc-*). Indirection
       via --quorum-bubble-bg in :root would resolve at definition time and
       ignore the later .acc-* override. */
    background: var(--quorum-acc, var(--quorum-petrol));
    color: var(--quorum-accent-contrast);
    border: 1px solid var(--quorum-acc, var(--quorum-petrol));
    font-weight: 600;
}

.quorum--archive-empty {
    padding-block: var(--quorum-space-xl);
    text-align: center;
    color: var(--quorum-muted);
}

.quorum--hero-empty {
    background: var(--quorum-hero-bg);
    color: var(--quorum-hero-fg);
    border: 1px solid var(--quorum-hero-border);
    border-radius: var(--quorum-radius);
    padding: var(--quorum-space-xl) var(--quorum-space-lg);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--quorum-space-md);
}
.quorum--empty-title {
    margin: 0;
    font-weight: 600;
    color: inherit;
}

.quorum--visually-hidden {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}

@media (prefers-contrast: more) {
    .quorum--archive-link     { text-decoration-thickness: 2px; }
    .quorum--bubble           { border-width: 2px; }
    .quorum--table-default tr { border-width: 2px; }
}
</style>
