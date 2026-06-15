/**
 * Card accent colour by meaning — no longer random by position.
 *
 * Each question type gets a fixed colour so teachers can grasp the type from
 * the coloured card edge before reading the type label. Collections (a
 * container, not a question type) use their own neutral slate colour.
 *
 * Token name === suffix of the `.acc-*` modifier class (see _studip-tokens.scss),
 * so both the class (`acc-<token>`) and the inline value (`var(--quorum-<token>)`)
 * derive from the same mapping — two card patterns, one source.
 *
 * @param {string} type  question type (mc, multi, scales, emoji, freitext, matrix)
 */
const TYPE_TOKEN = {
    mc:       'brand',        // Single choice — Stud.IP blue (the default case)
    multi:    'dark-violet',  // Multiple choice
    scales:   'petrol',       // Scale
    emoji:    'green',        // Emoji
    freitext: 'magenta',      // Free text / word cloud
    matrix:   'brown',        // Matrix
}

const COLLECTION_TOKEN = 'slate' // Collection: neutral container accent
const FALLBACK_TOKEN = 'petrol'

const tokenFor = (type) => TYPE_TOKEN[type] || FALLBACK_TOKEN

/** CSS class for the `:class` pattern (SurveysIndex, Archive). */
export const pollTypeAccentClass = (type) => `acc-${tokenFor(type)}`

/** Inline value for the `:style="{ '--quorum-acc': … }"` pattern (WorkplaceIndex, CollectionsIndex). */
export const pollTypeAccentVar = (type) => `var(--quorum-${tokenFor(type)})`

export const COLLECTION_ACCENT_CLASS = `acc-${COLLECTION_TOKEN}`
export const COLLECTION_ACCENT_VAR = `var(--quorum-${COLLECTION_TOKEN})`
