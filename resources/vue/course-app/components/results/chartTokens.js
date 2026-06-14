/**
 * Aurora token mapper for Chart.js — translates the `var(--quorum-*)` from
 * `_studip-tokens.scss` into concrete RGB strings that Chart.js needs.
 *
 * Aurora pattern:
 *
 *   - Collection accents rotate through petrol → green → magenta → brand → dark-violet
 *   - Same per data point in charts (1st option = petrol, 2nd = green, ...)
 *   - `forced-colors: active` (Windows HC): colors map to system color keywords,
 *     chart theme watcher triggers `chart.update('none')`
 *
 * Usage:
 *
 *     import { auroraColors } from './chartTokens.js'
 *     const palette = auroraColors(rootEl)   // ['#129c94', '#6ead10', ...]
 */

const AVATAR_TOKEN_NAMES = [
    '--quorum-petrol',
    '--quorum-green',
    '--quorum-magenta',
    '--quorum-brand',
    '--quorum-dark-violet',
]

/**
 * Reads the avatar palette from the CSS custom properties on the root element.
 * Fallback (for jsdom tests without the token CSS loaded): the static hex
 * values from `_studip-tokens.scss`.
 */
export const auroraColors = (rootEl = null) => {
    const fallback = ['#129c94', '#6ead10', '#b02e7c', '#28497c', '#682c8b']
    if (typeof window === 'undefined' || !rootEl) return fallback

    const styles = window.getComputedStyle(rootEl)
    return AVATAR_TOKEN_NAMES.map((name, i) => {
        const value = styles.getPropertyValue(name).trim()
        return value || fallback[i]
    })
}

/**
 * Picks the n-th accent color (cyclic).
 */
export const accentForIndex = (i, palette = null) => {
    const colors = palette ?? auroraColors()
    return colors[i % colors.length]
}

/**
 * High-contrast detection. Under `forced-colors: active` all chart colors map
 * to system color keywords (`Mark`, `LinkText`, `CanvasText`) — Chart.js
 * accepts these as CSS color strings.
 */
export const isForcedColors = () => {
    if (typeof window === 'undefined' || !window.matchMedia) return false
    return window.matchMedia('(forced-colors: active)').matches
}

export const forcedColorsPalette = () => ['Mark', 'LinkText', 'Mark', 'LinkText', 'Mark']

/**
 * HC main rule (applies to all chart types): high-contrast has no colors, at
 * most grayscale. Per chart type:
 *
 *   - Bar: single black; differentiated by height.
 *   - Donut/Bubble: grayscale palette + in-segment label (percent or count),
 *     since gray alone is too close for 4–5 levels.
 *   - Cloud: grayscale accent per word; differentiated by font size.
 */
export const grayscalePalette = () => ['#000000', '#404040', '#707070', '#a0a0a0', '#d0d0d0']

/**
 * Detects whether the current theme is high-contrast (manual toggle via the
 * `.theme-high-contrast` class, system preference `prefers-contrast`, or
 * Windows `forced-colors: active`).
 *
 * The class check mirrors the SCSS selector (`:root.theme-high-contrast,
 * .theme-high-contrast`) and checks both `<html>` and `<body>`, since the
 * class may be set on either.
 */
// Manual toggle: the `.theme-high-contrast` class may live on <html> OR
// <body> (the Storybook addon-themes sets it on the body). Extracted so
// `isHighContrast` stays flat.
const hasHighContrastClass = () => {
    const html = document?.documentElement?.classList?.contains('theme-high-contrast')
    const body = document?.body?.classList?.contains('theme-high-contrast')
    return html || body || false
}

export const isHighContrast = () => {
    if (typeof window === 'undefined') return false
    if (isForcedColors()) return true
    if (window.matchMedia?.('(prefers-contrast: more)').matches) return true
    return hasHighContrastClass()
}

/**
 * Segment palette for charts with multiple visually distinct areas
 * (donut, bubble). HC: grayscale, otherwise Aurora.
 */
export const segmentColors = (rootEl = null) =>
    isHighContrast() ? grayscalePalette() : auroraColors(rootEl)

/**
 * Reduced-motion detection — charts skip animations when the user has set this
 * in OS or browser.
 */
export const prefersReducedMotion = () => {
    if (typeof window === 'undefined' || !window.matchMedia) return false
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

/**
 * Default colors for Chart.js inline plugins (datalabels). Kept here because
 * Chart.js needs concrete color strings on the canvas (CSS custom properties
 * are not possible).
 *
 *   - `LABEL_OVERLAY_LIGHT`: white for labels over colored donut/bubble areas
 *   - `LABEL_OVERLAY_DARK`:  black for the default plugin fallback
 */
export const LABEL_OVERLAY_LIGHT = '#fff'
export const LABEL_OVERLAY_DARK  = '#000'

/**
 * Reads the **resolved theme foreground** at render time from the document —
 * Chart.js needs a concrete CSS color (not `var(--quorum-fg)`) so tick/legend
 * labels have sufficient contrast in light, dark, and HC modes.
 *
 * `getPropertyValue('--quorum-fg')` only returns the DECLARED string (e.g.
 * `var(--quorum-brand)`), not the RESOLVED one — browsers resolve CSS variable
 * references only when applied to a real property. Workaround: append an
 * invisible probe element with `color: var(--quorum-fg)`, then read
 * `getComputedStyle().color`, which is guaranteed to be a concrete `rgb(...)`.
 */
export const chartFg    = () => readResolvedTokenColor('--quorum-fg',     '#444')
export const chartMuted = () => readResolvedTokenColor('--quorum-muted',  '#888')
export const chartGrid  = () => readResolvedTokenColor('--quorum-border', '#ccc')

const readResolvedTokenColor = (varName, fallback) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') return fallback
    const probe = document.createElement('div')
    // Hidden + offscreen so the probe doesn't affect layout
    probe.style.cssText = `position:absolute;visibility:hidden;color:var(${varName})`
    document.body.appendChild(probe)
    const resolved = window.getComputedStyle(probe).color
    probe.remove()
    // If the variable is undefined, the browser usually returns `rgb(0, 0, 0)`
    // (CSS default for `color`) or an empty string. Both are problematic — use
    // the fallback.
    return (resolved && resolved !== '' && resolved !== 'rgba(0, 0, 0, 0)')
        ? resolved
        : fallback
}
