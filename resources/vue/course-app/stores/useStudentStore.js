import { defineStore } from 'pinia'
import { pluginUrl } from '../../pluginUrl.js'

/**
 * Pinia store for the STUDENT view of the course tab.
 *
 * The cid source is — as in the teacher store — the mount `data-cid` of the
 * PHP view (`#quorum-course-app`); `globalThis.STUDIP.CID` is NOT set in the
 * plugin full-page context.
 *
 * Quorum API (all GET, same-origin cookies):
 *   GET {PLUGIN_URL}api/course_student_polls?cid={CID}
 *       → { active:[{id,token,question,type,join_url}],
 *           finished:[{id,question,type,mkdate}] }
 *   GET {PLUGIN_URL}api/course_student_results/{POLL_ID}
 *       → type-dependent result shape (see loadResults).
 *
 * Deliberately NO management actions. Quiz learning effect: for ended quiz
 * polls the server additionally provides `correct: [optId, …]` (ended-gated) —
 * the store passes this data through unchanged; marking the correct option
 * happens in the StudentView. NO own verdict: the student view does not know
 * the individual answer.
 */

// cid PRIMARILY from the mount `data-cid` (reliably set by the PHP view).
const courseId = () => {
    const mount = typeof document !== 'undefined'
        ? document.getElementById('quorum-course-app')
        : null
    return mount?.dataset?.cid ?? ''
}

/* Prefer reading the error text from the JSON body ({ message }) — the server
   message is user-readable; the HTTP status is only the fallback. */
const errorText = async (res) => {
    try {
        const body = await res.json()
        if (typeof body?.message === 'string' && body.message !== '') return body.message
    } catch { /* no JSON body */ }
    return `HTTP ${res.status}`
}

export const useStudentStore = defineStore('student', {
    state: () => ({
        loading: false,
        error:   null,
        active:  [],   // [{ id, token, question, type, join_url }]
        finished: [],  // [{ id, question, type, mkdate }]
        // Course collections: [{ id, name, description,
        //   active:[{id,question,type,join_url}], finished:[{id,question,type}] }]
        collections: [],
        results: {},   // pollId → { status:'loading'|'ready'|'error', data? }
    }),

    actions: {
        /**
         * Loads the course's active + finished polls. Called initially and
         * (via StudentView) by the live-refresh interval.
         */
        async loadPolls() {
            this.loading = true
            this.error   = null
            try {
                const cid = encodeURIComponent(courseId())
                const res = await fetch(`${pluginUrl()}api/course_student_polls?cid=${cid}`, {
                    method:      'GET',
                    credentials: 'same-origin',
                })
                if (!res.ok) {
                    this.error    = await errorText(res)
                    this.active   = []
                    this.finished = []
                    return
                }
                const data = await res.json()
                this.active   = Array.isArray(data?.active)   ? data.active   : []
                this.finished = Array.isArray(data?.finished) ? data.finished : []
            } catch (e) {
                this.error    = e.message ?? String(e)
                this.active   = []
                this.finished = []
            } finally {
                this.loading = false
            }
        },

        /**
         * Loads the course's running & released collections (in parallel with
         * `loadPolls`, in the same live-refresh interval). Member results are
         * loaded lazily via `loadResults(pollId)` (same IDs).
         */
        async loadCollections() {
            try {
                const cid = encodeURIComponent(courseId())
                const res = await fetch(`${pluginUrl()}api/course_student_collections?cid=${cid}`, {
                    method:      'GET',
                    credentials: 'same-origin',
                })
                if (!res.ok) { this.collections = []; return }
                const data = await res.json()
                this.collections = Array.isArray(data?.collections) ? data.collections : []
            } catch {
                this.collections = []
            }
        },

        /**
         * Loads the results of a finished poll (lazily, on expand). Stores
         * status + data under `results[pollId]`. The response shape depends on
         * the type — the component renders it accordingly.
         */
        async loadResults(pollId) {
            this.results = { ...this.results, [pollId]: { status: 'loading' } }
            try {
                const res = await fetch(
                    `${pluginUrl()}api/course_student_results/${encodeURIComponent(pollId)}`,
                    { method: 'GET', credentials: 'same-origin' },
                )
                if (!res.ok) {
                    this.results = { ...this.results, [pollId]: { status: 'error' } }
                    return
                }
                const data = await res.json()
                this.results = { ...this.results, [pollId]: { status: 'ready', data } }
            } catch {
                this.results = { ...this.results, [pollId]: { status: 'error' } }
            }
        },
    },
})
