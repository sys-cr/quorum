<template>
    <PollsView :token="token" />
</template>

<script setup>
import PollsView from './PollsView.vue'

defineProps({
    token: { type: String, required: true },
})
</script>

<style lang="scss">
/* Load tokens globally so PollsView can reference var(--quorum-*). */
@use '../../scss/_studip-tokens.scss';
/* Stud.IP standard buttons — the anonymous vote page loads no core CSS. */
@use '../../scss/_studip-buttons.scss';
</style>
