import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

/**
 * Quorum bundles the full Vue stack (Vue, Pinia, vue-router, vue-i18n) itself,
 * because Stud.IP 6 ships `vue` only as a UMD/IIFE build (sets `window.Vue`),
 * not as ESM, and does not ship Pinia/vue-router/vue-i18n at all. Same pattern
 * as Stud.IP's own webpack apps (Courseware etc.).
 *
 * Vitest uses regular `import { … } from 'vue'` resolution (no external),
 * intentionally, since tests run in Node without the Stud.IP loader.
 */
export default defineConfig({
    // `base: './'` — relative asset URLs. Essential for lazily imported chunks
    // (e.g. `ResultsContainer` via `defineAsyncComponent`): Vite's runtime
    // preloader (`__vitePreload`) derives the CSS/JS hrefs of hashed async
    // chunks from `base`. The default `'/'` yields `/assets/<chunk>.css` —
    // absolute from the host root, NOT under the plugin path
    // `/plugins_packages/studip-quorum/.../public/assets/` → 404. With `'./'`
    // the preloader resolves relative to the module URL and hits the correct
    // plugin asset path. Entry assets are emitted by the PHP view from the
    // manifest (`$assetBaseUrl . '/public/' . $file`) and are unaffected, since
    // manifest paths are base-independent relative to outDir.
    base: './',
    plugins: [vue()],
    build: {
        // outDir = `public/`. Vite's default `assetsDir: 'assets'` places the
        // hashed bundles in `public/assets/`. Filesystem, manifest and PHP view
        // construction (`$assetBaseUrl . '/public/' . $css`) all line up.
        outDir: 'public',
        // `emptyOutDir: false` — `public/` also holds other Stud.IP plugin
        // assets besides the built `assets/` bundle; a wipe would delete them.
        emptyOutDir: false,
        manifest: true,
        rollupOptions: {
            input: {
                // `quorum-` prefixed bundle keys: output files are named
                // `quorum-courseapp-<hash>.js` etc. — instantly recognizable as
                // Quorum assets in the browser network tab or Apache logs.
                'quorum-courseapp':  resolve(__dirname, 'resources/vue/course-app/main.js'),
                'quorum-pollsview':  resolve(__dirname, 'resources/vue/polls-app/main.js'),
                'quorum-workplace':  resolve(__dirname, 'resources/vue/workplace-app/main.js'),
                'quorum-presenter':  resolve(__dirname, 'resources/vue/presenter-app/main.js'),
                'quorum-collection-actions': resolve(__dirname, 'resources/vue/collection-actions-app/main.js'),
            },
            output: { format: 'es' },
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/vue'),
            // Reference Stud.IP core components directly — bundled into our
            // output (same reason as Vue: Stud.IP ships no ESM build).
            '@studip': resolve(__dirname, '../studip-core/resources/vue'),
            '@studip-assets': resolve(__dirname, '../studip-core/resources/assets'),
        },
    },
})
