<template>
    <section class="quorum-presenter__stage quorum-presenter__leaderboard">
        <QuizLeaderboard
            :entries="entries"
            :loading="loading"
            :error="error"
        />
    </section>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import QuizLeaderboard from '@/components/QuizLeaderboard.vue'

/**
 * Leaderboard stage in the presenter (projector view).
 *
 * Loads the pseudonymous board via the anonymous leaderboard endpoint of the
 * current question (collection total when the question is a member) and
 * refreshes every 5 s while the stage is visible.
 */

const props = defineProps({
    token: { type: String, required: true },
})

const root      = typeof document !== 'undefined'
    ? document.getElementById('quorum-presenter-app')
    : null
const pluginUrl = root?.dataset?.pluginUrl ?? ''

const entries = ref([])
const loading = ref(false)
const error   = ref(false)
let timer = null

const load = async () => {
    loading.value = entries.value.length === 0
    error.value   = false
    try {
        const res = await fetch(`${pluginUrl}/api/leaderboard/${encodeURIComponent(props.token)}`, {
            credentials: 'same-origin',
        })
        if (!res.ok) throw new Error(`http_${res.status}`)
        entries.value = (await res.json()).entries ?? []
    } catch {
        error.value = true
    } finally {
        loading.value = false
    }
}

watch(() => props.token, () => {
    entries.value = []
    if (timer) clearInterval(timer)
    load()
    timer = setInterval(load, 5000)
}, { immediate: true })

onBeforeUnmount(() => {
    if (timer) clearInterval(timer)
})
</script>

<style scoped lang="scss">
.quorum-presenter__leaderboard {
    display: flex;
    flex-direction: column;
    min-block-size: 0;
    overflow-y: auto;
    max-inline-size: min(100%, 900px);
    margin-inline: auto;
    inline-size: 100%;

    /* Projector readability: larger type than the shared default list. */
    :deep(.quorum--leaderboard-title) { font-size: clamp(1.2rem, 1.4vw + 0.7rem, 2rem); }
    :deep(.quorum--leaderboard-list li) { font-size: clamp(1rem, 1vw + 0.6rem, 1.5rem); }
}
</style>
