<template>
    <main class="quorum--student" :aria-label="t('student.heading')">
        <header class="quorum--student-header">
            <h1>{{ t('student.heading') }}</h1>
        </header>

        <!-- Loading/error states: plain live-region paragraphs. The first load
             shows the hint; later live refreshes run silently so the view does
             not flicker on every interval. -->
        <p v-if="store.loading && !hasContent" class="quorum--empty-title" role="status">
            {{ t('student.loading') }}
        </p>

        <div v-else-if="store.error && !hasContent" class="quorum--hero-empty" role="alert">
            <p class="quorum--empty-title">{{ t('student.errorLoading') }}</p>
            <button type="button" class="button" @click="store.loadPolls()">
                {{ t('student.retry') }}
            </button>
        </div>

        <template v-else>
            <!-- Empty state: neither running NOR finished polls. -->
            <div v-if="!hasContent" class="quorum--hero-empty">
                <p class="quorum--empty-title">{{ t('student.empty') }}</p>
            </div>

            <!-- "Running now" section: join links to the open votes. -->
            <section v-if="store.active.length" class="quorum--student-section">
                <h2>{{ t('student.activeHeading') }}</h2>
                <ul class="quorum--student-active">
                    <li
                        v-for="poll in store.active"
                        :key="poll.id"
                        class="quorum--student-active-item"
                    >
                        <div class="quorum--student-active-text">
                            <span v-if="typeLabel(poll.type)" class="quorum--student-badge">
                                {{ typeLabel(poll.type) }}
                            </span>
                            <span class="quorum--student-question">{{ poll.question }}</span>
                        </div>
                        <a
                            class="button accept quorum--student-join"
                            :href="poll.join_url"
                            target="_blank"
                            rel="noopener"
                        >{{ t('student.join') }}</a>
                    </li>
                </ul>
            </section>

            <!-- "Past results" section: one collapsible entry per finished
                 poll; results are loaded lazily. -->
            <section v-if="store.finished.length" class="quorum--student-section">
                <h2>{{ t('student.finishedHeading') }}</h2>
                <ul class="quorum--student-finished">
                    <StudentFinishedEntry v-for="poll in store.finished" :key="poll.id" :poll="poll" />
                </ul>
            </section>

            <!-- "Collections" section: per collection the running member
                 questions (join link) + the finished, released questions
                 (collapsible results, same child component). -->
            <section v-if="store.collections.length" class="quorum--student-section">
                <h2>{{ t('student.collectionsHeading') }}</h2>
                <div
                    v-for="col in store.collections"
                    :key="col.id"
                    class="quorum--student-collection"
                >
                    <h3 class="quorum--student-collection-name">{{ col.name }}</h3>
                    <p v-if="col.description" class="quorum--student-collection-desc">{{ col.description }}</p>

                    <ul v-if="col.active.length" class="quorum--student-active">
                        <li
                            v-for="poll in col.active"
                            :key="poll.id"
                            class="quorum--student-active-item"
                        >
                            <div class="quorum--student-active-text">
                                <span v-if="typeLabel(poll.type)" class="quorum--student-badge">
                                    {{ typeLabel(poll.type) }}
                                </span>
                                <span class="quorum--student-question">{{ poll.question }}</span>
                            </div>
                            <a
                                class="button accept quorum--student-join"
                                :href="poll.join_url"
                                target="_blank"
                                rel="noopener"
                            >{{ t('student.join') }}</a>
                        </li>
                    </ul>

                    <ul v-if="col.finished.length" class="quorum--student-finished">
                        <StudentFinishedEntry v-for="poll in col.finished" :key="poll.id" :poll="poll" />
                    </ul>
                </div>
            </section>
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStudentStore } from '../stores/useStudentStore.js'
import StudentFinishedEntry from './StudentFinishedEntry.vue'
import { pollTypeLabel } from '@/pollTypeLabel.js'

/**
 * Student view of the course tab (read-only).
 *
 * Two sections: running polls with a join link and finished polls with
 * collapsible results. The running polls are refreshed via a visibility-aware
 * interval (every 5 s, only while the tab is visible) so newly started votes
 * appear without reloading the page.
 */

const { t }     = useI18n()
const store     = useStudentStore()
const typeLabel = (tp) => pollTypeLabel(t, tp)

// "Has content" = at least one running/finished poll or collection. Controls
// whether the loading/error banners are shown (live refresh must not trigger
// them).
const hasContent = computed(() =>
    store.active.length > 0 || store.finished.length > 0 || store.collections.length > 0)

// Load running polls + collections together (visibility-aware).
const refresh = () => {
    store.loadPolls()
    store.loadCollections()
}

// Live refresh: every 5 s, but only while the tab is visible — no background
// polling.
let timer = null
onMounted(() => {
    refresh()
    timer = setInterval(() => {
        if (typeof document !== 'undefined' && document.hidden === true) return
        refresh()
    }, 5000)
})
onUnmounted(() => {
    if (timer !== null) clearInterval(timer)
    timer = null
})
</script>

<style scoped lang="scss">
.quorum--student {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-lg);
    color: var(--quorum-fg);
    padding-block: var(--quorum-space-md);

    h1 {
        font-size: var(--quorum-text-xl);
        margin: 0;
        color: var(--quorum-fg);
    }
    h2 {
        font-size: var(--quorum-text-lg);
        margin: 0 0 var(--quorum-space-sm);
        color: var(--quorum-fg);
    }
}

.quorum--student-section {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
}

/* Collection: name + description as a heading, below it the running and
   finished member questions (same lists as the flat sections). */
.quorum--student-collection {
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
    padding-block-end: var(--quorum-space-sm);
}
.quorum--student-collection-name {
    margin: 0;
    font-size: var(--quorum-text-md);
    font-weight: 600;
    color: var(--quorum-fg);
}
.quorum--student-collection-desc {
    margin: 0;
    font-size: var(--quorum-text-sm);
    color: var(--quorum-muted);
    white-space: pre-line;
}

/* Running polls: card with the question on the left, join button on the right.
   From the narrow viewport (375 px) the card wraps onto a single column. */
.quorum--student-active {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
}
.quorum--student-active-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--quorum-space-sm) var(--quorum-space-md);
    padding: var(--quorum-space-md);
    border: 1px solid var(--quorum-border);
    border-inline-start: 8px solid var(--quorum-petrol);
    border-radius: var(--quorum-radius);
    background: color-mix(in srgb, var(--quorum-petrol) 7%, var(--quorum-bg));
}
.quorum--student-active-text {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--quorum-space-sm);
    flex: 1 1 12rem;
    min-inline-size: 0;
}
.quorum--student-join { flex-shrink: 0; }

/* Finished polls: collapsible entries. */
.quorum--student-finished {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--quorum-space-sm);
}
.quorum--student-finished-item {
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
    overflow: hidden;
}
.quorum--student-finished-head {
    margin: 0;
    font-size: var(--quorum-text-md);
}
.quorum--student-toggle {
    inline-size: 100%;
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--quorum-space-sm);
    padding: var(--quorum-space-md);
    background: transparent;
    border: 0;
    text-align: start;
    cursor: pointer;
    color: var(--quorum-fg);
    font: inherit;

    &:focus-visible {
        outline: var(--quorum-focus-ring);
        outline-offset: -2px;
    }
}
.quorum--student-results {
    padding: 0 var(--quorum-space-md) var(--quorum-space-md);
    overflow-x: auto;
}

.quorum--student-badge {
    display: inline-block;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: var(--quorum-text-sm);
    font-weight: 600;
    background: color-mix(in srgb, var(--quorum-petrol) 15%, var(--quorum-bg));
    color: var(--quorum-petrol);
    white-space: nowrap;
}
.quorum--student-question {
    font-weight: 600;
    overflow-wrap: anywhere;
}

.quorum--student-freitext {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;

    li {
        padding: 0.4rem 0.6rem;
        border: 1px solid var(--quorum-border);
        border-radius: var(--quorum-radius);
        overflow-wrap: anywhere;
        white-space: pre-line;
    }
}

.quorum--results-num { text-align: end; }
.quorum--results-empty { margin: 0; color: var(--quorum-muted); }

/* Quiz learning effect: subtly highlight the correct option. The meaning is
   carried by the ✓ symbol + the screen-reader text — the color is only an
   addition (never color alone). */
.quorum--results-correct {
    background: color-mix(in srgb, var(--quorum-success) 12%, var(--quorum-bg));

    td, th { font-weight: 600; }
}
.quorum--correct-mark {
    color: var(--quorum-success);
    font-weight: 800;
    margin-inline-end: 0.35rem;
}
@media (prefers-contrast: more) {
    .quorum--results-correct {
        outline: 2px solid var(--quorum-success);
        outline-offset: -2px;
    }
}

.quorum--hero-empty {
    background: var(--quorum-hero-bg);
    color: var(--quorum-hero-fg);
    border: 1px solid var(--quorum-hero-border);
    border-radius: var(--quorum-radius);
    padding: var(--quorum-space-xl) var(--quorum-space-lg);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--quorum-space-md);
}
.quorum--empty-title {
    margin: 0;
    font-weight: 600;
    color: inherit;
}

.quorum--visually-hidden {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}
</style>
