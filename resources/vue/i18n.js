import { createI18n } from 'vue-i18n'
import de from './locales/de.json'
import en from './locales/en.json'

/**
 * Mini i18n layer for Quorum Vue apps.
 *
 * Stud.IP's `STUDIP.Vue.load()` registers its own `gettext` plugin (based on
 * `vue-gettext`). Quorum deliberately uses `vue-i18n` for its own strings so:
 *   1. Tests can run in Node without a Stud.IP bootstrap (`vue-gettext` needs
 *      build-time extraction, `vue-i18n` does not).
 *   2. Translation strings are versioned locally in the Quorum repo instead of
 *      flowing through Stud.IP global `.po` files.
 *
 * Source of truth: `locales/de.json` + `locales/en.json`. The JSONs are inlined
 * by Vite at build time; test code imports them directly. Keys are identical
 * in DE and EN.
 */
export const messages = { de, en }

export const createQuorumI18n = (locale = 'de') => createI18n({
    legacy:        false,   // Composition API mode
    locale,
    fallbackLocale: 'en',
    messages,
})
