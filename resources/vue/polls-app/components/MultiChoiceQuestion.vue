<template>
    <form class="quorum--multi" @submit.prevent="onSubmit">
        <h1 class="polls-headline polls-question">{{ question }}</h1>
        <p class="quorum--multi-hint">{{ $t('multi.hint') }}</p>

        <fieldset class="quorum--multi-options" :disabled="disabled">
            <legend class="visually-hidden">{{ $t('multi.choose') }}</legend>
            <label
                v-for="option in options"
                :key="option.id"
                class="quorum--multi-option"
                :class="{ 'is-checked': selected.includes(option.id) }"
            >
                <input
                    type="checkbox"
                    class="quorum--multi-check"
                    :value="option.id"
                    :disabled="disabled"
                    v-model="selected"
                >
                <span class="quorum--multi-label">{{ option.label }}</span>
            </label>
        </fieldset>

        <button
            type="submit"
            class="quorum--multi-submit button"
            :disabled="disabled || selected.length === 0"
        >
            {{ $t('multi.submit') }}
        </button>
    </form>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    question: { type: String, required: true },
    options:  { type: Array,  default: () => [] },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

// Multi-select → `selected` is an array of option ids.
const selected = ref([])

function onSubmit() {
    if (props.disabled || selected.value.length === 0) return
    emit('submit', { selected: [...selected.value] })
}
</script>

<style scoped>
.quorum--multi {
    background: var(--quorum-bg);
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quorum--multi-hint {
    margin: 0;
    font-size: 0.875rem;
    color: var(--quorum-muted);
}

.quorum--multi-options {
    border: 0;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.quorum--multi-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border: 2px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    cursor: pointer;
    font-size: 1.05rem;
    /* touch target ≥ 44px */
    min-block-size: 44px;
}

.quorum--multi-option.is-checked {
    border-color: var(--quorum-petrol);
    background: color-mix(in srgb, var(--quorum-petrol) 8%, var(--quorum-bg));
}

.quorum--multi-option:focus-within {
    outline: 2px solid var(--quorum-link);
    outline-offset: 2px;
}

.quorum--multi-check {
    inline-size: 1.4rem;
    block-size: 1.4rem;
    accent-color: var(--quorum-petrol);
    flex: 0 0 auto;
}

.quorum--multi-label {
    flex: 1 1 auto;
}

.quorum--multi-submit {
    align-self: flex-start;
}

.quorum--multi-options:disabled {
    opacity: 0.6;
}

.visually-hidden {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (prefers-contrast: more), (forced-colors: active) {
    .quorum--multi-option { border-color: CanvasText; }
    .quorum--multi-option.is-checked { border-color: Highlight; }
}
</style>
