<template>
    <div class="action-menu quorum--action-menu" :class="{ 'is-open': open, 'is-reversed': reversed }" ref="rootEl">
        <button
            ref="triggerEl"
            class="action-menu-icon"
            type="button"
            :title="label"
            :aria-label="label"
            aria-haspopup="menu"
            :aria-expanded="open ? 'true' : 'false'"
            :disabled="busy"
            @click.stop="toggle"
            @keydown="onTriggerKeydown"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div
            v-if="open"
            class="action-menu-content"
            role="menu"
            @keydown="onMenuKeydown"
        >
            <div v-if="menuTitle" class="action-menu-title">{{ menuTitle }}</div>
            <!-- role="none": ul/li are layout only — per ARIA no list role may
                 sit between role="menu" and the menuitem buttons
                 (axe: aria-required-children). -->
            <ul class="action-menu-list" role="none">
                <li
                    v-for="action in actions"
                    :key="action.id"
                    class="action-menu-item"
                    :class="{ 'action-menu-item-disabled': action.disabled }"
                    role="none"
                >
                    <button
                        type="button"
                        ref="menuItemEls"
                        role="menuitem"
                        :disabled="action.disabled"
                        @click.stop="select(action)"
                    >
                        {{ action.label }}
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { activeMenuId, nextMenuId } from './actionMenuRegistry.js'

// "Only one open" is guaranteed via the shared reactive `activeMenuId` from the
// registry (constructive, no timing race). The state MUST live in a separate
// module — a `<script setup>` "singleton" would run per instance and coordinate
// nothing (see actionMenuRegistry.js). The registry exists exactly once per app
// bundle → no leak between apps.

/**
 * Reusable three-dot action menu in the Stud.IP `action-menu` look.
 *
 * Reproduces the native markup (`.action-menu` / `.action-menu-icon` /
 * `.action-menu-content` / `.action-menu-list` / `.action-menu-item`) so
 * Stud.IP's global `actionmenu.scss` provides the styling — only the
 * positioning wrapper is local. Includes full keyboard support
 * (arrows/Home/End/Escape), focus return to the trigger, and click-outside to
 * close.
 *
 * Generic: knows no concrete actions, only renders `actions` and reports the
 * selection via the `select` event — the parent decides what happens (link,
 * download, dialog …).
 */
const props = defineProps({
    /** @type {{ id: string, label: string, disabled?: boolean }[]} */
    actions:   { type: Array,  required: true },
    /** aria-label of the trigger button. */
    label:     { type: String, default: 'Aktionen' },
    /** Optional heading in the opened menu. */
    menuTitle: { type: String, default: '' },
    /** Disable the trigger (e.g. while an action is running). */
    busy:      { type: Boolean, default: false },
    /**
     * Opening direction: 'auto' (default, Stud.IP convention upward with a
     * space measurement), 'down' (always downward — for menus that sit at the
     * TOP of the page, e.g. detail-page headers) or 'up' (always upward).
     */
    direction: { type: String, default: 'auto' },
})

const emit = defineEmits(['select'])

const myId        = nextMenuId()
const rootEl      = ref(null)
const triggerEl   = ref(null)
const menuItemEls = ref([])

// Open iff THIS instance is the active one. Single source of truth → opening
// another instance closes this one automatically.
const open = computed(() => activeMenuId.value === myId)

// Opening direction — Stud.IP convention (`is-reversed` in actionmenu.scss):
// card menus open UPWARDS so they do not cover the cards below. Only when
// there is not enough viewport space above the trigger (card at the very top)
// does the menu fall back to "downwards". Re-measured on every open
// (nextTick, once the content is in the DOM).
const reversed = ref(props.direction !== 'down')
watch(open, (isOpen) => {
    if (!isOpen) return
    // A fixed direction overrides the measurement — menus at the top of the
    // page (detail-page headers) reliably open downwards instead of over the
    // title/nav.
    if (props.direction === 'down') { reversed.value = false; return }
    if (props.direction === 'up')   { reversed.value = true;  return }
    reversed.value = true
    nextTick(() => {
        const trigger = triggerEl.value
        const content = rootEl.value?.querySelector('.action-menu-content')
        if (!trigger || !content) return
        const spaceAbove = trigger.getBoundingClientRect().top
        reversed.value = content.offsetHeight <= spaceAbove
    })
})

const focusTrigger = () => nextTick(() => triggerEl.value?.focus())
// Only focusable (non-disabled) items — disabled buttons take no focus and
// would otherwise block arrow-key navigation.
const enabledItems = () => menuItemEls.value.filter(b => b && !b.disabled)
const focusMenuItem = (index) => {
    nextTick(() => {
        const items = enabledItems()
        if (items.length === 0) return
        const i = (index + items.length) % items.length
        items[i]?.focus()
    })
}

const toggle = () => { activeMenuId.value = open.value ? null : myId }
const close  = (restoreFocus = false) => {
    if (open.value) activeMenuId.value = null
    if (restoreFocus) focusTrigger()
}

const select = (action) => {
    if (action.disabled) return
    if (open.value) activeMenuId.value = null
    emit('select', action)
    focusTrigger()
}

const onTriggerKeydown = (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()
        activeMenuId.value = myId
        focusMenuItem(event.key === 'ArrowUp' ? -1 : 0)
    } else if (event.key === 'Escape' && open.value) {
        event.preventDefault()
        close()
    }
}

const onMenuKeydown = (event) => {
    const items = enabledItems()
    const current = items.indexOf(document.activeElement)
    switch (event.key) {
        case 'Escape':
            event.preventDefault()
            close(true)
            break
        case 'ArrowDown':
            event.preventDefault()
            focusMenuItem(current < 0 ? 0 : current + 1)
            break
        case 'ArrowUp':
            event.preventDefault()
            focusMenuItem(current < 0 ? -1 : current - 1)
            break
        case 'Home':
            event.preventDefault()
            focusMenuItem(0)
            break
        case 'End':
            event.preventDefault()
            focusMenuItem(-1)
            break
    }
}

// Click outside the menu closes it.
const onDocumentClick = (event) => {
    if (open.value && rootEl.value && !rootEl.value.contains(event.target)) {
        activeMenuId.value = null
    }
}
onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    if (open.value) activeMenuId.value = null
})
</script>

<style scoped>
/* Stud.IP's global `actionmenu.scss` provides all `.action-menu-*` styles;
   locally only the positioning context for the popover is needed. */
.quorum--action-menu { position: relative; }

/* Upward opening: same values as Stud.IP's `.action-menu.is-reversed`
   (actionmenu.scss). Duplicated locally so the direction is also correct
   outside the Stud.IP frame (isolated previews, tests). */
.quorum--action-menu.is-reversed .action-menu-content {
    top: auto;
    bottom: -4px;
}

@media (prefers-contrast: more), (forced-colors: active) {
    .action-menu-icon { border-color: CanvasText; }
    .action-menu-icon span { background: CanvasText; }
}
</style>
