<template>
    <article class="quorum--voting-compare">
        <header class="quorum--page-header">
            <h1>{{ t('compare.heading') }}</h1>
        </header>

        <div v-if="error" class="quorum--voting-compare-error" role="alert">
            <p>{{ t('compare.errorLoading') }}</p>
            <button type="button" class="button" @click="load">{{ t('compare.retry') }}</button>
        </div>

        <div v-else-if="!loaded" class="quorum--voting-compare-loading" role="status" aria-live="polite">
            {{ t('compare.loading') }}
        </div>

        <main v-else class="quorum--voting-compare-grid">
            <section
                v-for="(voting, index) in [votingA, votingB]"
                :key="voting.id"
                class="quorum--voting-side"
                :class="`quorum--voting-side-${index === 0 ? 'a' : 'b'}`"
            >
                <nav>
                    <a :href="resultsUrl(voting.id)">
                        {{ voting.question || t('compare.sideHeading', { date: formatDate(voting.mkdate) }) }}
                    </a>
                </nav>
                <div class="quorum--voting-side-result">
                    <p>
                        {{ t('compare.sampleSize', { n: voting.response_count ?? 0 }, voting.response_count ?? 0) }}
                    </p>
                </div>
            </section>
        </main>
    </article>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVotingStore } from '../stores/useVotingStore.js'

const props = defineProps({
    idA: { type: String, required: true },
    idB: { type: String, required: true },
})

const { t, locale } = useI18n()
const store         = useVotingStore()

const findIn = (id) => {
    if (store.current?.id === id) return store.current
    return store.archive.find(v => v.id === id) ?? null
}

const votingA = computed(() => findIn(props.idA))
const votingB = computed(() => findIn(props.idB))
const loaded  = computed(() => Boolean(votingA.value && votingB.value))
const error   = ref(false)

// Per-round results detail: the same list/detail view as on the workplace
// (`workplace/results`), within the course frame (cid) — one results view for
// both contexts (like SurveysIndex/Archive). Base URL + cid from the mount
// `<div>` (#quorum-course-app).
const resultsUrl = (id) => {
    const r = typeof window !== 'undefined' ? document.getElementById('quorum-course-app') : null
    const u = r?.dataset?.pluginUrl ?? ''
    if (!u) return ''
    const base = u.endsWith('/') ? u : `${u}/`
    const cid  = r?.dataset?.cid ?? ''
    const path = `workplace/results/${encodeURIComponent(id)}`
    return `${base}${cid ? `${path}?cid=${encodeURIComponent(cid)}` : path}`
}

// Quorum returns mkdate as a Unix timestamp (seconds) — fallback label when
// the question is missing.
const formatDate = (mkdate) => {
    if (mkdate == null) return ''
    const ms = typeof mkdate === 'number' ? mkdate * 1000 : Date.parse(mkdate)
    if (Number.isNaN(ms)) return ''
    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(new Date(ms))
}

const load = async () => {
    if (loaded.value) return
    error.value = false
    try {
        await store.loadTwoVotings(props.idA, props.idB)
    } catch {
        error.value = true
    }
}

onMounted(load)
</script>

<style scoped lang="scss">
.quorum--voting-compare {
    padding-block: 1rem;
    padding-inline: 1rem;
    background: var(--quorum-bg);
    color: var(--quorum-fg);

    h1 {
        font-size: var(--quorum-text-xl);
        margin-block-end: var(--quorum-space-md);
        color: var(--quorum-fg);
    }
}

.quorum--voting-compare-loading {
    padding: var(--quorum-space-xl) var(--quorum-space-lg);
    text-align: center;
    color: var(--quorum-muted);
    background: color-mix(in srgb, var(--quorum-petrol) 5%, var(--quorum-bg));
    border: 1px solid var(--quorum-border);
    border-inline-start: 8px solid var(--quorum-petrol);
    border-radius: var(--quorum-radius);
}

.quorum--voting-compare-error {
    padding: var(--quorum-space-lg);
    color: var(--quorum-fg);
    background: color-mix(in srgb, var(--quorum-error) 8%, var(--quorum-bg));
    border: 1px solid var(--quorum-error);
    border-inline-start: 8px solid var(--quorum-error);
    border-radius: var(--quorum-radius);
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
    align-items: flex-start;
}

/* Compare grid: two side-by-side columns from ~640px, otherwise stacked.
   `auto-fit` with `minmax(min(320px, 100%), 1fr)` handles this fluidly without
   a hard breakpoint. */
.quorum--voting-compare-grid {
    display: grid;
    gap: var(--quorum-space-md);
    grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr));
}

/* Compare sides follow the same card pattern as SurveysIndex/Archive:
   8px border-inline-start in the accent color, tinted background, shadow.
   Side A = petrol, Side B = magenta. */
.quorum--voting-side {
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

    nav a {
        color: var(--quorum-link);
        text-decoration: underline;
        min-block-size: var(--quorum-button-min-size);
        display: inline-block;
        line-height: var(--quorum-button-min-size);
        font-weight: 600;

        &:visited { color: var(--quorum-link-visited); }
        &:focus-visible {
            outline: 2px solid var(--quorum-acc);
            outline-offset: 2px;
        }
    }
}

.quorum--voting-side-a { --quorum-acc: var(--quorum-petrol); }
.quorum--voting-side-b { --quorum-acc: var(--quorum-magenta); }

.quorum--voting-side-result {
    padding: var(--quorum-space-sm) var(--quorum-space-md);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
    border: 1px solid color-mix(in srgb, var(--quorum-acc) 25%, var(--quorum-border));
    color: var(--quorum-fg);
    font-weight: 500;
}

@media (prefers-contrast: more) {
    .quorum--voting-side    { border-width: 2px; }
    .quorum--voting-side a  { text-decoration-thickness: 2px; }
}
</style>
