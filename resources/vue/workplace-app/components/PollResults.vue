<template>
    <section class="quorum--workplace quorum--container quorum--container--narrow" :aria-label="t('workplace.resultsHeading')">
        <StudipMessageBox v-if="loading" type="info" :hide-close="true">
            {{ t('workplace.loading') }}
        </StudipMessageBox>

        <StudipMessageBox v-else-if="error" type="error" :hide-close="true">
            {{ t('workplace.errorLoading') }}
            <button type="button" class="button" @click="load">{{ t('workplace.retry') }}</button>
        </StudipMessageBox>

        <template v-else-if="poll">
            <header class="quorum--workplace-header">
                <div class="quorum--results-headline">
                    <h1 class="quorum--workplace-title quorum--results-question">{{ poll.question }}</h1>
                    <p class="quorum--workplace-meta">
                        <span class="quorum--results-status" :data-status="state">{{ statusLabel }}</span>
                        · {{ t('voting.samples', { n: poll.response_count }, poll.response_count) }}
                    </p>
                </div>

                <!-- All management actions in one Stud.IP-style action menu
                     (like the cards), instead of a button bar. -->
                <QuorumActionMenu
                    class="quorum--results-menu"
                    :actions="actionsFor"
                    :label="t('workplace.actionsAria', { question: poll.question })"
                    :menu-title="t('workplace.actions')"
                    :busy="busy"
                    direction="down"
                    @select="runAction"
                />
            </header>

            <StudipMessageBox v-if="actionError" type="error" :hide-close="true">
                {{ t('workplace.errorLoading') }}
            </StudipMessageBox>

            <hr class="quorum--aurora-divider" aria-hidden="true">

            <!-- Deliberately plain (user feedback): a simple Stud.IP table
                 instead of the chart switcher — the presenter covers visuals. -->
            <p v-if="(poll.response_count ?? 0) === 0" class="quorum--results-empty">
                {{ t('workplace.resultsEmpty') }}
            </p>

            <!-- Free text: the answers as a list, each answer removable
                 (post-moderation; the blocklist filters upfront). -->
            <template v-else-if="poll.type === 'freitext'">
                <StudipMessageBox v-if="moderationError" type="error" :hide-close="true">
                    {{ t('workplace.moderationError') }}
                </StudipMessageBox>
                <ul class="quorum--results-freitext">
                    <li v-for="answer in freitextResponses" :key="answer.id">
                        <span class="quorum--results-freitext-text">{{ answer.text }}</span>
                        <button
                            type="button"
                            class="button quorum--results-moderate"
                            :aria-label="t('workplace.moderationRemoveAria')"
                            @click="confirmModeration.answer = answer"
                        >{{ t('workplace.moderationRemove') }}</button>
                    </li>
                </ul>
            </template>

            <!-- Matrix: rows × scale with a count per cell -->
            <table v-else-if="poll.type === 'matrix'" class="default">
                <thead>
                    <tr>
                        <th></th>
                        <th v-for="s in matrixScale" :key="s.id" class="quorum--results-num">{{ s.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in matrixRows" :key="row.id">
                        <th scope="row">{{ row.label }}</th>
                        <td v-for="s in matrixScale" :key="s.id" class="quorum--results-num">
                            {{ matrixCounts[row.id]?.[s.id] ?? 0 }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Choice types (mc/scales/emoji/multi): option · votes · percent -->
            <table v-else class="default">
                <thead>
                    <tr>
                        <th>{{ t('results.tableOption') }}</th>
                        <th class="quorum--results-num">{{ t('results.tableVotes') }}</th>
                        <th class="quorum--results-num">%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in tableRows" :key="row.id">
                        <td>{{ row.label }}</td>
                        <td class="quorum--results-num">{{ row.votes }}</td>
                        <td class="quorum--results-num">{{ row.percent }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="quorum--results-backlink">
                <a class="button" :href="backUrl">{{ t('workplace.resultsBack') }}</a>
            </p>
        </template>

        <QuorumDialog
            v-if="confirmModeration.answer"
            :title="t('workplace.moderationConfirmTitle')"
            :close-text="t('workplace.cancel')"
            :confirm-text="t('workplace.moderationConfirmAccept')"
            confirm-class="delete"
            @confirm="removeAnswer"
            @close="confirmModeration.answer = null"
        >
            <template #dialogContent>
                {{ t('workplace.moderationConfirmBody', { text: confirmModeration.answer.text }) }}
            </template>
        </QuorumDialog>

        <QuorumDialog
            v-if="deleteOpen && poll"
            :title="t('workplace.confirmDeleteTitle')"
            :close-text="t('workplace.cancel')"
            :confirm-text="t('workplace.confirmDeleteAccept')"
            confirm-class="delete"
            @confirm="performDelete"
            @close="deleteOpen = false"
        >
            <template #dialogContent>
                {{ t('workplace.confirmDeleteBody', { question: poll.question, count: poll.response_count }) }}
            </template>
        </QuorumDialog>

        <QrCodeDialog
            v-if="qrOpen && poll"
            :poll-title="poll.question"
            :poll-url="poll.join_url"
            :short-url="poll.short_url"
            @close="qrOpen = false"
        />
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import QuorumDialog from '@/components/QuorumDialog.vue'
import QrCodeDialog from '@/components/QrCodeDialog.vue'
import QuorumActionMenu from '@/components/QuorumActionMenu.vue'

/**
 * Retrospective results view in the workplace (per user feedback): review
 * and download (CSV + Stud.IP PDF) the results of any own poll — running,
 * paused or archived — without a course binding or a presenter detour.
 * Deliberately plain: simple table/list, no chart, one fetch, no SSE —
 * this page is a review, not a live monitor.
 */

const { t } = useI18n()

const root      = typeof document !== 'undefined'
    ? document.getElementById('quorum-workplace-app')
    : null
const pluginUrl = root?.dataset?.pluginUrl ?? ''
const pollId    = root?.dataset?.pollId ?? ''
const csrfToken = root?.dataset?.csrf ?? ''

const poll    = ref(null)
const loading = ref(false)
const error   = ref(false)

const freitextResponses = ref([])
const matrixCounts      = ref({})

// Free-text moderation: the answer whose removal is being confirmed.
const confirmModeration = reactive({ answer: null })
const moderationError   = ref(false)

// Management bar (detail page = card menu as visible actions).
const busy        = ref(false)   // locks buttons during a lifecycle call
const deleteOpen  = ref(false)   // delete confirmation open
const qrOpen      = ref(false)   // QR dialog open
const actionError = ref(false)   // banner on a failed action

// Trails full pages (wizard/dialogs) — same routes as the card menu.
const actionUrl = (path) => `${pluginUrl}/workplace/${path}/${encodeURIComponent(pollId)}`

const load = async () => {
    loading.value = true
    error.value   = false
    try {
        const res = await fetch(`${pluginUrl}/api/poll/${encodeURIComponent(pollId)}`, {
            credentials: 'same-origin',
            headers:     { 'Accept': 'application/json' },
        })
        if (!res.ok) throw new Error(`http_${res.status}`)
        poll.value = await res.json()
        // Free text via the moderation endpoint (provides response IDs for
        // removal) — the anonymous ID-less variant is used by the course tab.
        if (poll.value.type === 'freitext') await loadExtra('freitext_moderation', (d) => { freitextResponses.value = d.responses ?? [] })
        if (poll.value.type === 'matrix')   await loadExtra('matrix_counts', (d) => { matrixCounts.value = d.counts ?? {} })
    } catch {
        error.value = true
    } finally {
        loading.value = false
    }
}

// Free-text/matrix data comes from dedicated endpoints (as in the course
// tab). Errors here flip the whole page into the error state — without the
// data there would be nothing meaningful to show.
async function loadExtra(endpoint, apply) {
    const res = await fetch(`${pluginUrl}/api/${endpoint}/${encodeURIComponent(pollId)}`, { credentials: 'same-origin' })
    if (!res.ok) throw new Error(`http_${res.status}`)
    apply(await res.json())
}

onMounted(load)

/**
 * Free-text moderation: removes the confirmed answer permanently (POST with
 * CSRF token). On success it disappears from the list + counter; errors show
 * the banner above the list (a retry is possible at any time).
 */
const removeAnswer = async () => {
    const answer = confirmModeration.answer
    confirmModeration.answer = null
    if (!answer) return
    moderationError.value = false
    try {
        const res = await fetch(`${pluginUrl}/api/freitext_response_delete/${encodeURIComponent(answer.id)}`, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
        })
        if (!res.ok) throw new Error(`http_${res.status}`)
        freitextResponses.value = freitextResponses.value.filter(r => r.id !== answer.id)
        if (poll.value) {
            poll.value.response_count = Math.max(0, (poll.value.response_count ?? 1) - 1)
        }
    } catch {
        moderationError.value = true
    }
}

/**
 * Lifecycle action (start/finish/archive/unarchive) via POST with CSRF; the
 * page then reloads the poll (status/buttons update). Same flat Trails routes
 * as the card menu; errors show the banner.
 */
const doLifecycle = async (op) => {
    if (busy.value) return
    busy.value        = true
    actionError.value = false
    try {
        const res = await fetch(`${pluginUrl}/api/${op}/${encodeURIComponent(pollId)}`, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
        })
        if (!res.ok) throw new Error(`http_${res.status}`)
        await load()
    } catch {
        actionError.value = true
    } finally {
        busy.value = false
    }
}

/**
 * Permanent deletion (DELETE with CSRF). Afterwards there is nothing left to
 * show → back to the list (active or archive view, depending on the prior state).
 */
const performDelete = async () => {
    deleteOpen.value  = false
    busy.value        = true
    actionError.value = false
    try {
        const res = await fetch(`${pluginUrl}/api/delete/${encodeURIComponent(pollId)}`, {
            method:      'DELETE',
            credentials: 'same-origin',
            headers:     { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
        })
        if (!res.ok) throw new Error(`http_${res.status}`)
        if (typeof window !== 'undefined') window.location.href = backUrl.value
    } catch {
        actionError.value = true
        busy.value        = false
    }
}

// Three states derived from two orthogonal fields (as in WorkplaceIndex).
const state = computed(() => {
    if (!poll.value) return 'paused'
    if (poll.value.archived_at) return 'archived'
    return poll.value.is_active ? 'running' : 'paused'
})
const statusLabel = computed(() => ({
    running:  t('workplace.statusRunning'),
    paused:   t('workplace.statusPaused'),
    archived: t('workplace.statusArchived'),
}[state.value]))

const matrixRows  = computed(() => poll.value?.options?.rows  ?? [])
const matrixScale = computed(() => poll.value?.options?.scale ?? [])

// Choice types: option · votes · percent (one decimal, matching the CSV).
const tableRows = computed(() => {
    const opts   = Array.isArray(poll.value?.options) ? poll.value.options : []
    const counts = poll.value?.counts ?? {}
    const total  = Object.values(counts).reduce((a, b) => a + (Number(b) || 0), 0)
    return opts.map(o => {
        const votes = Number(counts[o.id] ?? 0)
        return {
            id:      o.id,
            label:   o.label,
            votes,
            percent: total > 0 ? `${((votes / total) * 100).toFixed(1)} %` : '0.0 %',
        }
    })
})

const csvUrl  = computed(() => `${pluginUrl}/api/export/${encodeURIComponent(pollId)}?format=csv`)
const pdfUrl  = computed(() => `${pluginUrl}/api/export/${encodeURIComponent(pollId)}?format=pdf`)
const backUrl = computed(() => {
    const view = state.value === 'archived' ? 'archive' : 'active'
    return `${pluginUrl}/workplace/index?view=${view}`
})

/**
 * Action list for the menu — state-/property-dependent, in the same order as
 * the card menu (only "Results" is omitted: we are already here).
 */
const actionsFor = computed(() => {
    if (!poll.value) return []
    const s     = state.value
    const items = []
    items.push({ id: 'edit', label: t('workplace.actionEdit') })
    if (s !== 'archived')   items.push({ id: 'present', label: t('workplace.actionPresent') })
    if (poll.value.join_url) items.push({ id: 'share', label: t('workplace.actionShare') })
    if ((poll.value.children_count ?? 0) > 0 && s !== 'archived') {
        items.push({ id: 'compare', label: t('workplace.actionCompare') })
    }
    items.push({ id: 'assign',   label: t('workplace.actionAssign') })
    items.push({ id: 'csv',      label: t('voting.exportCsv') })
    items.push({ id: 'pdf',      label: t('workplace.exportPdf') })
    items.push({ id: 'download', label: t('workplace.actionDownload') })
    if (s === 'running') {
        items.push({ id: 'finish',  label: t('workplace.actionFinish') })
        items.push({ id: 'archive', label: t('workplace.actionArchive') })
    } else if (s === 'paused') {
        items.push({ id: 'start',   label: t('workplace.actionStart') })
        items.push({ id: 'restart', label: t('workplace.actionRestart') })
        items.push({ id: 'archive', label: t('workplace.actionArchive') })
    } else {
        items.push({ id: 'unarchive', label: t('workplace.actionUnarchive') })
        items.push({ id: 'delete',    label: t('workplace.actionDelete') })
    }
    return items
})

/**
 * Menu dispatcher: navigation/download actions directly, lifecycle via the
 * `/api` endpoints (`doLifecycle`), QR/delete open their dialog.
 */
const runAction = (action) => {
    const go = (url, blank = false) => {
        if (typeof window === 'undefined') return
        if (blank) window.open(url, '_blank', 'noopener')
        else window.location.href = url
    }
    switch (action.id) {
        case 'edit':      go(actionUrl('edit')); break
        case 'present':   go(actionUrl('present_poll'), true); break
        case 'compare':   go(actionUrl('compare')); break
        case 'restart':   go(actionUrl('restart')); break
        case 'assign':    go(`${pluginUrl}/workplace/collection_assign/${encodeURIComponent(pollId)}`); break
        case 'csv':       go(csvUrl.value); break
        case 'pdf':       go(pdfUrl.value, true); break
        case 'download':  go(`${pluginUrl}/api/download/${encodeURIComponent(pollId)}`); break
        case 'share':     qrOpen.value = true; break
        case 'delete':    deleteOpen.value = true; break
        case 'finish':
        case 'start':
        case 'archive':
        case 'unarchive': doLifecycle(action.id); break
    }
}
</script>

<style scoped lang="scss">
.quorum--workplace {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-md);
    color: var(--quorum-fg);
    padding-block: var(--quorum-space-md);
}
.quorum--workplace-header {
    display: flex;
    /* no wrap: the action menu stays top-right next to the title instead of
       slipping down-left for long questions. */
    flex-wrap: nowrap;
    gap: var(--quorum-space-sm) var(--quorum-space-md);
    align-items: flex-start;
    justify-content: space-between;
}
.quorum--results-headline {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-xs);
    min-inline-size: 0;
    flex: 1 1 auto;
}
.quorum--results-menu { flex: 0 0 auto; }
.quorum--results-backlink { margin: 0; }
.quorum--workplace-title { font-size: var(--quorum-text-lg); font-weight: 600; margin: 0; }
.quorum--results-question {
    white-space: pre-line;
    overflow-wrap: anywhere;
}
.quorum--workplace-meta { font-size: var(--quorum-text-sm); color: var(--quorum-muted); margin: 0; }

.quorum--results-status {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;

    &[data-status='running']  { color: var(--quorum-petrol); }
    &[data-status='archived'] { color: var(--quorum-muted); }
}

.quorum--results-num { text-align: end; }

.quorum--results-empty {
    margin: 0;
    color: var(--quorum-muted);
}

.quorum--results-freitext {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;

    li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--quorum-space-sm);
        padding: 0.4rem 0.6rem;
        border: 1px solid var(--quorum-border);
        border-radius: var(--quorum-radius);
    }
}
.quorum--results-freitext-text {
    white-space: pre-line;
    overflow-wrap: anywhere;
}
.quorum--results-moderate {
    min-inline-size: 0;
    flex-shrink: 0;
}

</style>
