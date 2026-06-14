import { defineStore } from 'pinia'
import { pluginUrl } from '../../pluginUrl.js'

/**
 * Pinia store for poll detail + compare + archive (teacher view).
 *
 * Quorum API (no start/end time-window model; uses `is_active` (bool) +
 * `archived_at`):
 *   GET    {PLUGIN_URL}api/poll/{id}            → poll detail view
 *   GET    {PLUGIN_URL}api/course_polls?cid=…   → { cid, polls:[…] } (flat)
 *   POST   {PLUGIN_URL}api/finish/{id}          → finish (is_active:false)
 *   DELETE {PLUGIN_URL}api/delete/{id}          → delete incl. answers
 *
 * Model mapping: "running" = `is_active === true`; "finished" =
 * `is_active === false`. The question is `question`, options are `options`,
 * the short URL is `join_url`.
 */

const pollUrl        = (id) => `${pluginUrl()}api/poll/${id}`
const finishUrl      = (id) => `${pluginUrl()}api/finish/${id}`
const deleteUrl      = (id) => `${pluginUrl()}api/delete/${id}`
const coursePollsUrl = () => {
    // cid PRIMARILY from the mount `data-cid` — `globalThis.STUDIP.CID` is not
    // set in the full-page plugin context (undefined) → otherwise empty cid →
    // 400 → empty poll list. STUDIP.CID only as fallback (e.g. tests).
    const mount = typeof document !== 'undefined'
        ? document.getElementById('quorum-course-app')
        : null
    const cid = encodeURIComponent(mount?.dataset?.cid ?? globalThis.STUDIP?.CID ?? '')
    return `${pluginUrl()}api/course_polls?cid=${cid}`
}

const csrfHeaders = () => ({
    'Content-Type':  'application/json',
    'Accept':        'application/json',
    'X-CSRF-Token':  globalThis.STUDIP?.CSRF_TOKEN?.value ?? '',
})

// "Running" = is_active === true. Quorum has no start/end time window.
const isPollRunning = (poll) => poll?.is_active === true

/* Prefer reading the error text from the API's JSON body ({ error, message }) —
   the server message is user-readable ("Diese Abstimmung existiert nicht.");
   the bare HTTP status is only a fallback without a body. */
const errorText = async (res) => {
    try {
        const body = await res.json()
        if (typeof body?.message === 'string' && body.message !== '') return body.message
    } catch { /* no JSON body */ }
    return `HTTP ${res.status}: ${res.statusText}`
}

export const useVotingStore = defineStore('voting', {
    state: () => ({
        current: null,
        archive: [],
        loading: false,
        error:   null,
    }),

    getters: {
        byId: (s) => (id) => {
            if (s.current?.id === id) return s.current
            return s.archive.find(v => v.id === id) ?? null
        },

        isRunning: (s) => (id) => {
            const v = s.current?.id === id
                ? s.current
                : s.archive.find(x => x.id === id)
            return v ? isPollRunning(v) : false
        },

        finishedVotings: (s) => s.archive.filter(v => !isPollRunning(v)),
    },

    actions: {
        async loadArchive() {
            this.loading = true
            this.error   = null
            try {
                const res = await fetch(coursePollsUrl(), { method: 'GET', credentials: 'same-origin' })
                if (!res.ok) {
                    this.error   = await errorText(res)
                    this.archive = []
                    return
                }
                const data = await res.json()
                this.archive = Array.isArray(data?.polls) ? data.polls : []
            } catch (e) {
                this.error   = e.message ?? String(e)
                this.archive = []
            } finally {
                this.loading = false
            }
        },

        async loadTwoVotings(idA, idB) {
            const fetchOne = (id) => fetch(pollUrl(id), {
                method: 'GET', credentials: 'same-origin',
            }).then(async (r) => {
                if (!r.ok) throw new Error(await errorText(r))
                return r.json()
            })

            this.loading = true
            this.error   = null
            try {
                const result = await Promise.all([fetchOne(idA), fetchOne(idB)])
                // Store loaded polls so byId()/findIn() can resolve them
                // (otherwise VotingCompare stays stuck on "loading").
                for (const v of result) {
                    if (!v?.id) continue
                    const idx = this.archive.findIndex(x => x.id === v.id)
                    if (idx >= 0) this.archive[idx] = { ...this.archive[idx], ...v }
                    else this.archive.push(v)
                }
                return result
            } catch (e) {
                this.error = e.message ?? String(e)
                throw e
            } finally {
                this.loading = false
            }
        },

        async stopVoting(id) {
            const res = await fetch(finishUrl(id), {
                method:      'POST',
                credentials: 'same-origin',
                headers:     csrfHeaders(),
            })
            if (!res.ok) {
                this.error = await errorText(res)
                throw new Error(this.error)
            }
            const body = await res.json()
            // Backend returns { ok:true, is_active:false } — mirror is_active
            // onto current/archive (running → finished).
            const isActive = body?.is_active ?? false
            if (this.current?.id === id) {
                this.current = { ...this.current, is_active: isActive }
            }
            const idx = this.archive.findIndex(v => v.id === id)
            if (idx >= 0) this.archive[idx] = { ...this.archive[idx], is_active: isActive }
            return body
        },

        async removeVoting(id) {
            const res = await fetch(deleteUrl(id), {
                method:      'DELETE',
                credentials: 'same-origin',
                headers:     csrfHeaders(),
            })
            if (!res.ok) {
                this.error = await errorText(res)
                throw new Error(this.error)
            }
            if (this.current?.id === id) this.current = null
            this.archive = this.archive.filter(v => v.id !== id)
        },

        /**
         * Generic lifecycle call for the card controls in the course tab:
         * POST {PLUGIN_URL}api/{op}/{id} (start/archive/unarchive). The
         * endpoints allow owner OR tutor in the course (co-teaching). The list
         * is held by `useSurveysStore`; on success the component reloads it
         * (this store has no flat list state of the overview).
         */
        async lifecycle(id, op, { method = 'POST' } = {}) {
            const res = await fetch(`${pluginUrl()}api/${op}/${id}`, {
                method, credentials: 'same-origin', headers: csrfHeaders(),
            })
            if (!res.ok) {
                this.error = await errorText(res)
                throw new Error(this.error)
            }
            return res.json().catch(() => ({}))
        },

        startVoting(id)     { return this.lifecycle(id, 'start') },
        archiveVoting(id)   { return this.lifecycle(id, 'archive') },
        unarchiveVoting(id) { return this.lifecycle(id, 'unarchive') },
    },
})
