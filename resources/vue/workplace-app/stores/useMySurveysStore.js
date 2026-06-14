import { defineStore } from 'pinia'

/**
 * Pinia store for the polls list in the workplace widget.
 *
 * Holds only load state + the list from `GET /api/my_polls`. Stud.IP Trails
 * routing maps URL paths directly to `<action>_action` method names —
 * `my_polls` (underscore) → `my_polls_action`. A hyphen (`my-polls`) would be
 * `my-polls_action` and fail with "404 Unknown action".
 *
 * The workplace cards show the `response_count` snapshot from this store (no
 * live SSE in the list view — live counters exist only in the presenter),
 * keeping the store slim and individually testable.
 *
 * State machine:
 *   `idle` → `loading` → `ready` | `error`
 * `fetch()` leads from `error` back to `loading`.
 */
export const useMySurveysStore = defineStore('mySurveys', {
    state: () => ({
        polls:  /** @type {Array<object>} */ ([]),
        view:   /** @type {'active'|'archive'} */ ('active'),
        status: /** @type {'idle'|'loading'|'ready'|'error'} */ ('idle'),
        error:  /** @type {string|null} */ (null),
        // A lifecycle action may run per poll ID (drives the UI spinner)
        busyIds: /** @type {Set<string>} */ (new Set()),
        csrfToken: /** @type {string|null} */ (null),
    }),

    getters: {
        isReady:       (s) => s.status === 'ready',
        isLoading:     (s) => s.status === 'loading',
        hasError:      (s) => s.status === 'error',
        isEmpty:       (s) => s.status === 'ready' && s.polls.length === 0,
        runningPolls:  (s) => s.polls.filter(p => p.is_active),
        pausedPolls:   (s) => s.polls.filter(p => !p.is_active && !p.archived_at),
        archivedPolls: (s) => s.polls.filter(p => !!p.archived_at),
        isBusy:        (s) => (id) => s.busyIds.has(id),
    },

    actions: {
        /**
         * Loads the polls list from the backend for the current filter
         * (`?view=active|archive`). `pluginUrl` comes from the mount
         * `<div data-plugin-url="...">`.
         */
        async fetch(pluginUrl, view = null) {
            if (view) this.view = view
            this.status = 'loading'
            this.error  = null
            try {
                const url = `${pluginUrl}/api/my_polls?view=${encodeURIComponent(this.view)}`
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers:     { 'Accept': 'application/json' },
                })
                if (!res.ok) throw new Error(`http_${res.status}`)
                const json = await res.json()
                this.polls  = Array.isArray(json?.polls) ? json.polls : []
                this.status = 'ready'
            } catch (e) {
                this.error  = String(e?.message ?? e)
                this.status = 'error'
            }
        },

        setCsrfToken(token) {
            this.csrfToken = token || null
        },

        /**
         * Generic lifecycle call (POST/DELETE on `/api/{action}/{id}`). Trails
         * routes flat: the `action` name is exactly the PHP method name with an
         * underscore. `action` is the action name
         * (`finish`/`start`/`archive`/`unarchive`/`delete`).
         * Sets a busy marker, calls the backend with the CSRF header, and on
         * success re-fetches the list so cards re-sort into the current view.
         *
         * Throws on error — the caller (component) shows the UI error.
         */
        async lifecycle(pluginUrl, pollId, action, { method = 'POST', body = null } = {}) {
            const url = `${pluginUrl}/api/${action}/${encodeURIComponent(pollId)}`
            this.busyIds.add(pollId)
            try {
                const headers = {
                    'Accept': 'application/json',
                    'X-CSRF-Token': this.csrfToken ?? '',
                }
                if (body) headers['Content-Type'] = 'application/json'
                const res = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers,
                    body: body ? JSON.stringify(body) : undefined,
                })
                if (!res.ok) throw new Error(`http_${res.status}`)
                await this.fetch(pluginUrl)
                return await res.json().catch(() => ({}))
            } finally {
                this.busyIds.delete(pollId)
            }
        },

        finish(pluginUrl, id)    { return this.lifecycle(pluginUrl, id, 'finish') },
        start(pluginUrl, id)     { return this.lifecycle(pluginUrl, id, 'start') },
        archive(pluginUrl, id)   { return this.lifecycle(pluginUrl, id, 'archive') },
        unarchive(pluginUrl, id) { return this.lifecycle(pluginUrl, id, 'unarchive') },
        deletePoll(pluginUrl, id) { return this.lifecycle(pluginUrl, id, 'delete', { method: 'DELETE' }).then(() => {}) },
        // restart has NO JSON endpoint — the restart flow navigates to the page
        // `workplace/restart/{id}` (wizard dialog), not via /api/.

        reset() {
            this.polls   = []
            this.status  = 'idle'
            this.error   = null
            this.busyIds = new Set()
        },
    },
})
