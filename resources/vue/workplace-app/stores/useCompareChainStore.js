import { defineStore } from 'pinia'

/**
 * Pinia store for the peer-instruction compare full page.
 *
 * Holds the snapshot from `GET /api/compare_chain/{rootId}`. The chain is a
 * flat model: `root` (root poll) and `rounds` (all follow-up polls ascending
 * by `mkdate`). Counts are inline per round; `root.counts` comes from the
 * separate `root_counts` field of the API response.
 *
 * State machine:
 *   `idle` → `loading` → `ready` | `error`
 */
export const useCompareChainStore = defineStore('compareChain', {
    state: () => ({
        status: /** @type {'idle'|'loading'|'ready'|'error'} */ ('idle'),
        root:   /** @type {object|null} */ (null),
        rounds: /** @type {Array<object>} */ ([]),
        error:  /** @type {string|null} */ (null),
    }),

    getters: {
        isReady:   (s) => s.status === 'ready',
        isLoading: (s) => s.status === 'loading',
        hasError:  (s) => s.status === 'error',

        /** Root + all follow-up polls in chronological order. */
        allPolls: (s) => {
            if (s.status !== 'ready' || s.root === null) return []
            return [s.root, ...s.rounds]
        },

        /** Σ counts per poll ID (for the sample-size display). */
        totals: (s) => {
            const out = {}
            const sumOf = (counts) =>
                Object.values(counts ?? {}).reduce((a, n) => a + (Number(n) || 0), 0)
            if (s.root) out[s.root.id] = sumOf(s.root.counts)
            for (const r of s.rounds) out[r.id] = sumOf(r.counts)
            return out
        },

        /** Stable option order — follows the root. */
        optionIds: (s) => (s.root?.options ?? []).map(o => o.id),

        /** True once ≥ 1 follow-up round exists (otherwise no delta display). */
        hasDelta: (s) => s.rounds.length >= 1,
    },

    actions: {
        async fetch(pluginUrl, rootId) {
            this.status = 'loading'
            this.error  = null
            try {
                const url = `${pluginUrl}/api/compare_chain/${encodeURIComponent(rootId)}`
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers:     { 'Accept': 'application/json' },
                })
                if (!res.ok) throw new Error(`http_${res.status}`)
                const json = await res.json()

                // Embed root counts (root_counts → root.counts).
                const root = json?.root ? { ...json.root, counts: json.root_counts ?? {} } : null
                const rounds = Array.isArray(json?.rounds)
                    ? json.rounds.map(r => ({ ...r, counts: r.counts ?? {} }))
                    : []

                this.root   = root
                this.rounds = rounds
                this.status = 'ready'
            } catch (e) {
                this.error  = String(e?.message ?? e)
                this.status = 'error'
            }
        },

        reset() {
            this.status = 'idle'
            this.root   = null
            this.rounds = []
            this.error  = null
        },
    },
})
