import { defineAsyncComponent } from 'vue'

/**
 * Stud.IP base components for Quorum apps.
 *
 * These components are imported from the Stud.IP core and registered globally
 * on our own Vue app instances. They are bundled directly — same approach as
 * Vue itself (Stud.IP ships no ESM build).
 *
 * Requires `vue3-gettext` installed via `createStudipGettext()` so
 * `$gettext`/`$ngettext` resolve inside the Stud.IP components. See
 * studip-gettext.js.
 *
 * Vite alias `@studip` → `studip-core/resources/vue/`
 */

const components = {
    StudipIcon:              defineAsyncComponent(() => import('@studip/components/StudipIcon.vue')),
    StudipMessageBox:        defineAsyncComponent(() => import('@studip/components/StudipMessageBox.vue')),
    StudipActionMenu:        defineAsyncComponent(() => import('@studip/components/StudipActionMenu.vue')),
    StudipLoadingSkeleton:   defineAsyncComponent(() => import('@studip/components/StudipLoadingSkeleton.vue')),
    StudipProgressIndicator: defineAsyncComponent(() => import('@studip/components/StudipProgressIndicator.vue')),
    StudipSwitch:            defineAsyncComponent(() => import('@studip/components/StudipSwitch.vue')),
    StudipPagination:        defineAsyncComponent(() => import('@studip/components/StudipPagination.vue')),
}

/**
 * Registers all Stud.IP base components globally on a Vue app instance.
 * Call `registerStudipComponents(app)` before `app.mount(…)`.
 */
export function registerStudipComponents(app) {
    for (const [name, component] of Object.entries(components)) {
        app.component(name, component)
    }
}
