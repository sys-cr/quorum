import { createApp } from 'vue'
import { createPinia } from 'pinia'
import WorkplaceIndex from './components/WorkplaceIndex.vue'
import WorkplaceCompare from './components/WorkplaceCompare.vue'
import CollectionsIndex from './components/CollectionsIndex.vue'
import PollResults from './components/PollResults.vue'
import { createQuorumI18n } from '../i18n.js'
import { createStudipGettext } from '../studip-gettext.js'
import { registerStudipComponents } from '../studip-components.js'
// Stud.IP design tokens (--quorum-* = Stud.IP blue #28497c etc.). This app has
// no App.vue, so import them globally here — otherwise the tokens are undefined
// and components fall back to default colors.
import '../../scss/_studip-tokens.scss'
// Shared poll card look (identical to the course tab).
import '../../scss/_poll-card.scss'

/**
 * Bootstraps the Quorum workplace admin full page.
 *
 * Rendered by the WorkplaceController; runs in the Stud.IP PageLayout frame
 * outside any course. The Stud.IP workplace tile (`/contents/quorum`) only
 * links here and needs no Vue code itself.
 *
 * Mode switch via the `data-mode` attribute on the mount container:
 *   - `data-mode="compare"`     → mounts `WorkplaceCompare` (peer instruction)
 *   - `data-mode="collections"` → mounts `CollectionsIndex` (collection cards)
 *   - `data-mode="results"`     → mounts `PollResults` (retrospective + export)
 *   - otherwise (default)       → mounts `WorkplaceIndex`
 *
 * Vue/Pinia/vue-i18n are bundled in — Stud.IP 6 ships `vue.global.prod.js`
 * only as UMD/IIFE, incompatible with ESM `import`.
 */
const componentFor = (root) => {
    const mode = root?.dataset?.mode ?? 'index'
    if (mode === 'compare')     return WorkplaceCompare
    if (mode === 'collections') return CollectionsIndex
    if (mode === 'results')     return PollResults
    return WorkplaceIndex
}

const mount = (root) => {
    // `STUDIP.LANGUAGE_BASE` is not set in the full-page plugin context — the
    // PHP view provides the user language as `data-lang` on the mount. Without
    // this read the workplace would render in German for all non-German users
    // despite existing translations.
    const lang      = (root?.dataset?.lang ?? globalThis.STUDIP?.LANGUAGE_BASE ?? 'de').slice(0, 2)
    const component = componentFor(root)

    const app = createApp(component)
    app.use(createPinia())
    app.use(createQuorumI18n(lang))
    app.use(createStudipGettext(lang))
    registerStudipComponents(app)
    app.mount(root)
    return app
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('quorum-workplace-app')
    if (root) mount(root)
}
