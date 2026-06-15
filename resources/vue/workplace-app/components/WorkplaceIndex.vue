<template>
    <section class="quorum--workplace quorum--container" :aria-label="t('workplace.heading')">
        <header class="quorum--workplace-header">
            <h1 class="quorum--workplace-title">
                {{ store.view === 'archive' ? t('workplace.headingArchive') : t('workplace.heading') }}
            </h1>
            <p v-if="store.isReady && !store.isEmpty" class="quorum--workplace-meta">
                <template v-if="store.view === 'active'">
                    {{ t('workplace.summary', { running: store.runningPolls.length, total: store.polls.length }) }}
                </template>
                <template v-else>
                    {{ t('workplace.summaryArchive', { total: store.polls.length }) }}
                </template>
            </p>
        </header>

        <StudipMessageBox v-if="store.isLoading" type="info" :hide-close="true">
            {{ t('workplace.loading') }}
        </StudipMessageBox>

        <StudipMessageBox v-else-if="store.hasError" type="error" :hide-close="true">
            {{ t('workplace.errorLoading') }}
            <button type="button" class="button" @click="reload">{{ t('workplace.retry') }}</button>
        </StudipMessageBox>

        <template v-else-if="store.isEmpty">
            <div class="quorum--hero-empty">
                <p class="quorum--empty-title">
                    {{ store.view === 'archive' ? t('workplace.emptyArchive') : t('workplace.empty') }}
                </p>
                <hr v-if="store.view === 'active'" class="quorum--aurora-divider" aria-hidden="true">
                <p v-if="store.view === 'active'" class="quorum--workplace-empty-hint">
                    {{ t('workplace.emptyHintSidebar') }}
                </p>
            </div>
            <!-- Onboarding entry point OUTSIDE the box (its own action row,
                 Stud.IP standard button). -->
            <div v-if="store.view === 'active' && demoUrl" class="quorum--workplace-empty-demo">
                <span>{{ t('workplace.emptyDemoText') }}</span>
                <a class="button" :href="demoUrl">{{ t('workplace.emptyDemoCta') }}</a>
            </div>
        </template>

        <ul v-else class="quorum--workplace-cards">
            <li
                v-for="poll in store.polls"
                :key="poll.id"
                class="quorum--workplace-card"
                :class="`is-${stateOf(poll)}`"
                :style="{ '--quorum-acc': pollTypeAccentVar(poll.type) }"
            >
                <div class="quorum--workplace-card-head">
                    <span class="quorum--workplace-head-tags">
                        <span class="quorum--workplace-status" :data-status="stateOf(poll)">
                            {{ statusLabel(poll) }}
                        </span>
                        <span v-if="typeLabel(poll.type)" class="quorum--workplace-type">
                            {{ typeLabel(poll.type) }}
                        </span>
                        <span v-if="poll.quiz_mode" class="quorum--workplace-quiz">
                            <span aria-hidden="true">🏆</span> {{ t('quiz.badge') }}
                        </span>
                    </span>
                    <span class="quorum--workplace-bubble"
                          :aria-label="t('workplace.responsesAria', poll.response_count, { n: poll.response_count })">
                        {{ poll.response_count }}
                    </span>
                </div>

                <p class="quorum--workplace-card-question">
                    <a class="quorum--card-open"
                       :href="`${pluginUrl}/workplace/results/${encodeURIComponent(poll.id)}`"
                       :aria-label="t('workplace.openResultsAria', { question: poll.question })">{{ poll.question }}</a>
                </p>

                <p class="quorum--workplace-card-seminar">
                    <span class="quorum--workplace-seminar-label">{{ t('workplace.seminarLabel') }}:</span>
                    <template v-if="poll.seminar_id">
                        {{ poll.seminar_name || t('workplace.seminarUnknown') }}
                    </template>
                    <em v-else class="quorum--workplace-seminar-global">{{ t('workplace.seminarGlobal') }}</em>
                </p>

                <p v-if="poll.children_count > 0" class="quorum--workplace-card-chain">
                    <span aria-hidden="true">⛓</span>
                    {{ t('workplace.chain', poll.children_count, { n: poll.children_count }) }}
                </p>

                <p v-if="poll.active_children > 0" class="quorum--workplace-card-roundlive">
                    <span aria-hidden="true">▶</span>
                    {{ t('workplace.chainRunning') }}
                </p>

                <div class="quorum--workplace-card-footer">
                    <a
                        v-if="poll.seminar_id"
                        class="quorum--workplace-card-link"
                        :href="seminarLinkFor(poll)"
                        :aria-label="t('workplace.openLinkAria', { question: poll.question })"
                    >
                        {{ t('workplace.open') }}
                    </a>
                    <span v-else class="quorum--workplace-card-link-placeholder"></span>

                    <QuorumActionMenu
                        class="quorum--workplace-actions"
                        :actions="actionsFor(poll)"
                        :label="t('workplace.actionsAria', { question: poll.question })"
                        :menu-title="t('workplace.actions')"
                        :busy="store.isBusy(poll.id)"
                        @select="runAction($event, poll)"
                    />
                </div>
            </li>
        </ul>

        <!-- Archived collections: per user feedback they live here in the
             archive, together with the archived single surveys — the former
             active/archive toggle on the collections page is gone. -->
        <template v-if="store.view === 'archive'">
            <StudipMessageBox v-if="colStore.hasError" type="error" :hide-close="true">
                {{ t('collections.errorLoading') }}
                <button type="button" class="button" @click="reloadCollections">{{ t('workplace.retry') }}</button>
            </StudipMessageBox>

            <template v-else-if="colStore.isReady && colStore.collections.length">
                <h2 class="quorum--workplace-subheading">{{ t('collections.headingArchive') }}</h2>
                <ul class="quorum--workplace-cards">
                    <li
                        v-for="col in colStore.collections"
                        :key="col.id"
                        class="quorum--workplace-card is-archived"
                        :style="{ '--quorum-acc': COLLECTION_ACCENT_VAR }"
                    >
                        <div class="quorum--workplace-card-head">
                            <span class="quorum--workplace-status" data-status="archived">
                                {{ t('workplace.statusArchived') }}
                            </span>
                            <span class="quorum--workplace-bubble"
                                  :aria-label="t('collections.pollsAria', { n: col.poll_count }, col.poll_count)">
                                {{ col.poll_count }}
                            </span>
                        </div>

                        <p class="quorum--workplace-card-question">
                            <a class="quorum--card-open"
                               :href="`${pluginUrl}/workplace/collection/${encodeURIComponent(col.id)}`"
                               :aria-label="t('collections.openAria', { name: col.name })">{{ col.name }}</a>
                        </p>
                        <p class="quorum--workplace-card-count">
                            {{ t('collections.pollCount', col.poll_count, { n: col.poll_count }) }}
                        </p>
                        <p v-if="col.description" class="quorum--workplace-card-seminar">{{ col.description }}</p>

                        <div class="quorum--workplace-card-footer">
                            <span class="quorum--workplace-card-link-placeholder"></span>
                            <QuorumActionMenu
                                class="quorum--workplace-actions"
                                :actions="collectionActionsFor()"
                                :label="t('collections.actionsAria', { name: col.name })"
                                :menu-title="t('workplace.actions')"
                                :busy="colStore.isBusy(col.id)"
                                @select="runCollectionAction($event, col)"
                            />
                        </div>
                    </li>
                </ul>
            </template>
        </template>

        <QuorumDialog
            v-if="confirmCollection.collection"
            :title="t('collections.confirmDeleteTitle')"
            :close-text="t('workplace.cancel')"
            :confirm-text="t('collections.confirmDeleteAccept')"
            confirm-class="delete"
            @confirm="performCollectionConfirm"
            @close="confirmCollection.collection = null"
        >
            <template #dialogContent>
                {{ t('collections.confirmDeleteBody', { name: confirmCollection.collection.name }) }}
            </template>
        </QuorumDialog>

        <QuorumDialog
            v-if="confirm.poll"
            :title="t('workplace.confirmDeleteTitle')"
            :close-text="t('workplace.cancel')"
            :confirm-text="t('workplace.confirmDeleteAccept')"
            confirm-class="delete"
            @confirm="performConfirm"
            @close="cancelConfirm"
        >
            <template #dialogContent>
                {{ t('workplace.confirmDeleteBody', { question: confirm.poll.question, count: confirm.poll.response_count }) }}
            </template>
        </QuorumDialog>

        <QrCodeDialog
            v-if="qrPoll"
            :poll-title="qrPoll.question"
            :poll-url="qrPoll.join_url"
            @close="qrPoll = null"
        />
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMySurveysStore } from '../stores/useMySurveysStore.js'
import { useCollectionsStore } from '../stores/useCollectionsStore.js'
import QuorumDialog from '@/components/QuorumDialog.vue'
import QrCodeDialog from '@/components/QrCodeDialog.vue'
import QuorumActionMenu from '@/components/QuorumActionMenu.vue'
import { pollTypeLabel } from '@/pollTypeLabel.js'
import { pollTypeAccentVar, COLLECTION_ACCENT_VAR } from '@/pollTypeAccent.js'

/**
 * Workplace full page — list of own polls with lifecycle actions.
 *
 * Three card states from two orthogonal fields:
 *   running:  is_active && !archived_at
 *   paused:  !is_active && !archived_at  (voting ended, data kept)
 *   archived: archived_at != null        (soft delete)
 *
 * Actions per state:
 *   running  → finish, archive
 *   paused   → start, restart (wizard), archive
 *   archived → unarchive, delete (hard delete with confirm)
 *
 * Each card inherits a rotating accent color
 * (petrol/green/magenta/brand/dark-violet).
 */

const { t } = useI18n()
const typeLabel = (type) => pollTypeLabel(t, type)
const store = useMySurveysStore()
// Archive view: archived collections are shown as well (own store, same
// lifecycle API as on the collections page).
const colStore = useCollectionsStore()

const root      = typeof document !== 'undefined'
    ? document.getElementById('quorum-workplace-app')
    : null
const pluginUrl = root?.dataset?.pluginUrl ?? ''
const initView  = root?.dataset?.view ?? 'active'
// Onboarding entry point in the empty state: a pointer to loading the demo
// content (course-independent). The GET page shows a confirmation first.
const demoUrl   = pluginUrl ? `${pluginUrl}/workplace/load_demo` : ''
const csrfToken = root?.dataset?.csrf ?? ''

const confirm           = reactive({ poll: null, action: null })
const confirmCollection = reactive({ collection: null })
// Poll whose QR/share dialog is currently open (null = closed).
const qrPoll     = ref(null)

// The card action menu (open/close, keyboard, focus return, single-open) lives
// in the reusable QuorumActionMenu — only the action definition (actionsFor)
// and execution (runAction) remain here.

onMounted(() => {
    store.setCsrfToken(csrfToken)
    store.fetch(pluginUrl, initView)
    if (initView === 'archive') {
        colStore.setCsrfToken(csrfToken)
        colStore.fetch(pluginUrl, 'archive')
    }
})

const reload            = () => store.fetch(pluginUrl)
const reloadCollections = () => colStore.fetch(pluginUrl, 'archive')

/**
 * Actions for archived collections: reactivate (back to the collections
 * page) or delete permanently (confirm dialog).
 */
const collectionActionsFor = () => [
    { id: 'unarchive', label: t('workplace.actionUnarchive'), kind: 'lifecycle', op: 'unarchive' },
    { id: 'delete',    label: t('collections.actionDelete'),  kind: 'confirm', destructive: true },
]

const runCollectionAction = async (action, col) => {
    if (action.kind === 'confirm') {
        confirmCollection.collection = col
        return
    }
    try {
        await colStore[action.op](pluginUrl, col.id)
    } catch {
        // the error is in colStore.error → the section's banner renders it
    }
}

const performCollectionConfirm = async () => {
    const col = confirmCollection.collection
    confirmCollection.collection = null
    if (!col) return
    try {
        await colStore.deleteCollection(pluginUrl, col.id)
    } catch { /* see above */ }
}

const seminarLinkFor = (poll) => `${pluginUrl}/index/index?cid=${encodeURIComponent(poll.seminar_id)}`

const stateOf = (poll) => {
    if (poll.archived_at) return 'archived'
    return poll.is_active ? 'running' : 'paused'
}
const statusLabel = (poll) => {
    const map = {
        running:  t('workplace.statusRunning'),
        paused:   t('workplace.statusPaused'),
        archived: t('workplace.statusArchived'),
    }
    return map[stateOf(poll)]
}

/**
 * Returns the available actions per card depending on state and properties.
 * Edit + restart open separate Stud.IP dialogs — as external links to plugin
 * Trails routes so Stud.IP can mount the real `StudipWizardDialog`/`StudipDialog`.
 */
const actionsFor = (poll) => {
    const state = stateOf(poll)
    const url   = (path) => `${pluginUrl}/workplace/${path}/${encodeURIComponent(poll.id)}`
    const items = []

    items.push({ id: 'edit',   label: t('workplace.actionEdit'),   kind: 'link', href: url('edit') })
    // Results — retrospective review incl. CSV export, in ANY state (also
    // archived: that is exactly what the archive is for).
    items.push({ id: 'results', label: t('workplace.actionResults'), kind: 'link', href: url('results') })
    // Present — single poll directly in the fullscreen presenter (new tab),
    // without needing a collection. For running + paused polls (archived ones
    // have no live presenter use).
    if (state !== 'archived') {
        items.push({ id: 'present', label: t('workplace.actionPresent'), kind: 'present', href: url('present_poll') })
    }
    // QR / share — only if the backend provided a join_url.
    if (poll.join_url) {
        items.push({ id: 'share', label: t('workplace.actionShare'), kind: 'qr' })
    }
    items.push({ id: 'assign', label: t('workplace.actionAssign'), kind: 'link', href: `${pluginUrl}/workplace/collection_assign/${encodeURIComponent(poll.id)}` })
    // Definition download (JSON) — file download via Content-Disposition,
    // re-importable via survey import.
    items.push({ id: 'download', label: t('workplace.actionDownload'), kind: 'download', href: `${pluginUrl}/api/download/${encodeURIComponent(poll.id)}` })

    // "Show comparison" appears only when the poll has follow-up rounds
    // (children_count > 0). The full page shows the whole compare chain with
    // delta display.
    if ((poll.children_count ?? 0) > 0 && state !== 'archived') {
        items.push({ id: 'compare', label: t('workplace.actionCompare'), kind: 'link', href: url('compare') })
    }

    if (state === 'running') {
        items.push({ id: 'finish',   label: t('workplace.actionFinish'),   kind: 'lifecycle', op: 'finish' })
        items.push({ id: 'archive',  label: t('workplace.actionArchive'),  kind: 'lifecycle', op: 'archive' })
    } else if (state === 'paused') {
        items.push({ id: 'start',    label: t('workplace.actionStart'),    kind: 'lifecycle', op: 'start' })
        items.push({ id: 'restart',  label: t('workplace.actionRestart'),  kind: 'link',      href: url('restart') })
        items.push({ id: 'archive',  label: t('workplace.actionArchive'),  kind: 'lifecycle', op: 'archive' })
    } else {
        items.push({ id: 'unarchive', label: t('workplace.actionUnarchive'), kind: 'lifecycle', op: 'unarchive' })
        items.push({ id: 'delete',    label: t('workplace.actionDelete'),    kind: 'confirm',   op: 'delete', destructive: true })
    }
    return items
}

const runAction = async (action, poll) => {
    if (action.kind === 'link') {
        // Stud.IP's JS layer turns the URL into an inline dialog (data-dialog).
        window.location.href = action.href
        return
    }
    if (action.kind === 'download') {
        // File download (Content-Disposition) — no page change.
        window.location.href = action.href
        return
    }
    if (action.kind === 'present') {
        // Presenter fullscreen in a new tab.
        window.open(action.href, '_blank', 'noopener')
        return
    }
    if (action.kind === 'qr') {
        qrPoll.value = poll
        return
    }
    if (action.kind === 'confirm') {
        confirm.poll   = poll
        confirm.action = action
        return
    }
    // lifecycle: direct API call without confirmation (reversible)
    try {
        await store.lifecycle(pluginUrl, poll.id, action.op)
    } catch {
        // Error is in store.error → rendered in the error banner
    }
}

const cancelConfirm = () => {
    confirm.poll   = null
    confirm.action = null
}

const performConfirm = async () => {
    const poll = confirm.poll
    if (!poll) return
    cancelConfirm()
    try {
        await store.deletePoll(pluginUrl, poll.id)
    } catch { /* see above */ }
}
</script>

<style scoped lang="scss">
.quorum--workplace {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-md);
    color: var(--quorum-fg);
    /* Vertical padding in addition to horizontal padding from `.quorum--container`. */
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
/* Subheading of the collections section in the archive */
.quorum--workplace-subheading {
    font-size: var(--quorum-text-md);
    font-weight: 600;
    margin: var(--quorum-space-md) 0 0;
}
.quorum--workplace-meta  { font-size: var(--quorum-text-sm); color: var(--quorum-muted); margin: 0; }

.quorum--workplace-state {
    padding: var(--quorum-space-sm) var(--quorum-space-md);
    border-radius: var(--quorum-radius);
    background: color-mix(in srgb, var(--quorum-petrol) 6%, var(--quorum-bg));
    margin: 0;
    font-size: var(--quorum-text-md);
}
.quorum--workplace-state--error {
    background: color-mix(in srgb, var(--quorum-error) 8%, var(--quorum-bg));
    color: var(--quorum-error);
}

/* Empty states use the same hero look as Archive in the course-app —
   `var(--quorum-hero-*)` tokens come from _studip-tokens.scss. Active and
   archive variants share the background; the active state also shows a CTA
   below the Aurora divider. */
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
/* `.quorum--aurora-divider` is defined as a global utility in
   `resources/scss/_studip-tokens.scss` — only a local margin override here for
   the tighter empty-state spacing. */
.quorum--aurora-divider { margin: var(--quorum-space-xs) 0; }

/* Title on top: slim, reads as a casual hint. */
.quorum--empty-title {
    margin: 0;
    font-weight: 400;
    font-size: var(--quorum-text-md);
    color: inherit;
    opacity: 0.85;
}
/* CTA below: bold — the eye should land there. */
.quorum--workplace-empty-hint {
    margin: 0;
    font-size: var(--quorum-text-lg);
    font-weight: 700;
    color: inherit;
}
/* Demo onboarding: its own action row BELOW the hero box — muted hint text on
   the left, Stud.IP standard button on the right. The button deliberately sits
   outside the box. */
.quorum--workplace-empty-demo {
    margin-block-start: var(--quorum-space-md);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--quorum-space-sm) var(--quorum-space-md);
    font-size: var(--quorum-text-sm);
    color: var(--quorum-muted);

    span { flex: 1 1 16rem; }
    .button { flex: 0 0 auto; }
}

.quorum--workplace-cards {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: var(--quorum-space-md);
    /* Mobile = one column, desktop = multiple columns at min 280px, max 1fr.
       The container max-width in the app wrapper already caps the count. */
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
}

/* The poll card look (`.quorum--workplace-card*`, pills, bubble, stretched
   link, footer, HC) now lives in `resources/scss/_poll-card.scss` — ONE
   source, identical in the course tab. Loaded globally via main.js. */
</style>
