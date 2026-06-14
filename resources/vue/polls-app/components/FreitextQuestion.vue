<template>
    <form class="quorum--freitext" @submit.prevent="onSubmit">
        <h1 class="polls-headline polls-question">{{ question }}</h1>
        <div class="quorum--freitext-field">
            <label :for="inputId" class="visually-hidden">{{ $t('freitext.placeholder') }}</label>
            <textarea
                :id="inputId"
                v-model="text"
                class="quorum--freitext-input"
                :placeholder="$t('freitext.placeholder')"
                maxlength="200"
                rows="4"
                :aria-describedby="countId"
            />
            <p :id="countId" class="quorum--freitext-count" :class="{ 'is-near-limit': text.length >= 180 }">
                {{ $t('freitext.charCount', { count: text.length }) }}
            </p>
            <p v-if="showError" class="quorum--freitext-error" role="alert">
                {{ $t('freitext.empty') }}
            </p>
        </div>
        <button type="submit" class="quorum--freitext-submit button" :disabled="disabled">
            {{ $t('freitext.submit') }}
        </button>
    </form>
</template>

<script setup>
import { ref, useId } from 'vue'

const props = defineProps({
    question: { type: String, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const inputId = useId()
const countId = useId()
const text = ref('')
const showError = ref(false)

function onSubmit() {
    if (props.disabled) return
    if (text.value.trim() === '') {
        showError.value = true
        return
    }
    showError.value = false
    emit('submit', { text: text.value })
}
</script>

<style scoped>
.quorum--freitext {
    /* Flat bordered frame. */
    background: var(--quorum-bg);
    border: 1px solid var(--quorum-border);
    padding: 0;
    border-radius: var(--quorum-radius);
    overflow: hidden;                              /* clip inner corners cleanly */
    box-shadow: 0 1px 3px color-mix(in srgb, var(--quorum-fg) 12%, transparent);
    max-width: 480px;
    margin-inline: auto;
}

.quorum--freitext > * {
    background: var(--quorum-bg);
    color: var(--quorum-fg);
}

.quorum--freitext {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.polls-headline,
.quorum--freitext-field,
.quorum--freitext-submit {
    background: var(--quorum-bg);
    padding-inline: 1.25rem;
    margin: 0;
}

.polls-headline {
    padding-block: 1.25rem 0;
    font-size: clamp(1.15rem, 1rem + 1.2vw, 1.5rem);
    line-height: 1.3;
    hyphens: auto;
    overflow-wrap: break-word;
    text-wrap: balance;
    color: var(--quorum-fg);
    border-radius: calc(var(--quorum-radius)) calc(var(--quorum-radius)) 0 0;
}

.quorum--freitext-field {
    padding-block: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.quorum--freitext-input {
    width: 100%;
    box-sizing: border-box;
    font: inherit;
    font-size: 1rem;
    padding: 0.6rem 0.75rem;
    border: 2px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    resize: vertical;
    min-block-size: 6rem;
}

.quorum--freitext-input:focus-visible {
    outline: var(--quorum-focus-ring);
    outline-offset: 2px;
    border-color: var(--quorum-accent);
}

.quorum--freitext-count {
    font-size: 0.8rem;
    color: var(--quorum-muted);
    text-align: end;
}

.quorum--freitext-count.is-near-limit {
    color: var(--quorum-warning);
    font-weight: 600;
}

.quorum--freitext-error {
    font-size: 0.875rem;
    color: var(--quorum-error);
    font-weight: 600;
}

.quorum--freitext-submit {
    padding-block: 0.75rem 1.25rem;
    border-radius: 0 0 var(--quorum-radius) var(--quorum-radius);
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

@media (prefers-contrast: more) {
    .quorum--freitext-input {
        border-width: 3px;
    }
}

@media (forced-colors: active) {
    .quorum--freitext-input {
        border-color: ButtonText;
        color: ButtonText;
        background: ButtonFace;
    }
}
</style>
