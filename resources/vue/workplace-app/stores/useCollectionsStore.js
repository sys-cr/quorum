import { defineStore } from 'pinia'

/**
 * Pinia store for the collection list in the workplace.
 *
 * Mirrors the pattern of `useMySurveysStore`: loading state + list from
 * `GET /api/my_collections`, lifecycle actions as POST/DELETE to
 * `/api/collection_<op>/{id}` with a CSRF header, then reload of the list.
 *
 * State machine: `idle` → `loading` → `ready` | `error`.
 */
export const useCollectionsStore = defineStore('collections', {
    state: () => ({
        collections: /** @type {Array<object>} */ ([]),
        view:        /** @type {'active'|'archive'} */ ('active'),
        status:      /** @type {'idle'|'loading'|'ready'|'error'} */ ('idle'),
        error:       /** @type {string|null} */ (null),
        busyIds:     /** @type {Set<string>} */ (new Set()),
        csrfToken:   /** @type {string|null} */ (null),
    }),

    getters: {
        isReady:   (s) => s.status === 'ready',
        isLoading: (s) => s.status === 'loading',
        hasError:  (s) => s.status === 'error',
        isEmpty:   (s) => s.status === 'ready' && s.collections.length === 0,
        isBusy:    (s) => (id) => s.busyIds.has(id),
        runningCollections: (s) => s.collections.filter(c => (c.active_count ?? 0) > 0),
    },

    actions: {
        async fetch(pluginUrl, view = null) {
            if (view) this.view = view
            this.status = 'loading'
            this.error  = null
            try {
                const url = `${pluginUrl}/api/my_collections?view=${encodeURIComponent(this.view)}`
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers:     { 'Accept': 'application/json' },
                })
                if (!res.ok) throw new Error(`http_${res.status}`)
                const json = await res.json()
                this.collections = Array.isArray(json?.collections) ? json.collections : []
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
         * Generic lifecycle call to `/api/collection_<op>/{id}`.
         * `op` ∈ start|finish|archive|unarchive|delete; `body` (e.g.
         * `{mode: 'step'}`) is sent as JSON. Throws on error — the component
         * renders the error banner.
         */
        async lifecycle(pluginUrl, collectionId, op, { method = 'POST', body = null } = {}) {
            const url = `${pluginUrl}/api/collection_${op}/${encodeURIComponent(collectionId)}`
            this.busyIds.add(collectionId)
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
                this.busyIds.delete(collectionId)
            }
        },

        // Flow control: `mode 'all'` = all questions at once, `mode 'step'` =
        // only question 1 active, advancing happens in the presenter.
        startAll(pluginUrl, id)  { return this.lifecycle(pluginUrl, id, 'start', { body: { mode: 'all' } }) },
        startStep(pluginUrl, id) { return this.lifecycle(pluginUrl, id, 'start', { body: { mode: 'step' } }) },
        finish(pluginUrl, id)    { return this.lifecycle(pluginUrl, id, 'finish') },
        archive(pluginUrl, id)   { return this.lifecycle(pluginUrl, id, 'archive') },
        unarchive(pluginUrl, id) { return this.lifecycle(pluginUrl, id, 'unarchive') },
        deleteCollection(pluginUrl, id) {
            return this.lifecycle(pluginUrl, id, 'delete', { method: 'DELETE' }).then(() => {})
        },

        reset() {
            this.collections = []
            this.status      = 'idle'
            this.error       = null
            this.busyIds     = new Set()
        },
    },
})
