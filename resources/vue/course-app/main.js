import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import StudentView from './components/StudentView.vue'
import { createQuorumRouter } from './router.js'
import { createQuorumI18n } from '../i18n.js'
import { createStudipGettext } from '../studip-gettext.js'
import { registerStudipComponents } from '../studip-components.js'

/**
 * Bootstraps the teacher course-app.
 *
 * Vue/Pinia/vue-router/vue-i18n are bundled in — Stud.IP 6 ships
 * `vue.global.prod.js` only as a UMD/IIFE build, incompatible with
 * ESM `import { ... } from 'vue'`.
 *
 * Locale: `STUDIP.LANGUAGE_BASE` (e.g. "de_DE") is trimmed to its ISO stem
 * and passed to vue-i18n.
 *
 * Mount point: `<div id="quorum-course-app" data-cid="…">` in the PHP view.
 */
// `STUDIP.PLUGIN_URL` is not set in the full-page plugin context — the
// base-path-aware URL comes from the PHP view as `data-plugin-url` on the
// mount (`PluginEngine::getURL(..., absolute=true)`). Mirror it into the
// global so store/composable URL helpers build correct paths; otherwise their
// hardcoded root fallback 404s on subdirectory installs (`host/studip/…`).
const mirrorPluginUrl = (root) => {
    if (!root?.dataset?.pluginUrl) return
    globalThis.STUDIP = globalThis.STUDIP ?? {}
    const u = root.dataset.pluginUrl
    globalThis.STUDIP.PLUGIN_URL = u.endsWith('/') ? u : `${u}/`
}

// Reads the mount configuration from the data attributes (locale + role).
// Bundles the `??`/`slice` logic so `mount` stays flat.
const readMountProps = (root) => ({
    lang: (root?.dataset?.lang ?? globalThis.STUDIP?.LANGUAGE_BASE ?? 'de').slice(0, 2),
    role: root?.dataset?.role ?? '',
})

const mount = (root) => {
    mirrorPluginUrl(root)
    const { lang, role } = readMountProps(root)

    // Role switch: students get the lean, read-only StudentView (no vue-router,
    // no management actions) — only Pinia + i18n + studip-gettext + the Stud.IP
    // base components. For `tutor`/missing the previous behavior stays (App +
    // Router).
    if (role === 'student') {
        const app = createApp(StudentView)
        app.use(createPinia())
        app.use(createQuorumI18n(lang))
        app.use(createStudipGettext(lang))
        registerStudipComponents(app)
        app.mount(root)
        return app
    }

    // Archive sidebar view (`?view=archive`, set by IndexController) → set the
    // initial route to the archive. Set the hash BEFORE router creation so
    // `createWebHashHistory` reads it as the start route; a later
    // `router.replace` would be overridden by the initial `/` → `/surveys`
    // navigation.
    if (typeof location !== 'undefined' && !location.hash) {
        const view = new URLSearchParams(location.search).get('view')
        if (view === 'archive')          location.hash = '#/archive'
        else if (view === 'collections') location.hash = '#/collections'
    }

    const app    = createApp(App)
    const router = createQuorumRouter()
    app.use(createPinia())
    app.use(router)
    app.use(createQuorumI18n(lang))
    app.use(createStudipGettext(lang))
    registerStudipComponents(app)
    app.mount(root)
    return app
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('quorum-course-app')
    if (root) mount(root)
}

export { mount, App, StudentView }
