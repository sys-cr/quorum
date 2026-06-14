<template>
    <section class="quorum--workplace quorum--container" :aria-label="t('collections.heading')">
        <header class="quorum--workplace-header">
            <h1 class="quorum--workplace-title">{{ t('collections.heading') }}</h1>
            <p v-if="store.isReady && !store.isEmpty" class="quorum--workplace-meta">
                {{ t('collections.summary', { n: store.collections.length }, store.collections.length) }}
            </p>
        </header>

        <!-- No active/archive switch: archived collections live in the
             sidebar "archive" view, together with the archived single
             surveys (user feedback). -->

        <StudipMessageBox v-if="store.isLoading" type="info" :hide-close="true">
            {{ t('collections.loading') }}
        </StudipMessageBox>

        <StudipMessageBox v-else-if="store.hasError" type="error" :hide-close="true">
            {{ t('collections.errorLoading') }}
            <button type="button" class="button" @click="reload">{{ t('workplace.retry') }}</button>
        </StudipMessageBox>

        <div v-else-if="store.isEmpty" class="quorum--hero-empty">
            <p class="quorum--empty-title">{{ t('collections.empty') }}</p>
            <hr class="quorum--aurora-divider" aria-hidden="true">
            <p class="quorum--workplace-empty-hint">
                {{ t('collections.emptyHintSidebar') }}
            </p>
        </div>

        <ul v-else class="quorum--workplace-cards">
            <li
                v-for="(col, i) in store.collections"
                :key="col.id"
                class="quorum--workplace-card"
                :class="`is-${stateOf(col)}`"
                :style="{ '--quorum-acc': accentFor(i) }"
            >
                <div class="quorum--workplace-card-head">
                    <span class="quorum--workplace-status" :data-status="stateOf(col)">
                        {{ statusLabel(col) }}
                    </span>
                    <span class="quorum--workplace-bubble"
                          :aria-label="t('collections.pollsAria', { n: col.poll_count }, col.poll_count)">
                        {{ col.poll_count }}
                    </span>
                </div>

                <p class="quorum--workplace-card-question">
                    <a class="quorum--card-open"
                       :href="collectionUrl(col)"
                       :aria-label="t('collections.openAria', { name: col.name })">{{ col.name }}</a>
                </p>

                <p class="quorum--collection-count">
                    {{ t('collections.pollCount', col.poll_count, { n: col.poll_count }) }}
                </p>

                <p v-if="col.description" class="quorum--collection-desc">{{ col.description }}</p>

                <p v-if="stateOf(col) === 'running'" class="quorum--collection-progress">
                    {{ t('collections.runningMeta', { active: col.active_count, total: col.poll_count }) }}
                </p>

                <div class="quorum--workplace-card-footer">
                    <span class="quorum--workplace-card-link-placeholder"></span>

                    <QuorumActionMenu
                        class="quorum--workplace-actions"
                        :actions="actionsFor(col)"
                        :label="t('collections.actionsAria', { name: col.name })"
                        :menu-title="t('workplace.actions')"
                        :busy="store.isBusy(col.id)"
                        @select="runAction($event, col)"
                    />
                </div>
            </li>
        </ul>

    </section>
</template>

<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCollectionsStore } from '../stores/useCollectionsStore.js'
import QuorumActionMenu from '@/components/QuorumActionMenu.vue'

/**
 * Collection list as Vue cards with an action menu.
 *
 * Replaces the earlier PHP card list (`views/workplace/collections.php`),
 * which had no actions at all — collections could not be started that way
 * (live-test feedback). Cards + menu mirror the single-survey cards
 * (WorkplaceIndex), including flow control:
 *
 *   - "Start voting — all questions": students click through all questions
 *     on their own; the owner finishes later and shows the results in the
 *     presenter.
 *   - "Start voting — question by question": only question 1 runs; the
 *     owner advances in the presenter via "start next question".
 *   - "Stop voting": stops all member questions.
 */

const { t } = useI18n()
const store = useCollectionsStore()

const root      = typeof document !== 'undefined'
    ? document.getElementById('quorum-workplace-app')
    : null
const pluginUrl = root?.dataset?.pluginUrl ?? ''
const csrfToken = root?.dataset?.csrf ?? ''
const cid       = root?.dataset?.cid ?? ''

onMounted(() => {
    store.setCsrfToken(csrfToken)
    // This page shows only active collections — the archive lives in the
    // sidebar "archive" view (WorkplaceIndex, view=archive).
    store.fetch(pluginUrl, 'active')
})

const reload = () => store.fetch(pluginUrl)

const ACCENTS = [
    'var(--quorum-petrol)',
    'var(--quorum-green)',
    'var(--quorum-magenta)',
    'var(--quorum-brand)',
    'var(--quorum-dark-violet)',
]
const accentFor = (i) => ACCENTS[i % ACCENTS.length]

// Inside the course frame (cid set) all Trails links stay in the course context.
const withCid = (url) => (cid ? `${url}?cid=${encodeURIComponent(cid)}` : url)
const collectionUrl = (col) => withCid(`${pluginUrl}/workplace/collection/${encodeURIComponent(col.id)}`)

const stateOf = (col) => ((col.active_count ?? 0) > 0 ? 'running' : 'paused')
const statusLabel = (col) => (
    stateOf(col) === 'running' ? t('workplace.statusRunning') : t('collections.statusIdle')
)

/**
 * Actions per state — same structure as `actionsFor` of the poll cards
 * (archived collections live in the archive view, not here):
 *   ready   → edit, present, start (all | question by question),
 *             download, archive
 *   running → edit, present, stop voting, download, archive
 */
const actionsFor = (col) => {
    const hasPolls = (col.poll_count ?? 0) > 0
    const items    = []

    items.push({ id: 'edit', label: t('workplace.actionEdit'), kind: 'link', href: withCid(`${pluginUrl}/workplace/collection_edit/${encodeURIComponent(col.id)}`) })
    items.push({ id: 'present', label: t('workplace.actionPresent'), kind: 'present', href: `${pluginUrl}/workplace/presenter/${encodeURIComponent(col.id)}`, disabled: !hasPolls })
    if (stateOf(col) === 'running') {
        items.push({ id: 'finish', label: t('collections.actionFinish'), kind: 'lifecycle', op: 'finish' })
    } else {
        items.push({ id: 'startAll',  label: t('collections.actionStartAll'),  kind: 'lifecycle', op: 'startAll',  disabled: !hasPolls })
        items.push({ id: 'startStep', label: t('collections.actionStartStep'), kind: 'lifecycle', op: 'startStep', disabled: !hasPolls })
    }
    items.push({ id: 'download', label: t('workplace.actionDownload'), kind: 'download', href: `${pluginUrl}/api/download_collection/${encodeURIComponent(col.id)}` })
    items.push({ id: 'archive', label: t('workplace.actionArchive'), kind: 'lifecycle', op: 'archive' })
    return items
}

const runAction = async (action, col) => {
    if (action.kind === 'link' || action.kind === 'download') {
        window.location.href = action.href
        return
    }
    if (action.kind === 'present') {
        // Presenter full screen in a new tab (as on the collection detail page).
        window.open(action.href, '_blank', 'noopener')
        return
    }
    // lifecycle: direct API call without confirmation (reversible)
    try {
        await store[action.op](pluginUrl, col.id)
    } catch {
        // the error is in store.error → the banner above renders it
    }
}
</script>

<style scoped lang="scss">
/* Cards, status pill, bubble, footer etc. use the same classes as
   WorkplaceIndex — the styles there are scoped, so the needed subset is
   duplicated here (deliberately kept small; shared tokens come from
   _studip-tokens.scss). */
.quorum--workplace {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-md);
    color: var(--quorum-fg);
    padding-block: var(--quorum-space-md);
}

.quorum--workplace-header {
    display: flex;
    flex-wrap: wrap;
    gap: var(--quorum-space-sm) var(--quorum-space-md);
    align-items: baseline;
    justify-content: space-between;
}
.quorum--workplace-title { font-size: var(--quorum-text-lg); font-weight: 600; margin: 0; }
.quorum--workplace-meta  { font-size: var(--quorum-text-sm); color: var(--quorum-muted); margin: 0; }

.quorum--hero-empty {
    background: var(--quorum-hero-bg);
    color: var(--quorum-hero-fg);
    border: 1px solid var(--quorum-hero-border);
    border-radius: var(--quorum-radius);
    padding: var(--quorum-space-lg) var(--quorum-space-xl);
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: var(--quorum-space-sm);
}
.quorum--aurora-divider { margin: var(--quorum-space-xs) 0; }
.quorum--empty-title {
    margin: 0;
    font-weight: 400;
    font-size: var(--quorum-text-md);
    color: inherit;
    opacity: 0.85;
}
.quorum--workplace-empty-hint {
    margin: 0;
    font-size: var(--quorum-text-lg);
    font-weight: 700;
    color: inherit;
}

.quorum--workplace-cards {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: var(--quorum-space-md);
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
}

.quorum--workplace-card {
    --quorum-acc: var(--quorum-petrol);
    background: color-mix(in srgb, var(--quorum-acc) 7%, var(--quorum-bg));
    border: 1px solid var(--quorum-border);
    border-inline-start: 8px solid var(--quorum-acc);
    border-radius: var(--quorum-radius);
    padding: var(--quorum-space-md);
    box-shadow: 0 1px 4px color-mix(in srgb, var(--quorum-acc) 18%, transparent);
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
    min-block-size: 152px;
    // Anchor for the stretched link (clicking the whole card opens the detail page).
    position: relative;
}

// Clicking the card opens the collection: the name link spans the whole card
// via ::after; footer/menu sit above it with a higher z-index.
.quorum--card-open {
    color: inherit;
    text-decoration: none;

    &::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 1;
        border-radius: inherit;
    }
    &:focus-visible { outline: 2px solid var(--quorum-acc); outline-offset: 2px; }
}
.quorum--workplace-card-footer { position: relative; z-index: 2; }
.quorum--workplace-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--quorum-space-sm);
}

.quorum--workplace-status {
    font-size: var(--quorum-text-sm);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: var(--quorum-space-xs) var(--quorum-space-sm);
    border-radius: 999px;
    color: var(--quorum-fg);
    background: color-mix(in srgb, var(--quorum-acc) 18%, transparent);

    &[data-status='paused'] { color: var(--quorum-muted); background: transparent; }
}

.quorum--workplace-bubble {
    background: color-mix(in srgb, var(--quorum-acc) 60%, var(--quorum-fg));
    color: var(--quorum-accent-contrast);
    min-inline-size: 2rem;
    block-size: 2rem;
    padding: 0 0.55rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
}

.quorum--workplace-card-question {
    white-space: pre-line;
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.35;
    color: var(--quorum-fg);
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.quorum--collection-count {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--quorum-fg);
}

.quorum--collection-desc {
    margin: 0;
    font-size: 0.85rem;
    color: var(--quorum-muted);
    white-space: pre-line;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.quorum--collection-progress {
    margin: 0;
    font-size: 0.8rem;
    color: var(--quorum-muted);
}

.quorum--workplace-card-footer {
    margin-block-start: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-block-start: 0.4rem;
}
.quorum--workplace-card-link {
    color: var(--quorum-link);
    font-weight: 600;
    text-decoration: none;
    border-bottom: 1px solid currentColor;
    padding-block: 0.1rem;

    &:hover, &:focus-visible {
        color: var(--quorum-link-visited);
    }
}

@media (prefers-contrast: more), (forced-colors: active) {
    .quorum--workplace-card {
        background: var(--quorum-bg);
        border-inline-start-color: CanvasText;
        box-shadow: none;
    }
    .quorum--workplace-bubble { background: CanvasText; color: Canvas; }
    .quorum--workplace-status {
        color: CanvasText;
        background: transparent;
        border: 1px solid CanvasText;
    }
}
</style>
