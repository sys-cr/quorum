/**
 * Builds a frequency map from an array of response strings.
 * @param {string[]} responses
 * @returns {Record<string, number>}
 */
export function buildWordFrequency(responses) {
    const freq = {}
    for (const response of responses) {
        const words = response.split(/\s+/)
        for (const raw of words) {
            const word = raw.toLowerCase().replace(/[.,;!?]+$/, '')
            if (!word || /^\d+$/.test(word)) continue
            freq[word] = (freq[word] ?? 0) + 1
        }
    }
    return freq
}
