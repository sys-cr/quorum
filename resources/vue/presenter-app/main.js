import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PresenterRoot from './components/PresenterRoot.vue'
import { createQuorumI18n } from '../i18n.js'
import { createStudipGettext } from '../studip-gettext.js'
// Stud.IP design tokens (--quorum-* = Stud.IP blue #28497c etc.). No App.vue in
// this app → load globally here, otherwise the tokens are undefined and the
// components lose their colors.
import '../../scss/_studip-tokens.scss'
// Stud.IP standard buttons (`.button`, `.button-group`) — the standalone page
// loads no core CSS, the partial replicates the core look.
import '../../scss/_studip-buttons.scss'

/**
 * Bootstrap of the Quorum presenter app.
 *
 * Vue/Pinia/vue-i18n are bundled in — Stud.IP 6 ships `vue.global.prod.js`
 * only as UMD/IIFE, which is not usable via ESM `import`.
 *
 * Separate Vite entry because the presenter has its own components
 * (Fullscreen API, keyboard shortcuts, auditorium dark mode).
 *
 * Mount lookup: `<div id="quorum-presenter-app" data-plugin-url=…
 * data-csrf=… data-return-url=…>` plus embedded JSON state in
 * `<script id="quorum-presenter-data">`.
 */
const mount = (root) => {
    const lang = (globalThis.STUDIP?.LANGUAGE_BASE ?? 'de').slice(0, 2)

    const app = createApp(PresenterRoot)
    app.use(createPinia())
    app.use(createQuorumI18n(lang))
    app.use(createStudipGettext(lang))
    app.mount(root)
    return app
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('quorum-presenter-app')
    if (root) mount(root)
}
