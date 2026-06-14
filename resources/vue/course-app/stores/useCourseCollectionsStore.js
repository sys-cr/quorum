import { defineStore } from 'pinia'
import { pluginUrl } from '../../pluginUrl.js'

/**
 * Pinia store for the collection list in the course tab (teacher view).
 *
 * Mirrors the workplace `useCollectionsStore`, but cid-scoped: loads
 * `GET api/course_collections?cid=…` (co-teaching — tutors in the course see
 * all collections of the course) and calls the lifecycle endpoints
 * `api/collection_<op>/{id}`. CSRF comes from `STUDIP.CSRF_TOKEN` (the
 * course-app mount carries NO `data-csrf`).
 */

const courseCollectionsUrl = () => {
    const mount = typeof document !== 'undefined'
        ? document.getElementById('quorum-course-app')
        : null
    const cid = encodeURIComponent(mount?.dataset?.cid ?? globalThis.STUDIP?.CID ?? '')
    return `${pluginUrl()}api/course_collections?cid=${cid}`
}

const csrfHeaders = (json = false) => {
    const h = {
        'Accept':       'application/json',
        'X-CSRF-Token': globalThis.STUDIP?.CSRF_TOKEN?.value ?? '',
    }
    if (json) h['Content-Type'] = 'application/json'
    return h
}

export const useCourseCollectionsStore = defineStore('courseCollections', {
    state: () => ({
        collections: /** @type {Array<object>} */ ([]),
        status:      /** @type {'idle'|'loading'|'ready'|'error'} */ ('idle'),
        error:       /** @type {string|null} */ (null),
        busyIds:     /** @type {Set<string>} */ (new Set()),
    }),

    getters: {
        isReady:   (s) => s.status === 'ready',
        isLoading: (s) => s.status === 'loading',
        hasError:  (s) => s.status === 'error',
        isEmpty:   (s) => s.status === 'ready' && s.collections.length === 0,
        isBusy:    (s) => (id) => s.busyIds.has(id),
    },

    actions: {
        async loadAll() {
            this.status = 'loading'
            this.error  = null
            try {
                const res = await fetch(courseCollectionsUrl(), {
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

        /**
         * Lifecycle call on `api/collection_<op>/{id}` (co-teaching authorized),
         * then reload the list. `op` ∈ start|finish|archive|unarchive|delete.
         */
        async lifecycle(collectionId, op, { method = 'POST', body = null } = {}) {
            this.busyIds.add(collectionId)
            try {
                const res = await fetch(`${pluginUrl()}api/collection_${op}/${encodeURIComponent(collectionId)}`, {
                    method,
                    credentials: 'same-origin',
                    headers:     csrfHeaders(body !== null),
                    body:        body !== null ? JSON.stringify(body) : undefined,
                })
                if (!res.ok) {
                    this.error = `http_${res.status}`
                    throw new Error(this.error)
                }
                await this.loadAll()
                return await res.json().catch(() => ({}))
            } finally {
                this.busyIds.delete(collectionId)
            }
        },

        startAll(id)  { return this.lifecycle(id, 'start', { body: { mode: 'all' } }) },
        startStep(id) { return this.lifecycle(id, 'start', { body: { mode: 'step' } }) },
        finish(id)    { return this.lifecycle(id, 'finish') },
        archive(id)   { return this.lifecycle(id, 'archive') },
    },
})
