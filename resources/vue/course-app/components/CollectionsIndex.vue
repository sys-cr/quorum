<template>
    <main class="quorum--surveys-index" :aria-label="t('collections.heading')">
        <header>
            <h1>{{ t('collections.heading') }}</h1>
        </header>

        <p v-if="store.isLoading" class="quorum--empty-title" role="status">
            {{ t('collections.loading') }}
        </p>

        <div v-else-if="store.hasError" class="quorum--hero-empty" role="alert">
            <p class="quorum--empty-title">{{ t('collections.errorLoading') }}</p>
            <button type="button" class="button" @click="store.loadAll()">{{ t('surveys.retry') }}</button>
        </div>

        <div v-else-if="store.isEmpty" class="quorum--hero-empty">
            <p class="quorum--empty-title">{{ t('collections.empty') }}</p>
        </div>

        <ul v-else class="quorum--workplace-cards">
            <li
                v-for="col in store.collections"
                :key="col.id"
                class="quorum--workplace-card"
                :class="`is-${stateOf(col)}`"
                :style="{ '--quorum-acc': COLLECTION_ACCENT_VAR }"
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
                       :href="deepLink(`workplace/collection/${col.id}`)"
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

        <footer class="button-group quorum--footer-actions">
            <button type="button" class="button accept" @click="onAdd">
                {{ t('collections.add') }}
            </button>
        </footer>
    </main>
</template>

<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCourseCollectionsStore } from '../stores/useCourseCollectionsStore.js'
import QuorumActionMenu from '@/components/QuorumActionMenu.vue'
import { COLLECTION_ACCENT_VAR } from '@/pollTypeAccent.js'

/**
 * Collection list in the course tab (teacher view). Cards + action menu like
 * the workplace `CollectionsIndex`, but cid-scoped: lifecycle against
 * `api/collection_*` (co-teaching), detail/edit/present as a deep link into the
 * course frame of the workplace full pages (like `SurveysIndex`).
 */

const { t } = useI18n()
const store  = useCourseCollectionsStore()

onMounted(() => {
    if (store.status === 'idle') store.loadAll()
})

// Base URL + cid from the mount `<div>` (#quorum-course-app) so deep links stay
// in the course context.
const mount     = typeof document !== 'undefined' ? document.getElementById('quorum-course-app') : null
const cid       = mount?.dataset?.cid ?? ''
const rawPlugin = mount?.dataset?.pluginUrl ?? ''
const base      = rawPlugin ? (rawPlugin.endsWith('/') ? rawPlugin : `${rawPlugin}/`) : ''
const deepLink  = (path) => {
    const url = `${base}${path}`
    return cid ? `${url}${path.includes('?') ? '&' : '?'}cid=${encodeURIComponent(cid)}` : url
}
const navigate  = (path) => { if (typeof window !== 'undefined' && base) window.location.href = deepLink(path) }

const stateOf = (col) => ((col.active_count ?? 0) > 0 ? 'running' : 'paused')
const statusLabel = (col) =>
    stateOf(col) === 'running' ? t('workplace.statusRunning') : t('collections.statusIdle')

const onAdd = () => navigate('workplace/collection_new')

const actionsFor = (col) => {
    const hasPolls = (col.poll_count ?? 0) > 0
    const items    = []
    items.push({ id: 'edit', label: t('workplace.actionEdit'), kind: 'link', path: `workplace/collection_edit/${col.id}` })
    items.push({ id: 'present', label: t('workplace.actionPresent'), kind: 'present', path: `workplace/presenter/${col.id}`, disabled: !hasPolls })
    if (stateOf(col) === 'running') {
        items.push({ id: 'finish', label: t('collections.actionFinish'), kind: 'lifecycle', op: 'finish' })
    } else {
        items.push({ id: 'startAll',  label: t('collections.actionStartAll'),  kind: 'lifecycle', op: 'startAll',  disabled: !hasPolls })
        items.push({ id: 'startStep', label: t('collections.actionStartStep'), kind: 'lifecycle', op: 'startStep', disabled: !hasPolls })
    }
    items.push({ id: 'download', label: t('workplace.actionDownload'), kind: 'download', path: `api/download_collection/${col.id}` })
    items.push({ id: 'archive', label: t('workplace.actionArchive'), kind: 'lifecycle', op: 'archive' })
    return items
}

const runAction = async (action, col) => {
    if (action.kind === 'link' || action.kind === 'download') {
        navigate(action.path)
        return
    }
    if (action.kind === 'present') {
        // The presenter is cid-independent (full screen in a new tab).
        if (typeof window !== 'undefined' && base) window.open(`${base}${action.path}`, '_blank', 'noopener')
        return
    }
    try {
        await store[action.op](col.id)
    } catch {
        /* store.error renders the banner above. */
    }
}
</script>

<style scoped lang="scss">
.quorum--surveys-index {
    padding-block: var(--quorum-space-md);
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-md);

    h1 {
        font-size: var(--quorum-text-xl);
        margin-block-end: var(--quorum-space-md);
        color: var(--quorum-fg);
    }
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
.quorum--empty-title { margin: 0; font-weight: 600; color: inherit; }

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
// via ::after. Footer/menu sit above it with a higher z-index and stay
// independently clickable.
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
.quorum--collection-count { margin: 0; font-size: 0.85rem; font-weight: 600; color: var(--quorum-fg); }
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
.quorum--collection-progress { margin: 0; font-size: 0.8rem; color: var(--quorum-muted); }
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

    &:hover, &:focus-visible { color: var(--quorum-link-visited); }
}
.quorum--footer-actions { padding-block: var(--quorum-space-sm); }

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
