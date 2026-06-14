/**
 * Shared plugin base URL helper for all Quorum Vue apps.
 *
 * `STUDIP.PLUGIN_URL` is NOT guaranteed to be set in plugin context
 * (full-page/tab mounts set it in their `main.js` from the mount's
 * `data-plugin-url`).
 *
 * Always returns WITH a trailing slash so callers can simply build
 * `${pluginUrl()}api/...`.
 */
const FALLBACK = '/plugins.php/quorumstudipplugin/'

export const pluginUrl = () => {
    const u = globalThis.STUDIP?.PLUGIN_URL ?? FALLBACK
    return u.endsWith('/') ? u : `${u}/`
}
