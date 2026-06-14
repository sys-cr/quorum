import { defineStore } from 'pinia'
import { pluginUrl } from '../../pluginUrl.js'

/**
 * Pinia store for the teacher overview of course polls (flat list).
 *
 * Quorum API (flat poll list for the current course):
 *   GET    {PLUGIN_URL}api/course_polls?cid={CID}   → { cid, polls:[…] }
 *
 * Create/edit/duplicate happen via deep-links into the workplace forms (see
 * SurveysIndex.vue → onAdd etc.), not here. Stop/delete live in useVotingStore.
 *
 * CSRF: Stud.IP sets `STUDIP.CSRF_TOKEN.value` globally; state-changing
 * requests attach it as an `X-CSRF-Token` header.
 */

const coursePollsUrl = () => {
    // cid PRIMARILY from the mount `data-cid` — `globalThis.STUDIP.CID` is not
    // set in the full-page plugin context (undefined), which would otherwise
    // yield `cid=` (empty) → 400 → permanently empty list even when the course
    // has polls. STUDIP.CID only as fallback.
    const mount = typeof document !== 'undefined'
        ? document.getElementById('quorum-course-app')
        : null
    const cid = encodeURIComponent(mount?.dataset?.cid ?? globalThis.STUDIP?.CID ?? '')
    return `${pluginUrl()}api/course_polls?cid=${cid}`
}

/* Prefer reading the error text from the API's JSON body ({ error, message }) —
   the server message is user-readable; the HTTP status is only a fallback. */
const errorText = async (res) => {
    try {
        const body = await res.json()
        if (typeof body?.message === 'string' && body.message !== '') return body.message
    } catch { /* no JSON body */ }
    return `HTTP ${res.status}: ${res.statusText}`
}

export const useSurveysStore = defineStore('surveys', {
    state: () => ({
        polls:   [],
        loading: false,
        error:   null,
    }),

    getters: {
        count:   (s) => s.polls.length,
        isEmpty: (s) => s.polls.length === 0,
        byId:    (s) => (id) => s.polls.find(p => p.id === id) ?? null,
    },

    actions: {
        async loadAll() {
            this.loading = true
            this.error   = null
            try {
                const res = await fetch(coursePollsUrl(), { method: 'GET', credentials: 'same-origin' })
                if (!res.ok) {
                    this.error = await errorText(res)
                    this.polls = []
                    return
                }
                const data = await res.json()
                this.polls = Array.isArray(data?.polls) ? data.polls : []
            } catch (e) {
                this.error = e.message ?? String(e)
                this.polls = []
            } finally {
                this.loading = false
            }
        },
    },
})
