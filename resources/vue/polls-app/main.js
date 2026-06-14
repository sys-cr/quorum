import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import PollsView from './PollsView.vue'
import { createQuorumI18n } from '../i18n.js'
import { createStudipGettext } from '../studip-gettext.js'

/**
 * Bootstrap of the student polls app.
 *
 * Vue/Pinia/vue-i18n are bundled in — Stud.IP 6 ships `vue.global.prod.js`
 * only as UMD/IIFE, which is not usable via ESM `import`. Stud.IP's own
 * webpack apps do the same.
 *
 * Mount point: `<div id="quorum-polls-app" data-token="…">` in the PHP view.
 * Token passed via the `data-token` attribute.
 */
const mount = (root) => {
    const token = root.dataset.token ?? ''
    // `STUDIP.PLUGIN_URL` is NOT set on the anonymous vote page — the
    // base-path-aware URL arrives as `data-plugin-url` on the mount. Mirror it
    // so the store does not fall back to a hardcoded root path (which would
    // 404 on subdirectory installs).
    if (root?.dataset?.pluginUrl) {
        globalThis.STUDIP = globalThis.STUDIP ?? {}
        const u = root.dataset.pluginUrl
        globalThis.STUDIP.PLUGIN_URL = u.endsWith('/') ? u : `${u}/`
    }
    const lang  = (globalThis.STUDIP?.LANGUAGE_BASE ?? 'de').slice(0, 2)

    const app = createApp(App, { token })
    app.use(createPinia())
    app.use(createQuorumI18n(lang))
    app.use(createStudipGettext(lang))
    app.component('PollsView', PollsView)
    app.mount(root)
    return app
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('quorum-polls-app')
    if (root) mount(root)
}

export { mount, App, PollsView }
