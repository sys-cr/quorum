import { defineStore } from 'pinia'
import { pluginUrl } from '../../pluginUrl.js'

/**
 * State of the student polls app.
 *
 * Endpoints (flat, plugin base from `STUDIP.PLUGIN_URL` = mount data-plugin-url):
 *   GET  {base}api/polls/{token}
 *   GET  {base}api/poll_status/{token}   ← live sync
 *   POST {base}api/responses/{pollId}
 *
 * Auth: anonymous. GET uses `credentials: 'omit'`; the vote (POST) is secured
 * server-side via a **same-origin check** (no CSRF token — the endpoint runs
 * for Nobody sessions, see `api.php` `responses_action`).
 *
 * Live sync: the page polls the lightweight status endpoint at an interval.
 * When the owner starts voting (presenter or course tab), the open participant
 * page switches to the active question without a reload — including
 * auto-follow to the next question of the same collection (`active_token`).
 */

// Interval handles deliberately live outside the Pinia state (not
// serializable, do not belong in the devtools). Exactly one watch exists per
// app instance.
let watchTimer = null
let visibilityHandler = null

const STATUS_INTERVAL_MS = 4000

// Persistent duplicate-vote guard per question. Replaces the former
// server-side IP rate limit: once a question has been answered, the browser
// remembers it locally (localStorage), so a reload cannot vote again.
// Deliberately client-side only — the server stores no IP/client trait (data
// minimization). Best effort: anyone clearing localStorage or using another
// device can vote again — as with any anonymous poll.
const VOTE_KEY_PREFIX = 'quorum.voted.'

function readVote(pollId) {
    if (!pollId || typeof localStorage === 'undefined') return null
    try {
        const raw = localStorage.getItem(VOTE_KEY_PREFIX + pollId)
        return raw === null ? null : JSON.parse(raw)
    } catch {
        return null
    }
}

function persistVote(pollId, payload) {
    if (!pollId || typeof localStorage === 'undefined') return
    try {
        localStorage.setItem(VOTE_KEY_PREFIX + pollId, JSON.stringify(payload))
    } catch {
        /* localStorage full/disabled — the guard is best effort. */
    }
}

// Read the body of a failed GET as JSON; null when the server provides no
// (usable) JSON body.
async function readErrorBody(res) {
    try { return await res.json() } catch { return null }
}

// Determine the active sibling question of a 410 response — only set when it
// differs from the current token (auto-follow target).
function followTokenFrom(body, token) {
    const activeToken = body?.active_token ?? null
    return activeToken && activeToken !== token ? activeToken : null
}

// Condense the live-sync status into a compact decision:
//   followToken  — another collection question is running → follow it
//   ownActive    — the OWN question is active
//   ended        — voting finished for good
function readStatusDecision(status, token) {
    const activeToken = status?.active_token ?? null
    return {
        followToken: activeToken && activeToken !== token ? activeToken : null,
        ownActive:   status?.status === 'active' && (!activeToken || activeToken === token),
        ended:       status?.status === 'ended',
    }
}

export const usePollsStore = defineStore('polls', {
    state: () => ({
        currentPoll:     null,
        loading:         false,
        submitting:      false,
        error:           null,
        submittedAnswer: null,
        // Token of the currently shown / last loaded question — anchor for
        // the status watch and the auto-follow.
        token:           null,
        // Local "ended" state once the countdown reaches 0. The server stays
        // authoritative (rejects late answers); this flag only makes the UI
        // switch consistently right away.
        timeExpired:     false,
        // Voting stopped or not yet started (server: 410 + `status: paused`).
        // The page waits visibly and switches automatically on start — NOT a
        // dead end state.
        waiting:         false,
        // Server reports the poll as finished for good (archived,
        // `status: ended`). Regular end state, NOT an error.
        ended:           false,

        // Quiz learning effect: solution of the ended quiz question.
        // `{ correct: ['optId', …], options: [{id,label}, …] }` or null.
        // The server provides it ONLY for ended quiz questions (otherwise 403) —
        // null is the normal case while the question is running, NOT an error.
        solution:        null,
    }),

    getters: {
        hasPoll:          (s) => s.currentPoll !== null,
        isAnswered:       (s) => s.submittedAnswer !== null,
        availableOptions: (s) => s.currentPoll?.options ?? [],
        // Remaining time relative to the server clock; null = no limit.
        remainingSeconds: (s) => s.currentPoll?.remaining_seconds ?? null,
        hasTimeLimit:     (s) => (s.currentPoll?.remaining_seconds ?? null) !== null,

        // ID of the own submitted answer (quiz is always single-choice mc →
        // `submittedAnswer.selected` is a string). Other shapes (matrix object
        // etc.) yield null.
        ownChoiceId: (s) => {
            const sel = s.submittedAnswer?.selected
            return typeof sel === 'string' ? sel : null
        },

        // Was the own answer correct?
        //   true / false  → verdict known
        //   null          → solution missing OR no own vote was cast
        ownAnswerCorrect: (s) => {
            if (!s.solution) return null
            const sel = s.submittedAnswer?.selected
            const choice = typeof sel === 'string' ? sel : null
            if (choice === null) return null
            return s.solution.correct.includes(choice)
        },
    },

    actions: {
        async loadPoll(token) {
            this.token       = token
            this.loading     = true
            this.error       = null
            this.timeExpired = false
            this.waiting     = false
            this.ended       = false
            this.solution    = null   // discard the previous question's solution

            try {
                const url = `${pluginUrl()}api/polls/${encodeURIComponent(token)}`
                const res = await fetch(url, { method: 'GET', credentials: 'omit' })

                if (!res.ok) {
                    // Read the error text from the JSON body (the server
                    // provides a readable `message`), fall back to the status code.
                    const body = await readErrorBody(res)
                    if (await this.applyLoadError(token, res.status, body)) return
                    this.currentPoll = null
                    return
                }

                this.currentPoll = await res.json()
                // Already voted on this question in this browser? Then restore
                // the answer state — reload-proof duplicate-vote guard without
                // a server-side client trait.
                const prior = readVote(this.currentPoll?.id)
                if (prior !== null) {
                    this.submittedAnswer = prior
                }
            } catch (e) {
                this.error       = e.message ?? String(e)
                this.currentPoll = null
            } finally {
                this.loading = false
            }
        },

        /**
         * Map an error response of `loadPoll` onto state.
         * Returns `true` ⇒ the caller is done (auto-follow takes over),
         * `false` ⇒ the caller sets `currentPoll = null` and returns.
         */
        async applyLoadError(token, statusCode, body) {
            if (statusCode !== 410) {
                this.error = body?.message ?? `HTTP ${statusCode}`
                return false
            }
            // Distinguish waiting from ended. If a sibling question of the
            // collection is already running, follow it directly instead of
            // waiting.
            const followToken = followTokenFrom(body, token)
            if (followToken) {
                this.loading = false
                await this.followTo(followToken)
                return true
            }
            if ((body?.status ?? 'ended') === 'paused') {
                this.waiting = true
            } else {
                this.ended = true
                // Question has (already) ended — try the solution. If this
                // browser already voted, submittedAnswer is set by readVote
                // (above), so the verdict applies.
                this.maybeLoadSolution()
            }
            return false
        },

        /**
         * Switch to another question of the same collection (auto-follow).
         * Resets the answer state — the new question has not been answered
         * yet — and updates the URL so reload/bookmark land on the current
         * question.
         */
        async followTo(newToken) {
            const oldToken = this.token
            this.submittedAnswer = null
            this.timeExpired     = false
            try {
                if (oldToken && typeof window !== 'undefined' && window.history?.replaceState) {
                    const url = new URL(window.location.href)
                    if (url.pathname.includes(oldToken)) {
                        url.pathname = url.pathname.replace(oldToken, newToken)
                        window.history.replaceState(null, '', url)
                    }
                }
            } catch { /* URL upkeep is a convenience, not a must */ }
            await this.loadPoll(newToken)
        },

        /**
         * One status tick. Errors are deliberately swallowed — the next
         * interval tick retries; a warning message would only unsettle
         * students.
         */
        async checkStatus() {
            if (!this.token) return
            let status
            try {
                const url = `${pluginUrl()}api/poll_status/${encodeURIComponent(this.token)}`
                const res = await fetch(url, { method: 'GET', credentials: 'omit' })
                if (!res.ok) return
                status = await res.json()
            } catch {
                return
            }

            const d = readStatusDecision(status, this.token)
            if (this.isAnswered) return this.syncAnswered(d)
            if (this.hasPoll)    return this.syncActive(d)
            return this.syncWaiting(d)
        },

        // Thanks state: only react again when a DIFFERENT question starts (no
        // re-vote on the already answered question).
        async syncAnswered(d) {
            if (d.followToken) {
                await this.followTo(d.followToken)
            } else if (d.ended) {
                // Deliberately do NOT null currentPoll — the learning block
                // needs quiz_mode/options. The template checks `ended` before
                // `hasPoll`, so the end state stays correct.
                this.markEnded()
            }
        },

        // An active question is shown: react only to stop/switch.
        async syncActive(d) {
            if (d.ownActive) return
            if (d.followToken) {
                await this.followTo(d.followToken)
            } else {
                // Reload fetches the 410 body and sets waiting/ended.
                await this.loadPoll(this.token)
            }
        },

        // Waiting/ended state without a loaded question.
        async syncWaiting(d) {
            if (d.ownActive) {
                await this.loadPoll(this.token)
            } else if (d.followToken) {
                await this.followTo(d.followToken)
            } else if (d.ended && !this.ended) {
                this.markEnded()
            }
        },

        // Unified end state including solution fetch.
        markEnded() {
            this.ended   = true
            this.waiting = false
            this.maybeLoadSolution()
        },

        /**
         * Starts the status polling (idempotent). Hidden tabs pause (saving
         * battery/server) and re-check immediately on becoming visible.
         */
        startStatusWatch(token, intervalMs = STATUS_INTERVAL_MS) {
            this.stopStatusWatch()
            if (token) this.token = token

            watchTimer = setInterval(() => {
                if (typeof document !== 'undefined' && document.hidden) return
                this.checkStatus()
            }, intervalMs)

            if (typeof document !== 'undefined') {
                visibilityHandler = () => {
                    if (!document.hidden) this.checkStatus()
                }
                document.addEventListener('visibilitychange', visibilityHandler)
            }
        },

        stopStatusWatch() {
            if (watchTimer) { clearInterval(watchTimer); watchTimer = null }
            if (visibilityHandler && typeof document !== 'undefined') {
                document.removeEventListener('visibilitychange', visibilityHandler)
                visibilityHandler = null
            }
        },

        async submitAnswer(payload) {
            if (!this.currentPoll) {
                throw new Error('Kein Poll geladen — submitAnswer abgewiesen.')
            }
            if (this.timeExpired) {
                // No voting after expiry. The server would reject it anyway —
                // saves the roundtrip.
                throw new Error('Zeit abgelaufen — submitAnswer abgewiesen.')
            }
            if (this.submitting) return   // prevent double submit

            const url = `${pluginUrl()}api/responses/${this.currentPoll.id}`

            this.submitting = true
            this.error      = null
            try {
                // Anonymous vote: server checks same-origin (no CSRF token).
                // `same-origin` sends cookie/origin without cross-site.
                const res = await fetch(url, {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/json' },
                    body:        JSON.stringify(payload),
                })

                if (!res.ok) {
                    throw new Error(`Antwort konnte nicht gespeichert werden: HTTP ${res.status}`)
                }

                this.submittedAnswer = payload
                persistVote(this.currentPoll?.id, payload)
            } catch (e) {
                this.error = e.message ?? String(e)
                throw e
            } finally {
                this.submitting = false
            }
        },

        // Quiz learning effect: fetch the ended quiz question's solution.
        // Anonymous (same-origin). The server returns 200 only for ended quiz
        // questions, otherwise 403 (`solution_unavailable`) — that is the normal
        // case while the question is running and NOT an error state: we then set
        // solution to null.
        async loadSolution() {
            const token = this.token
            if (!token) return
            try {
                const url = `${pluginUrl()}api/poll_solution/${encodeURIComponent(token)}`
                const res = await fetch(url, { method: 'GET', credentials: 'same-origin' })
                if (res.ok) {
                    this.solution = await res.json()
                } else {
                    // 403 before the end is expected — not an error, just "no solution yet".
                    this.solution = null
                }
            } catch {
                this.solution = null
            }
        },

        // Central trigger — loads the solution once a question has ended.
        // Idempotent. We also attempt the fetch without locally known
        // `quiz_mode` (e.g. initial load of an already ended question, where
        // currentPoll is null then): the endpoint is server-side quiz- AND
        // ended-gated and returns 403 (→ solution stays null) if this is not an
        // ended quiz. If the question is known locally and NOT a quiz, we save
        // the roundtrip.
        maybeLoadSolution() {
            if (!this.ended || this.solution) return
            if (this.currentPoll && !this.currentPoll.quiz_mode) return
            this.loadSolution()
        },

        // Called by the countdown on reaching 0 — switches the UI locally to
        // "ended". The server rejects late answers anyway; this is only for
        // immediate UI consistency.
        markExpired() {
            this.timeExpired = true
        },

        reset() {
            this.stopStatusWatch()
            this.currentPoll     = null
            this.loading         = false
            this.submitting      = false
            this.error           = null
            this.submittedAnswer = null
            this.token           = null
            this.timeExpired     = false
            this.waiting         = false
            this.ended           = false
            this.solution        = null
        },
    },
})
