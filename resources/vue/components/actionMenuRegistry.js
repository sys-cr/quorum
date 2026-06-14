import { ref } from 'vue'

/**
 * Shared state for QuorumActionMenu: at most ONE action menu open at a time —
 * per app bundle.
 *
 * This state MUST live in a separate module, not in the component's
 * `<script setup>`. The `<script setup>` top level is the setup() function and
 * runs PER INSTANCE — "singletons" declared there are really per-instance and
 * coordinate nothing. Module top level here runs exactly once and is shared by
 * all instances.
 *
 * `activeMenuId` is reactive; each instance derives its `open` as
 * `computed(activeMenuId === myId)`. Opening an instance (setting
 * `activeMenuId`) thus automatically closes all others.
 */
export const activeMenuId = ref(null)

let counter = 0
/** Returns a process-wide unique menu id. */
export const nextMenuId = () => ++counter
