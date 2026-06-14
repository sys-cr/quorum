const STOPWORDS = {
    de: new Set([
        'der', 'die', 'das', 'und', 'ist', 'ein', 'eine', 'in', 'zu',
        'mit', 'von', 'auf', 'für', 'bei', 'aus', 'an', 'durch',
    ]),
    en: new Set([
        'the', 'a', 'an', 'and', 'is', 'are', 'in', 'to', 'of', 'for',
        'at', 'by', 'with', 'from', 'on',
    ]),
}

/**
 * Filters stopwords from a word list.
 * @param {string[]} words
 * @param {'de'|'en'} lang
 * @returns {string[]}
 */
export function filterStopwords(words, lang) {
    const set = STOPWORDS[lang] ?? new Set()
    return words.filter(w => !set.has(w.toLowerCase()))
}
