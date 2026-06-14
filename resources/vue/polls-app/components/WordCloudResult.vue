<template>
    <section class="quorum--wordcloud" :aria-label="$t('wordCloud.heading')">
        <h2 class="quorum--wordcloud-heading">{{ $t('wordCloud.heading') }}</h2>
        <p v-if="!words.length" class="quorum--wordcloud-empty">{{ $t('wordCloud.empty') }}</p>
        <template v-else>
            <!-- Visual word cloud: font-size scales with frequency -->
            <div class="quorum--wordcloud-canvas" aria-hidden="true">
                <span
                    v-for="(word, i) in sortedWords"
                    :key="word.text"
                    class="quorum--wordcloud-word"
                    :class="`acc-${ACCENTS[i % ACCENTS.length]}`"
                    :style="`font-size: ${fontSize(word.freq)}rem`"
                >{{ word.text }}</span>
            </div>
            <!-- Screen-reader alternative: ordered list by frequency -->
            <details class="quorum--wordcloud-sr">
                <summary>{{ $t('wordCloud.ariaList') }}</summary>
                <ol>
                    <li v-for="word in sortedWords" :key="word.text">
                        {{ word.text }} ({{ word.freq }})
                    </li>
                </ol>
            </details>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { buildWordFrequency } from '../utils/wordCloud.js'
import { filterStopwords } from '../utils/stopwords.js'

const props = defineProps({
    responses: { type: Array, required: true },
    lang:      { type: String, default: 'de' },
})

const ACCENTS = ['petrol', 'magenta', 'brand', 'green', 'dark-violet']
const MIN_FONT = 0.8
const MAX_FONT = 2.4

const sortedWords = computed(() => {
    const freq = buildWordFrequency(props.responses)
    const words = filterStopwords(Object.keys(freq), props.lang)
    return words
        .map(text => ({ text, freq: freq[text] }))
        .sort((a, b) => b.freq - a.freq)
        .slice(0, 40)
})

function fontSize(freq) {
    const max = sortedWords.value[0]?.freq ?? 1
    const ratio = max > 1 ? (freq - 1) / (max - 1) : 0
    return (MIN_FONT + ratio * (MAX_FONT - MIN_FONT)).toFixed(2)
}
</script>

<style scoped>
.quorum--wordcloud {
    padding: 1rem;
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
}

.quorum--wordcloud-heading {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.75rem;
}

.quorum--wordcloud-empty {
    color: var(--quorum-muted);
    font-size: 0.875rem;
}

.quorum--wordcloud-canvas {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
    align-items: baseline;
    line-height: 1.2;
}

.quorum--wordcloud-word {
    font-weight: 600;
    transition: transform 80ms ease;
    cursor: default;
    user-select: none;
}

.quorum--wordcloud-word:hover {
    transform: scale(1.05);
}

/* Colour per accent class */
.acc-petrol      { color: var(--quorum-petrol); }
.acc-magenta     { color: var(--quorum-magenta); }
.acc-brand       { color: var(--quorum-brand); }
.acc-green       { color: var(--quorum-success); }
.acc-dark-violet { color: var(--quorum-dark-violet); }

.quorum--wordcloud-sr {
    margin-block-start: 1rem;
    font-size: 0.875rem;
}

.quorum--wordcloud-sr summary {
    cursor: pointer;
    color: var(--quorum-muted);
}

.quorum--wordcloud-sr ol {
    margin-block-start: 0.5rem;
    padding-inline-start: 1.5rem;
}

@media (prefers-reduced-motion: reduce) {
    .quorum--wordcloud-word {
        transition: none;
    }
    .quorum--wordcloud-word:hover {
        transform: none;
    }
}

@media (forced-colors: active) {
    .quorum--wordcloud-word {
        color: CanvasText;
    }
}
</style>
