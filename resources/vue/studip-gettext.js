import { createGettext } from 'vue3-gettext'

/**
 * Minimal vue3-gettext setup for Quorum apps.
 *
 * Stud.IP core components (StudipDialog, StudipMessageBox, etc.) expect
 * `$gettext`/`$ngettext` as global Vue properties — installed via the official
 * `vue3-gettext` package that Stud.IP itself uses.
 *
 * We provide no own translation catalogs: the Stud.IP components contain their
 * strings in German (default) anyway. Our own strings stay in vue-i18n (t(…)).
 *
 * `silent: true` suppresses "missing translation" warnings in the browser —
 * without a catalog every Stud.IP string would otherwise be reported missing.
 *
 * vue3-gettext only knows `en_GB` for English (not `en_EN`), so we map the ISO
 * stems explicitly to supported locale codes instead of building
 * `${lang}_${lang.toUpperCase()}` (which produced the invalid `en_EN`).
 */
const LOCALE_MAP = {
    de: 'de_DE',
    en: 'en_GB',
}

export function createStudipGettext(lang = 'de') {
    const base   = lang.includes('_') ? lang.slice(0, 2) : lang
    const locale = lang.includes('_')
        ? lang
        : (LOCALE_MAP[base] ?? 'de_DE')
    return createGettext({
        availableLanguages: {
            de_DE: 'Deutsch',
            en_GB: 'English',
        },
        defaultLanguage: locale,
        silent: true,
        translations: {},
        setGlobalProperties: true,
    })
}
