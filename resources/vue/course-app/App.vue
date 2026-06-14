<template>
    <div class="quorum--course-app quorum--container">
        <RouterView />
    </div>
</template>

<script setup>
import { RouterView } from 'vue-router'
</script>

<style lang="scss">
/* Load tokens app-wide so components can use var(--quorum-*) without further imports. */
@use '../../scss/_studip-tokens.scss';
/* Shared poll card look (identical to the workplace). */
@use '../../scss/_poll-card.scss';

.quorum--course-app {
    background: var(--quorum-bg);
    color:      var(--quorum-fg);
    min-block-size: 100%;
    font-family: 'Lato', 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.5;
    /* Vertical padding in addition to horizontal padding from `.quorum--container` */
    padding-block: var(--quorum-space-md);
}
</style>
