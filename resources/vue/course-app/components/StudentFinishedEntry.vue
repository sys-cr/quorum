<template>
    <li class="quorum--student-finished-item">
        <h3 class="quorum--student-finished-head">
            <button
                type="button"
                class="quorum--student-toggle"
                :aria-expanded="open"
                @click="toggle"
            >
                <span v-if="typeLabel(poll.type)" class="quorum--student-badge">
                    {{ typeLabel(poll.type) }}
                </span>
                <span class="quorum--student-question">{{ poll.question }}</span>
                <span class="quorum--visually-hidden">
                    {{ open ? t('student.collapseResults') : t('student.expandResults') }}
                </span>
            </button>
        </h3>

        <div v-if="open" class="quorum--student-results">
            <p v-if="state === 'loading'" class="quorum--empty-title" role="status">{{ t('student.loading') }}</p>
            <p v-else-if="state === 'error'" class="quorum--empty-title" role="alert">{{ t('student.errorLoading') }}</p>

            <template v-else-if="data">
                <!-- Free text: list of responses. -->
                <template v-if="data.type === 'freitext'">
                    <p v-if="!freitextResponses.length" class="quorum--results-empty">{{ t('student.noResults') }}</p>
                    <ul v-else class="quorum--student-freitext">
                        <li v-for="(text, i) in freitextResponses" :key="i">{{ text }}</li>
                    </ul>
                </template>

                <!-- Matrix: rows × scale, missing cell = 0. -->
                <table v-else-if="data.type === 'matrix'" class="default">
                    <thead>
                        <tr>
                            <th></th>
                            <th v-for="s in matrixScale" :key="s.id" class="quorum--results-num">{{ s.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in matrixRows" :key="row.id">
                            <th scope="row">{{ row.label }}</th>
                            <td v-for="s in matrixScale" :key="s.id" class="quorum--results-num">
                                {{ matrixCell(row.id, s.id) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Choice types: option · votes · percent. Quiz: `correct`
                     marks the correct option(s) with ✓ + text. -->
                <table v-else class="default">
                    <thead>
                        <tr>
                            <th>{{ t('student.optionHeader') }}</th>
                            <th class="quorum--results-num">{{ t('student.votesHeader') }}</th>
                            <th class="quorum--results-num">{{ t('student.percentHeader') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in choiceRows"
                            :key="r.id"
                            :class="{ 'quorum--results-correct': r.correct }"
                        >
                            <td>
                                <span v-if="r.correct" class="quorum--correct-mark">
                                    <span aria-hidden="true">✓</span>
                                    <span class="quorum--visually-hidden">{{ t('student.correctAnswer') }}</span>
                                </span>
                                {{ r.label }}
                            </td>
                            <td class="quorum--results-num">{{ r.votes }}</td>
                            <td class="quorum--results-num">{{ r.percent }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </div>
    </li>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStudentStore } from '../stores/useStudentStore.js'
import { pollTypeLabel } from '@/pollTypeLabel.js'

/**
 * A collapsible entry for a finished poll with lazily loaded results. Reused in
 * the student view both in the flat "past results" list and per collection
 * (DRY). The result shape (free text/matrix/choice) depends on the type; quiz
 * polls mark the correct option(s).
 */
const props = defineProps({
    poll: { type: Object, required: true },
})

const { t }     = useI18n()
const store     = useStudentStore()
const typeLabel = (tp) => pollTypeLabel(t, tp)

const open   = ref(false)
const toggle = () => {
    open.value = !open.value
    if (open.value && !store.results[props.poll.id]) store.loadResults(props.poll.id)
}

const entry = computed(() => store.results[props.poll.id] ?? null)
const state = computed(() => entry.value?.status ?? null)
const data  = computed(() => entry.value?.data ?? null)

const freitextResponses = computed(() => Array.isArray(data.value?.responses) ? data.value.responses : [])
const matrixRows  = computed(() => data.value?.options?.rows  ?? [])
const matrixScale = computed(() => data.value?.options?.scale ?? [])
const matrixCell  = (rowId, scaleId) => data.value?.counts?.[rowId]?.[scaleId] ?? 0

const choiceRows = computed(() => {
    const r       = data.value
    const opts    = Array.isArray(r?.options) ? r.options : []
    const counts  = r?.counts ?? {}
    const correct = Array.isArray(r?.correct) ? r.correct : []
    const total   = Object.values(counts).reduce((a, b) => a + (Number(b) || 0), 0)
    return opts.map(o => {
        const votes = Number(counts[o.id] ?? 0)
        return {
            id:      o.id,
            label:   o.label,
            votes,
            percent: total > 0 ? `${Math.round((votes / total) * 100)} %` : '—',
            correct: correct.includes(o.id),
        }
    })
})
</script>
