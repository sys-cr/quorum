<template>
    <main class="quorum--surveys-index">
        <header>
            <h1>{{ t('surveys.heading') }}</h1>
        </header>

        <p v-if="votingStore.error" class="quorum--voting-error" role="alert">
            <span>{{ t('voting.actionError') }}</span>
            <button type="button" class="button" @click="votingStore.error = null">
                {{ t('voting.close') }}
            </button>
        </p>

        <p v-if="store.loading" class="quorum--empty-title" role="status">
            {{ t('surveys.loading') }}
        </p>

        <div v-else-if="store.error" class="quorum--hero-empty" role="alert">
            <p class="quorum--empty-title">{{ t('surveys.errorLoading') }}</p>
            <button type="button" class="button" @click="store.loadAll()">
                {{ t('surveys.retry') }}
            </button>
        </div>

        <div v-else-if="store.isEmpty" class="quorum--hero-empty">
            <p class="quorum--empty-title">{{ t('surveys.empty') }}</p>
        </div>

        <ul v-else class="quorum--cards quorum--surveys">
            <li
                v-for="(poll, index) in store.polls"
                :key="poll.id"
                class="quorum--workplace-card"
                :class="[`is-${stateOf(poll)}`, accentClass(index)]"
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
                          :aria-label="t('archive.responsesAria', { n: poll.response_count ?? 0 }, poll.response_count ?? 0)">
                        {{ poll.response_count ?? 0 }}
                    </span>
                </div>

                <p class="quorum--workplace-card-question">
                    <a class="quorum--card-open"
                       :href="resultsUrl(poll.id)"
                       :aria-label="t('surveys.openResultsAria', { title: poll.question })">{{ poll.question }}</a>
                </p>

                <p class="quorum--workplace-card-seminar">
                    <time :datetime="isoOf(poll.mkdate)">{{ formatDate(poll.mkdate) }}</time>
                </p>

                <div class="quorum--workplace-card-footer">
                    <span class="quorum--workplace-card-link-placeholder"></span>
                    <QuorumActionMenu
                        :actions="actionsFor(poll)"
                        :label="t('surveys.actionsAria', { title: poll.question })"
                        :menu-title="t('surveys.actionsMenuTitle')"
                        @select="onAction($event, poll)"
                    />
                </div>
            </li>
        </ul>

        <footer class="button-group quorum--footer-actions">
            <button type="button" class="button accept" @click="onAdd">
                {{ t('surveys.add') }}
            </button>
        </footer>

        <QuorumDialog
            v-if="confirmGroup"
            :question="t('surveys.confirmRemove', { title: confirmGroup.question })"
            @confirm="doRemove"
            @close="confirmGroup = null"
        />

        <QrCodeDialog
            v-if="qrPoll"
            :poll-title="qrPoll.question"
            :poll-url="qrPoll.join_url"
            @close="qrPoll = null"
        />
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSurveysStore } from '../stores/useSurveysStore.js'
import { useVotingStore } from '../stores/useVotingStore.js'
import { pluginUrl } from '../../pluginUrl.js'
import QuorumDialog from '@/components/QuorumDialog.vue'
import QuorumActionMenu from '@/components/QuorumActionMenu.vue'
import QrCodeDialog from '@/components/QrCodeDialog.vue'
import { pollTypeLabel } from '@/pollTypeLabel.js'

// Definition download (question+type+options as JSON) — browser download via
// Content-Disposition; the exported file can be re-imported via survey import.
const downloadUrl = (id) => `${pluginUrl()}api/download/${encodeURIComponent(id)}`

const emit          = defineEmits(['add', 'edit', 'duplicate', 'remove'])
const { t, locale } = useI18n()
const typeLabel     = (type) => pollTypeLabel(t, type)
const store         = useSurveysStore()
const votingStore   = useVotingStore()
const confirmGroup  = ref(null)
const qrPoll        = ref(null)
// IDs with a lifecycle action currently running (double-click protection).
const busyIds       = ref(new Set())

onMounted(() => {
    if (store.isEmpty && !store.loading) store.loadAll()
})

// Quorum returns mkdate as a Unix timestamp (seconds) — convert to JS ms.
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
    }).format(d)
}

// Deep-link to the workplace full-page forms (CRUD is not rebuilt in the
// course-app). Base URL + cid come from the mount `<div>` in the PHP view
// (`#quorum-course-app[data-plugin-url][data-cid]`).
const navigate = (path) => {
    if (typeof window === 'undefined') return
    const r = document.getElementById('quorum-course-app')
    const pluginUrl = r?.dataset?.pluginUrl ?? ''
    if (!pluginUrl) return
    // `data-plugin-url` comes without a trailing slash (`…/quorumstudipplugin`);
    // without normalization the concatenation becomes
    // `…/quorumstudippluginworkplace/new` → 404/access denied.
    const base = pluginUrl.endsWith('/') ? pluginUrl : `${pluginUrl}/`
    window.location.href = `${base}${path}`
}

// Collection accent rotates cyclically through the avatar palette.
const ACCENTS = ['acc-petrol', 'acc-green', 'acc-magenta', 'acc-brand', 'acc-dark-violet']
const accentClass = (i) => ACCENTS[i % ACCENTS.length]

// cid from the mount `<div>` — passed as a query param to the workplace forms
// so their controller activates the COURSE frame (header/tabs/sidebar) instead
// of the workplace frame (otherwise the course context is lost on the form
// pages). Applies to create, edit AND duplicate.
const courseId = () => {
    const r = typeof window !== 'undefined' ? document.getElementById('quorum-course-app') : null
    return r?.dataset?.cid ?? ''
}
const withCid = (path) => {
    const cid = courseId()
    return cid ? `${path}${path.includes('?') ? '&' : '?'}cid=${encodeURIComponent(cid)}` : path
}

const onAdd = () => {
    emit('add')
    navigate(withCid('workplace/new'))
}
// Edit/duplicate → workplace forms, in the course frame.
const onEdit      = (p) => { emit('edit', p);      navigate(withCid(`workplace/edit/${p.id}`)) }
const onDuplicate = (p) => { emit('duplicate', p); navigate(withCid(`workplace/restart/${p.id}`)) }
const onRemove    = (p) => { confirmGroup.value = p }

// Status derivation as in the workplace list: check `archived_at` first (an
// archived poll must not show "running" despite a set `is_active`).
const stateOf = (p) => p.archived_at ? 'archived' : (p.is_active ? 'running' : 'paused')
// Status pill as in the workplace (same labels → same card).
const statusLabel = (p) => ({
    running:  t('workplace.statusRunning'),
    paused:   t('workplace.statusPaused'),
    archived: t('workplace.statusArchived'),
}[stateOf(p)])

// Present opens the workplace presenter full page in a new tab (projector).
// Base URL from the mount `<div>` (like `navigate`).
const presentUrl = (id) => {
    const r = typeof window !== 'undefined' ? document.getElementById('quorum-course-app') : null
    const u = r?.dataset?.pluginUrl ?? ''
    if (!u) return ''
    const base = u.endsWith('/') ? u : `${u}/`
    return `${base}workplace/present_poll/${encodeURIComponent(id)}`
}

// Results detail: the same list/detail view as on the workplace
// (`workplace/results`), within the course frame (cid). ONE results view for
// both contexts — the card title and the "Results" action point here.
const resultsUrl = (id) => {
    const r = typeof window !== 'undefined' ? document.getElementById('quorum-course-app') : null
    const u = r?.dataset?.pluginUrl ?? ''
    if (!u) return ''
    const base = u.endsWith('/') ? u : `${u}/`
    return `${base}${withCid(`workplace/results/${encodeURIComponent(id)}`)}`
}

// Full card action menu on parity with the workplace list (`WorkplaceIndex`),
// status-dependent. Result display, compare and archive exist natively in the
// course-app; edit/restart/present go via deep link into the course frame of
// the workplace full pages.
const actionsFor = (p) => {
    const state = stateOf(p)
    const items = [
        { id: 'edit',    label: t('surveys.actionEdit') },
        { id: 'results', label: t('surveys.actionResults') },
    ]
    if (state !== 'archived') items.push({ id: 'present', label: t('surveys.actionPresent') })
    if (p.join_url)           items.push({ id: 'share',   label: t('surveys.actionShare') })
    items.push({ id: 'download', label: t('surveys.actionDownload') })
    if ((p.children_count ?? 0) > 0 && state !== 'archived') {
        items.push({ id: 'compare', label: t('surveys.actionCompare') })
    }
    if (state === 'running') {
        items.push({ id: 'finish',  label: t('surveys.actionFinish') })
        items.push({ id: 'archive', label: t('surveys.actionArchive') })
    } else if (state === 'paused') {
        items.push({ id: 'start',   label: t('surveys.actionStart') })
        items.push({ id: 'restart', label: t('surveys.actionRestart') })
        items.push({ id: 'archive', label: t('surveys.actionArchive') })
    } else {
        items.push({ id: 'unarchive', label: t('surveys.actionUnarchive') })
        items.push({ id: 'remove',    label: t('surveys.actionDelete'), destructive: true })
    }
    return items
}

// Lifecycle calls the endpoint (co-teaching authorized) and reloads the
// overview afterwards — the voting store has no flat list state. Errors land in
// the `votingStore.error` banner (never silently swallowed).
const runLifecycle = async (p, fn) => {
    if (busyIds.value.has(p.id)) return
    busyIds.value.add(p.id)
    try {
        await votingStore[fn](p.id)
        await store.loadAll()
    } catch {
        /* votingStore.error is rendered as a banner above. */
    } finally {
        busyIds.value.delete(p.id)
    }
}

// Card navigation in a new tab (present/beamer).
const openResults = (p) => {
    if (typeof window === 'undefined') return
    const u = resultsUrl(p.id)
    if (u) window.location.href = u
}
const openPresent = (p) => {
    if (typeof window === 'undefined') return
    const u = presentUrl(p.id)
    if (u) window.open(u, '_blank', 'noopener')
}
const openDownload = (p) => {
    if (typeof window !== 'undefined') window.location.href = downloadUrl(p.id)
}

// Dispatcher as a lookup map (one action id → one handler) instead of a long
// switch chain — keeps the McCabe complexity flat.
const ACTION_HANDLERS = {
    edit:      (p) => onEdit(p),
    results:   (p) => openResults(p),
    present:   (p) => openPresent(p),
    share:     (p) => { qrPoll.value = p },
    download:  (p) => openDownload(p),
    compare:   (p) => navigate(withCid(`workplace/compare/${p.id}`)),
    restart:   (p) => onDuplicate(p),
    start:     (p) => runLifecycle(p, 'startVoting'),
    finish:    (p) => runLifecycle(p, 'stopVoting'),
    archive:   (p) => runLifecycle(p, 'archiveVoting'),
    unarchive: (p) => runLifecycle(p, 'unarchiveVoting'),
    remove:    (p) => onRemove(p),
}
const onAction = (action, p) => ACTION_HANDLERS[action.id]?.(p)
const doRemove    = async () => {
    const p = confirmGroup.value
    confirmGroup.value = null
    try {
        await votingStore.removeVoting(p.id)
        await store.loadAll()
        emit('remove', p)
    } catch {
        // Don't swallow the error — votingStore.error is rendered as a banner
        // above; do NOT show the list as deleted.
    }
}
</script>

<style scoped lang="scss">
/* Error banner for action failures (e.g. delete failed). */
.quorum--voting-error {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--quorum-space-sm);
    padding: var(--quorum-space-sm) var(--quorum-space-md);
    margin-block-end: var(--quorum-space-md);
    border: 1px solid var(--quorum-error);
    border-radius: var(--quorum-radius);
    background: color-mix(in srgb, var(--quorum-error) 10%, var(--quorum-bg));
    color: var(--quorum-fg);
}

.quorum--surveys-index {
    /* Horizontal padding comes from the app container. */
    padding-block: var(--quorum-space-md);

    h1 {
        font-size: var(--quorum-text-xl);
        margin-block-end: var(--quorum-space-md);
        color: var(--quorum-fg);
    }
}

/* Card grid: a single `auto-fill` with `min(280px, 100%)` covers mobile
   (1 column) and desktop (multi-column) without a separate breakpoint. */
.quorum--cards {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: var(--quorum-space-md);
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
}

/* The poll card (`.quorum--workplace-card*`, pills, bubble, stretched link)
   is now provided by the shared `resources/scss/_poll-card.scss` — identical
   to the workplace (loaded globally via course-app/App.vue). */

.quorum--footer-actions {
    padding-block: var(--quorum-space-sm);
}

.quorum--empty {
    text-align: center;
    padding-block: var(--quorum-space-xl);
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

.quorum--col-actions { text-align: end; }
.quorum--actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    justify-content: flex-end;
}

</style>
