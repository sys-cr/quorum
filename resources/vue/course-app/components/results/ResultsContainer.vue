<template>
    <section class="quorum--results-container">
        <header class="quorum--results-toolbar">
            <span class="quorum--results-label">{{ t('results.heading') }}</span>
            <!-- Stud.IP standard buttons (`.button` in `.button-group`, active
                 type as `.active` — core convention) instead of a custom chip
                 look. In the course context the core CSS styles these; on the
                 standalone presenter page the partial `_studip-buttons.scss`
                 does. -->
            <div class="quorum--results-toggle button-group" role="group" :aria-label="t('results.toggleAria')">
                <button
                    v-for="opt in toggleOptions"
                    :key="opt.value"
                    type="button"
                    class="button"
                    :class="{ active: type === opt.value }"
                    :aria-pressed="type === opt.value"
                    @click="setType(opt.value)"
                >{{ opt.label }}</button>
            </div>
        </header>

        <ResultsBar    v-if="type === 'bar-vertical'"   :options="options" :counts="counts" orientation="vertical" />
        <ResultsBar    v-else-if="type === 'bar-horizontal'" :options="options" :counts="counts" orientation="horizontal" />
        <ResultsDonut  v-else-if="type === 'donut'"     :options="options" :counts="counts" />
        <ResultsBubble v-else-if="type === 'bubble'"    :options="options" :counts="counts" />
        <ResultsCloud  v-else-if="type === 'cloud'"     :options="options" :counts="counts" />
    </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ResultsBar    from './ResultsBar.vue'
import ResultsDonut  from './ResultsDonut.vue'
import ResultsBubble from './ResultsBubble.vue'
import ResultsCloud  from './ResultsCloud.vue'

const props = defineProps({
    options:   { type: Array,  required: true },
    counts:    { type: Object, default: () => ({}) },
    /** Allowed chart types, default: all. */
    available: { type: Array, default: () => ['bar-vertical', 'bar-horizontal', 'donut', 'bubble', 'cloud'] },
    /** Initially selected type; persisted in localStorage. */
    defaultType: { type: String, default: 'bar-vertical' },
    /** Persistence key — per question/collection ID so each question keeps its last type. */
    persistenceKey: { type: String, default: 'quorum.results.type' },
})

const { t } = useI18n()

const VALID = new Set(['bar-vertical', 'bar-horizontal', 'donut', 'bubble', 'cloud'])

/* Read from localStorage up front so the first render shows the persisted type
   — otherwise the default type flickers briefly on mount. */
const readPersisted = () => {
    try {
        const stored = window.localStorage?.getItem(props.persistenceKey)
        if (stored && VALID.has(stored) && props.available.includes(stored)) return stored
    } catch { /* ignore */ }
    return props.defaultType
}
const type = ref(readPersisted())

const setType = (next) => {
    if (!VALID.has(next) || !props.available.includes(next)) return
    type.value = next
    try { window.localStorage?.setItem(props.persistenceKey, next) } catch { /* ignore */ }
}

const toggleOptions = computed(() => {
    const labels = {
        'bar-vertical':   t('results.barVertical'),
        'bar-horizontal': t('results.barHorizontal'),
        'donut':          t('results.donut'),
        'bubble':         t('results.bubble'),
        'cloud':          t('results.cloud'),
    }
    return props.available.map(v => ({ value: v, label: labels[v] }))
})
</script>

<style scoped lang="scss">
/* Card look: 8px border-inline-start in petrol, tinted background, colored shadow. */
.quorum--results-container {
    background: color-mix(in srgb, var(--quorum-petrol) 7%, var(--quorum-bg));
    border: 1px solid var(--quorum-border);
    border-inline-start: 8px solid var(--quorum-petrol);
    border-radius: var(--quorum-radius);
    padding: 1rem;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--quorum-petrol) 18%, transparent);
    color: var(--quorum-fg);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quorum--results-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    justify-content: space-between;
}

.quorum--results-label {
    font-weight: 600;
    color: var(--quorum-fg);
    font-size: 0.95rem;
}

.quorum--results-toggle {
    /* `.button-group` (core/partial) provides inline-flex + gap. The core's
       100 px minimum button does not fit the switcher — five types must wrap
       at 375 px without horizontal overflow. */
    .button {
        min-inline-size: 0;
        white-space: nowrap;
    }
}
</style>
