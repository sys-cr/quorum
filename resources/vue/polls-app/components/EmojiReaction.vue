<template>
    <form class="quorum--emoji" @submit.prevent="onSubmit">
        <div class="quorum--emoji-inner">
            <h1 class="polls-headline polls-question">{{ question }}</h1>
            <fieldset class="quorum--emoji-options">
                <legend class="visually-hidden">{{ $t('emoji.choose') }}</legend>
                <button
                    v-for="(option, index) in options"
                    :key="option.id"
                    type="button"
                    class="quorum--emoji-btn"
                    :class="{ 'is-selected': selected === option.id, [`acc-${ACCENTS[index % ACCENTS.length]}`]: true }"
                    :aria-pressed="selected === option.id"
                    :aria-label="option.label"
                    @click="selected = option.id"
                >
                    <span aria-hidden="true">{{ option.label }}</span>
                </button>
            </fieldset>
            <button type="submit" class="button" :disabled="!selected || disabled">
                {{ $t('emoji.submit') }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    question: { type: String, required: true },
    options:  { type: Array, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const selected = ref(null)
const ACCENTS = ['petrol', 'green', 'magenta', 'brand', 'dark-violet']

function onSubmit() {
    if (!selected.value || props.disabled) return
    emit('submit', { selected: selected.value })
}
</script>

<style scoped>
.quorum--emoji {
    /* Flat bordered frame. */
    background: var(--quorum-bg);
    border: 1px solid var(--quorum-border);
    padding: 0;
    border-radius: var(--quorum-radius);
    overflow: hidden;
    box-shadow: 0 1px 3px color-mix(in srgb, var(--quorum-fg) 12%, transparent);
    max-width: 480px;
    margin-inline: auto;
}

.quorum--emoji-inner {
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    padding: 1.25rem;
    border-radius: var(--quorum-radius);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.polls-headline {
    font-size: clamp(1.15rem, 1rem + 1.2vw, 1.5rem);
    line-height: 1.3;
    margin: 0;
    hyphens: auto;
    overflow-wrap: break-word;
    text-wrap: balance;
}

.quorum--emoji-options {
    border: 0;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
}

.quorum--emoji-btn {
    min-width: 44px;  /* touch target ≥ 44×44 px */
    min-height: 44px;
    padding: 0.5rem 0.75rem;
    font-size: 1.75rem;
    line-height: 1;
    cursor: pointer;
    border: 2px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
    transition: transform 80ms ease, border-color 120ms ease;
}

.quorum--emoji-btn:hover {
    border-color: var(--quorum-accent);
    transform: scale(1.1);
}

.quorum--emoji-btn.is-selected {
    border-color: var(--quorum-accent);
    border-width: 3px;
    background: color-mix(in srgb, var(--quorum-accent) 12%, var(--quorum-bg));
}

.quorum--emoji-btn:focus-visible {
    outline: var(--quorum-focus-ring);
    outline-offset: 2px;
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (prefers-reduced-motion: reduce) {
    .quorum--emoji-btn {
        transition: none;
    }
    .quorum--emoji-btn:hover {
        transform: none;
    }
}

@media (prefers-contrast: more) {
    .quorum--emoji-btn {
        border-width: 3px;
    }
    .quorum--emoji-btn.is-selected {
        border-width: 4px;
    }
}

@media (forced-colors: active) {
    .quorum--emoji-btn {
        border-color: ButtonText;
    }
    .quorum--emoji-btn.is-selected {
        border-color: Highlight;
        background: Highlight;
        color: HighlightText;
    }
}
</style>
