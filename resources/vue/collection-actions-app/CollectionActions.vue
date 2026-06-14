<template>
    <span class="quorum--collection-actions">
        <QuorumActionMenu
            :actions="actions"
            :label="t('collections.actionsAria', { name })"
            :menu-title="t('workplace.actions')"
            :busy="busy"
            direction="down"
            @select="runAction"
        />

        <QrCodeDialog
            v-if="qrOpen"
            :poll-title="qrTitle"
            :poll-url="qrUrl"
            :short-url="qrShort"
            @close="qrOpen = false"
        />

        <QuorumDialog
            v-if="deleteOpen"
            :title="t('collections.confirmDeleteTitle')"
            :close-text="t('workplace.cancel')"
            :confirm-text="t('collections.confirmDeleteAccept')"
            confirm-class="delete"
            @confirm="performDelete"
            @close="deleteOpen = false"
        >
            <template #dialogContent>
                {{ t('collections.confirmDeleteBody', { name }) }}
            </template>
        </QuorumDialog>
    </span>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import QuorumActionMenu from '@/components/QuorumActionMenu.vue'
import QrCodeDialog from '@/components/QrCodeDialog.vue'
import QuorumDialog from '@/components/QuorumDialog.vue'

/**
 * All management actions of the (server-rendered) collection detail page in
 * ONE Stud.IP-style action menu — the same `QuorumActionMenu` as the cards,
 * instead of a long button bar (user request: too many buttons). Lifecycle
 * runs through the same `/api/collection_*` endpoints as the card menu (CSRF
 * header); afterwards the page reloads, because it is PHP. QR/share is a menu
 * entry here (shows the first question "from the start").
 */
const props = defineProps({
    name:         { type: String,  default: '' },
    collectionId: { type: String,  required: true },
    pluginUrl:    { type: String,  required: true },
    csrf:         { type: String,  default: '' },
    archived:     { type: Boolean, default: false },
    anyActive:    { type: Boolean, default: false },
    hasPolls:     { type: Boolean, default: false },
    presenterUrl: { type: String,  default: '' },
    editUrl:      { type: String,  default: '' },
    newPollUrl:   { type: String,  default: '' },
    downloadUrl:  { type: String,  default: '' },
    backUrl:      { type: String,  default: '' },
    qrUrl:        { type: String,  default: null },
    qrShort:      { type: String,  default: null },
    qrTitle:      { type: String,  default: '' },
})

const { t }      = useI18n()
const busy       = ref(false)
const qrOpen     = ref(false)
const deleteOpen = ref(false)

const actions = computed(() => {
    const a = []
    if (props.hasPolls) a.push({ id: 'present', label: t('workplace.actionPresent') })
    if (props.qrUrl)    a.push({ id: 'share',   label: t('workplace.actionShare') })
    if (!props.archived) {
        if (props.anyActive) {
            a.push({ id: 'finish', label: t('collections.actionFinish') })
        } else {
            a.push({ id: 'startAll',  label: t('collections.actionStartAll'),  disabled: !props.hasPolls })
            a.push({ id: 'startStep', label: t('collections.actionStartStep'), disabled: !props.hasPolls })
        }
        a.push({ id: 'addSurvey', label: t('collections.addSurvey') })
    }
    a.push({ id: 'edit',     label: t('workplace.actionEdit') })
    a.push({ id: 'download', label: t('workplace.actionDownload') })
    if (props.archived) {
        a.push({ id: 'unarchive', label: t('workplace.actionUnarchive') })
    } else {
        a.push({ id: 'archive', label: t('workplace.actionArchive') })
    }
    a.push({ id: 'delete', label: t('collections.actionDelete') })
    return a
})

// POST/DELETE to /api/collection_{op} with CSRF; on success reload the page.
const lifecycle = async (op, { method = 'POST', body = null } = {}) => {
    if (busy.value) return
    busy.value = true
    try {
        const headers = { 'Accept': 'application/json', 'X-CSRF-Token': props.csrf }
        if (body) headers['Content-Type'] = 'application/json'
        const res = await fetch(`${props.pluginUrl}/api/collection_${op}/${encodeURIComponent(props.collectionId)}`, {
            method,
            credentials: 'same-origin',
            headers,
            body: body ? JSON.stringify(body) : undefined,
        })
        if (!res.ok) throw new Error(`http_${res.status}`)
        return res
    } catch (e) {
        busy.value = false
        throw e
    }
}

const reload = () => { if (typeof window !== 'undefined') window.location.reload() }
const go     = (url, blank = false) => {
    if (typeof window === 'undefined' || !url) return
    if (blank) window.open(url, '_blank', 'noopener')
    else window.location.href = url
}

// Trigger a lifecycle operation and reload the page on success.
const runLifecycle = (op, opts) => lifecycle(op, opts).then(reload).catch(() => {})

// Dispatcher as a lookup map (one action id → one handler) instead of a switch
// chain — keeps the McCabe complexity flat.
const ACTION_HANDLERS = {
    present:   () => go(props.presenterUrl, true),
    share:     () => { qrOpen.value = true },
    edit:      () => go(props.editUrl),
    addSurvey: () => go(props.newPollUrl),
    download:  () => go(props.downloadUrl),
    delete:    () => { deleteOpen.value = true },
    startAll:  () => runLifecycle('start', { body: { mode: 'all' } }),
    startStep: () => runLifecycle('start', { body: { mode: 'step' } }),
    finish:    () => runLifecycle('finish'),
    archive:   () => runLifecycle('archive'),
    unarchive: () => runLifecycle('unarchive'),
}
const runAction = (action) => ACTION_HANDLERS[action.id]?.()

const performDelete = () => {
    deleteOpen.value = false
    lifecycle('delete', { method: 'DELETE' }).then(() => go(props.backUrl)).catch(() => {})
}
</script>
