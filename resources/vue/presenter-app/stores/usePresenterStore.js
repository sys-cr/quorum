import { defineStore } from 'pinia'

/**
 * State for the presenter app.
 *
 * Holds the collection polls + the current index. Lifecycle actions
 * (`finish`/`start` of the active poll) go through the lifecycle API
 * (`POST /api/finish|start/{id}`).
 */
export const usePresenterStore = defineStore('presenter', {
    state: () => ({
        collection: /** @type {{id: string, name: string}|null} */ (null),
        polls:      /** @type {Array<object>} */ ([]),
        currentIdx: 0,
        pluginUrl:  '',
        csrfToken:  '',
        returnUrl:  '',
        // Per-poll voting status so the UI toggle reflects the server state
        // without a reload (mirrored locally).
        runningOverrides: /** @type {Record<string, boolean>} */ ({}),
        // Transient error hint (e.g. toggle rejected) — PresenterRoot shows it
        // briefly and clears it. Without it the start/stop button would snap
        // back silently on server rejection.
        lastError: /** @type {string|null} */ (null),
    }),

    getters: {
        currentPoll:  (s) => s.polls[s.currentIdx] ?? null,
        hasPrev:      (s) => s.currentIdx > 0,
        hasNext:      (s) => s.currentIdx < s.polls.length - 1,
        total:        (s) => s.polls.length,
        positionLabel: (s) => `${s.currentIdx + 1} / ${s.polls.length}`,
        isCurrentRunning: (s) => {
            const p = s.polls[s.currentIdx]
            if (!p) return false
            return s.runningOverrides[p.id] ?? p.is_active
        },
    },

    actions: {
        hydrateFromMount(root) {
            this.pluginUrl = root?.dataset?.pluginUrl ?? ''
            this.csrfToken = root?.dataset?.csrf ?? ''
            this.returnUrl = root?.dataset?.returnUrl ?? ''
            const dataNode = typeof document !== 'undefined'
                ? document.getElementById('quorum-presenter-data')
                : null
            if (dataNode) {
                try {
                    const json = JSON.parse(dataNode.textContent ?? '{}')
                    this.collection = json.collection ?? null
                    this.polls      = Array.isArray(json.polls) ? json.polls : []
                } catch {
                    this.collection = null
                    this.polls      = []
                }
            }
        },

        next() { if (this.hasNext) this.currentIdx += 1 },
        prev() { if (this.hasPrev) this.currentIdx -= 1 },
        gotoIndex(i) {
            if (i >= 0 && i < this.polls.length) this.currentIdx = i
        },

        /**
         * Voting toggle for the current poll. Optimistic via
         * `runningOverrides` — rolls back on error.
         */
        async toggleCurrentVoting() {
            const poll = this.currentPoll
            if (!poll) return
            const wasRunning = this.isCurrentRunning
            const op         = wasRunning ? 'finish' : 'start'

            // Optimistic update
            this.lastError = null
            this.runningOverrides = { ...this.runningOverrides, [poll.id]: !wasRunning }
            try {
                const res = await fetch(
                    `${this.pluginUrl}/api/${op}/${encodeURIComponent(poll.id)}`,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-Token': this.csrfToken,
                        },
                    },
                )
                if (!res.ok) throw new Error(`http_${res.status}`)
            } catch {
                // Rollback + visible hint (otherwise the button reverts silently)
                const restored = { ...this.runningOverrides }
                restored[poll.id] = wasRunning
                this.runningOverrides = restored
                this.lastError = 'toggle'
            }
        },

        /**
         * Flow control: finishes the current question (if running), advances
         * to the next and starts its voting — the owner's "question done →
         * next question" flow as ONE click instead of stop/arrow/start. Uses
         * the existing lifecycle endpoints; no dedicated server endpoint is
         * needed.
         */
        async advanceToNext() {
            if (!this.hasNext) return
            if (this.isCurrentRunning) {
                await this.toggleCurrentVoting()
                // Stopping failed → do not advance, the error is pending.
                if (this.lastError) return
            }
            this.currentIdx += 1
            if (!this.isCurrentRunning) {
                await this.toggleCurrentVoting()
            }
        },

        leave() {
            if (this.returnUrl && typeof window !== 'undefined') {
                window.location.href = this.returnUrl
            }
        },
    },
})
