/**
 * Readable label for a poll type (card badges, lists). Translated via the
 * `pollType` namespace. Unknown/empty types return an empty string, so a raw
 * key is never shown as a badge.
 *
 * @param {(key: string) => string} t  vue-i18n translation function
 * @param {string} type                poll type (mc, multi, scales, emoji, freitext, matrix)
 * @returns {string}
 */
const KNOWN = new Set(['mc', 'multi', 'scales', 'emoji', 'freitext', 'matrix'])

export function pollTypeLabel(t, type) {
    return KNOWN.has(type) ? t(`pollType.${type}`) : ''
}
