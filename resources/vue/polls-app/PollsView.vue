<template>
    <main class="quorum--polls-app" :data-token="token" :lang="$i18n.locale">
        <!-- Loading -->
        <div v-if="store.loading" class="polls-state polls-state--loading" role="status" aria-live="polite">
            <p>{{ $t('polls.loading') }}</p>
        </div>

        <!-- Error -->
        <div v-else-if="store.error" class="polls-state polls-state--error" role="alert">
            <h1 class="polls-headline">{{ $t('polls.error') }}</h1>
            <p>{{ store.error }}</p>
        </div>

        <!-- Server reports the poll as ended (HTTP 410) — friendly end state
             instead of an error. -->
        <section v-else-if="store.ended" class="polls-state polls-state--expired" role="status" aria-live="polite">
            <h1 class="polls-headline">{{ $t('polls.ended') }}</h1>

            <!-- Quiz learning effect: after a quiz question ends, show the
                 correct answer + personal verdict. Only when the solution is
                 present (the server provides it only after the end). -->
            <div
                v-if="store.currentPoll?.quiz_mode && store.solution"
                class="polls-solution"
            >
                <p class="polls-solution-correct">
                    {{ $t('polls.solutionHeading') }}
                    <strong>{{ correctLabels }}</strong>
                </p>

                <!-- Own answer was correct -->
                <p
                    v-if="store.ownAnswerCorrect === true"
                    class="polls-verdict polls-verdict--right"
                    role="status"
                >
                    <span class="polls-verdict-icon" aria-hidden="true">✓</span>
                    {{ $t('polls.youWereRight') }}
                </p>

                <!-- Own answer was wrong — including own choice -->
                <p
                    v-else-if="store.ownAnswerCorrect === false"
                    class="polls-verdict polls-verdict--wrong"
                    role="status"
                >
                    <span class="polls-verdict-icon" aria-hidden="true">✗</span>
                    {{ $t('polls.youWereWrong') }}
                    <span class="polls-verdict-choice">
                        {{ $t('polls.yourChoiceWas', { answer: ownChoiceLabel }) }}
                    </span>
                </p>
            </div>
        </section>

        <!-- Confirmation after submit; in quiz mode with a live leaderboard -->
        <section v-else-if="store.isAnswered" class="polls-state polls-state--thanks" role="status" aria-live="polite">
            <h1 class="polls-headline">{{ $t('polls.thanks') }}</h1>
            <p>{{ $t('polls.submitted') }}</p>
            <QuizLeaderboard
                v-if="store.currentPoll?.quiz_mode"
                class="polls-leaderboard"
                :entries="leaderboard.entries"
                :loading="leaderboard.loading"
                :error="leaderboard.error"
            />
        </section>

        <!-- Time expired (toggled locally once the countdown reaches 0).
             No further voting possible. -->
        <section v-else-if="store.timeExpired" class="polls-state polls-state--expired" role="status" aria-live="polite">
            <h1 class="polls-headline">{{ $t('countdown.expired') }}</h1>
            <p>{{ $t('polls.thanks') }}</p>
        </section>

        <!-- Waiting state — voting stopped or not yet started. The status
             watch switches automatically as soon as the owner starts (also to
             the next question of the same collection). -->
        <section v-else-if="store.waiting" class="polls-state polls-state--waiting" role="status" aria-live="polite">
            <h1 class="polls-headline">{{ $t('polls.waiting') }}</h1>
            <p>{{ $t('polls.waitingHint') }}</p>
            <span class="polls-waiting-dots" aria-hidden="true"><i></i><i></i><i></i></span>
        </section>

        <!-- Active question (all types). Countdown above the question,
             only when a time limit is set (remaining_seconds !== null). -->
        <template v-else-if="store.hasPoll">
            <PollCountdown
                v-if="store.hasTimeLimit"
                class="polls-countdown"
                :remaining-seconds="store.remainingSeconds"
                :server-now="store.currentPoll.server_now ?? null"
                :expires-at="store.currentPoll.expires_at ?? null"
                @expired="onExpired"
            />

            <!-- Free text -->
            <FreitextQuestion
                v-if="pollType === 'freitext'"
                :question="store.currentPoll.question"
                :disabled="submitDisabled"
                @submit="submit"
            />

            <!-- Emoji -->
            <EmojiReaction
                v-else-if="pollType === 'emoji'"
                :question="store.currentPoll.question"
                :options="store.availableOptions"
                :disabled="submitDisabled"
                @submit="submit"
            />

            <!-- Matrix -->
            <MatrixQuestion
                v-else-if="pollType === 'matrix'"
                :question="store.currentPoll.question"
                :rows="store.currentPoll.options?.rows ?? []"
                :scale="store.currentPoll.options?.scale ?? []"
                :disabled="submitDisabled"
                @submit="submit"
            />

            <!-- Multiple choice with multi-select -->
            <MultiChoiceQuestion
                v-else-if="pollType === 'multi'"
                :question="store.currentPoll.question"
                :options="store.availableOptions"
                :disabled="submitDisabled"
                @submit="submit"
            />

            <!-- MC / scales (default). Outer = frame/padding, inner = content area. -->
            <form v-else class="polls-form polls-form--aurora" @submit.prevent>
                <div class="polls-form-inner">
                    <h1 class="polls-headline polls-question">{{ store.currentPoll.question }}</h1>
                    <p v-if="store.error" class="polls-submit-error" role="alert">{{ $t('polls.submitError') }}</p>

                    <!-- Quiz: pseudonymous leaderboard opt-in — active checkbox
                         + freely chosen nickname. Without the opt-in the answer
                         counts but does not appear on the leaderboard. -->
                    <div v-if="store.currentPoll.quiz_mode" class="polls-quiz-optin">
                        <label class="polls-quiz-check">
                            <input type="checkbox" v-model="quizOptIn">
                            {{ $t('quiz.optIn') }}
                        </label>
                        <label v-if="quizOptIn" class="polls-quiz-nickname">
                            <span class="visually-hidden">{{ $t('quiz.nicknameLabel') }}</span>
                            <input
                                type="text"
                                v-model="quizNickname"
                                maxlength="40"
                                :placeholder="$t('quiz.nicknamePlaceholder')"
                            >
                        </label>
                    </div>

                    <fieldset class="polls-options" :disabled="submitDisabled">
                        <legend class="visually-hidden">{{ $t('polls.choose') }}</legend>
                        <button
                            v-for="(option, index) in store.availableOptions"
                            :key="option.id"
                            type="button"
                            class="polls-option"
                            :class="accentClass(index)"
                            :disabled="submitDisabled"
                            @click="submit({ selected: option.id })"
                        >{{ option.label }}</button>
                    </fieldset>
                </div>
            </form>
        </template>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { usePollsStore } from './stores/usePollsStore.js'
import { pluginUrl } from '../pluginUrl.js'
import FreitextQuestion   from './components/FreitextQuestion.vue'
import EmojiReaction      from './components/EmojiReaction.vue'
import MatrixQuestion     from './components/MatrixQuestion.vue'
import MultiChoiceQuestion from './components/MultiChoiceQuestion.vue'
import PollCountdown    from '../components/PollCountdown.vue'
import QuizLeaderboard  from '../components/QuizLeaderboard.vue'

const props = defineProps({
    token: { type: String, required: true },
})

const store = usePollsStore()

// Quiz: leaderboard opt-in + nickname. Both persist in localStorage so
// follow-up questions of the same session (auto-follow) keep the name —
// revocable via the checkbox before every answer.
const NICK_KEY  = 'quorum.nickname'
const OPTIN_KEY = 'quorum.quizOptIn'
const readLocal = (k) => { try { return localStorage.getItem(k) } catch { return null } }
const quizOptIn    = ref(readLocal(OPTIN_KEY) === '1')
const quizNickname = ref(readLocal(NICK_KEY) ?? '')
watch(quizOptIn,    (v) => { try { localStorage.setItem(OPTIN_KEY, v ? '1' : '0') } catch { /* best effort */ } })
watch(quizNickname, (v) => { try { localStorage.setItem(NICK_KEY, v) } catch { /* best effort */ } })

// Leaderboard data (loaded only in quiz mode after answering, with a gentle
// auto-refresh while the thanks view is open).
const leaderboard = reactive({ entries: [], loading: false, error: false })
let leaderboardTimer = null

const loadLeaderboard = async () => {
    leaderboard.loading = leaderboard.entries.length === 0
    leaderboard.error   = false
    try {
        const res = await fetch(`${pluginUrl()}api/leaderboard/${encodeURIComponent(store.token ?? props.token)}`, {
            credentials: 'omit',
        })
        if (!res.ok) throw new Error(`http_${res.status}`)
        leaderboard.entries = (await res.json()).entries ?? []
    } catch {
        leaderboard.error = true
    } finally {
        leaderboard.loading = false
    }
}

// On entering the thanks view of a quiz question, load the board + refresh
// every 5 s (scores keep growing while others are still answering).
watch(() => store.isAnswered && !!store.currentPoll?.quiz_mode, (active) => {
    if (leaderboardTimer) { clearInterval(leaderboardTimer); leaderboardTimer = null }
    if (active) {
        loadLeaderboard()
        leaderboardTimer = setInterval(() => {
            if (typeof document === 'undefined' || !document.hidden) loadLeaderboard()
        }, 5000)
    }
}, { immediate: true })

onMounted(async () => {
    await store.loadPoll(props.token)
    // Live sync — reacts to voting start/stop without a reload. No token
    // argument so an auto-follow that already happened (loadPoll → followTo)
    // is not overwritten.
    store.startStatusWatch()
})

onBeforeUnmount(() => {
    store.stopStatusWatch()
    if (leaderboardTimer) clearInterval(leaderboardTimer)
})

const pollType = computed(() => store.currentPoll?.type ?? 'mc')

// Submit is locked while a request is running OR time has expired.
const submitDisabled = computed(() => store.submitting || store.timeExpired)

const submit = async (payload) => {
    try {
        // Quiz: only send the nickname with an active opt-in — the server
        // computes the points and stores the name pseudonymously.
        const nickname = quizOptIn.value ? quizNickname.value.trim() : ''
        if (store.currentPoll?.quiz_mode && nickname !== '') {
            payload = { ...payload, nickname }
        }
        await store.submitAnswer(payload)
    } catch {
        // Error is in store.error → rendered in the template
    }
}

// Countdown reaches 0. Switch locally to "ended" (immediate UI consistency) AND
// reload — the server then returns inactive/410 and remains authoritative.
const onExpired = () => {
    store.markExpired()
    store.loadPoll(props.token)
}

// Answer options cycle through the accent palette — each option gets its own color.
const ACCENTS = ['acc-petrol', 'acc-green', 'acc-magenta', 'acc-brand', 'acc-dark-violet']
const accentClass = (i) => ACCENTS[i % ACCENTS.length]

// Quiz learning effect: resolve the label(s) of the correct option(s) from the
// solution (correct = IDs, options = [{id,label}]). Multiple correct answers
// are joined with a comma.
const correctLabels = computed(() => {
    const sol = store.solution
    if (!sol) return ''
    const byId = new Map((sol.options ?? []).map(o => [o.id, o.label]))
    return (sol.correct ?? [])
        .map(id => byId.get(id) ?? id)
        .join(', ')
})

// Label of the own (wrong) choice — from the solution, otherwise from the poll
// options as a fallback.
const ownChoiceLabel = computed(() => {
    const id = store.ownChoiceId
    if (id === null) return ''
    const fromSolution = (store.solution?.options ?? []).find(o => o.id === id)
    if (fromSolution) return fromSolution.label
    const fromPoll = (store.currentPoll?.options ?? []).find(o => o.id === id)
    return fromPoll?.label ?? id
})
</script>

<style scoped>
.quorum--polls-app {
    padding: 1rem;
    max-width: 480px;
    margin-inline: auto;
    color: var(--quorum-fg);
    background: var(--quorum-bg);
    min-block-size: 100vh;
}

/* Loading/error/thanks states use the hero background */
.polls-state {
    background: var(--quorum-hero-bg);
    color: var(--quorum-hero-fg);
    border: 1px solid var(--quorum-hero-border);
    border-radius: var(--quorum-radius);
    padding: 2rem 1rem;
    text-align: center;
}
.polls-state--error {
    background: color-mix(in srgb, var(--quorum-error) 12%, var(--quorum-bg));
    border-color: var(--quorum-error);
    color: var(--quorum-fg);
}
.polls-state--thanks {
    background: color-mix(in srgb, var(--quorum-success) 12%, var(--quorum-bg));
    border-color: var(--quorum-success);
    color: var(--quorum-fg);
}
.polls-state--thanks .polls-headline::before {
    content: '✓ ';
    color: var(--quorum-success);
    font-weight: 800;
}
.polls-state--expired {
    background: color-mix(in srgb, var(--quorum-error) 12%, var(--quorum-bg));
    border-color: var(--quorum-error);
    color: var(--quorum-fg);
}
.polls-state--expired .polls-headline::before {
    content: '⏰ ';
}

/* Quiz learning effect: learning block after a quiz question ends.
   Left-aligned for readability, clearly set off below the "ended" heading. */
.polls-solution {
    margin-block-start: 1.25rem;
    text-align: start;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.polls-solution-correct {
    margin: 0;
    padding: 0.6rem 0.8rem;
    border-radius: var(--quorum-radius);
    background: color-mix(in srgb, var(--quorum-fg) 6%, var(--quorum-bg));
    color: var(--quorum-fg);
    overflow-wrap: break-word;
}

/* Verdict: status is NEVER conveyed by color alone — always a ✓/✗ symbol +
   text. The symbol is aria-hidden, the text carries the meaning. */
.polls-verdict {
    margin: 0;
    padding: 0.6rem 0.8rem;
    border-radius: var(--quorum-radius);
    font-weight: 600;
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.4rem;
    border: 1px solid transparent;
}
.polls-verdict-icon {
    font-weight: 800;
    font-size: 1.1em;
    line-height: 1;
}
.polls-verdict--right {
    background: color-mix(in srgb, var(--quorum-success) 14%, var(--quorum-bg));
    border-color: var(--quorum-success);
    color: var(--quorum-fg);
}
.polls-verdict--right .polls-verdict-icon { color: var(--quorum-success); }
.polls-verdict--wrong {
    background: color-mix(in srgb, var(--quorum-error) 14%, var(--quorum-bg));
    border-color: var(--quorum-error);
    color: var(--quorum-fg);
}
.polls-verdict--wrong .polls-verdict-icon { color: var(--quorum-error); }
.polls-verdict-choice {
    font-weight: 500;
    flex-basis: 100%;
}

@media (prefers-contrast: more) {
    .polls-verdict { border-width: 2px; }
}

/* Waiting state — neutral-informative (petrol tint); the status is also
   communicated via text + dot indicator (never color alone). */
.polls-state--waiting {
    background: color-mix(in srgb, var(--quorum-petrol) 10%, var(--quorum-bg));
    border-color: var(--quorum-petrol);
    color: var(--quorum-fg);
}
.polls-waiting-dots {
    display: inline-flex;
    gap: 0.4rem;
    margin-block-start: 0.75rem;
}
.polls-waiting-dots i {
    inline-size: 0.55rem;
    block-size: 0.55rem;
    border-radius: 50%;
    background: var(--quorum-petrol);
    animation: polls-waiting-pulse 1.4s ease-in-out infinite;
}
.polls-waiting-dots i:nth-child(2) { animation-delay: 0.2s; }
.polls-waiting-dots i:nth-child(3) { animation-delay: 0.4s; }
@keyframes polls-waiting-pulse {
    0%, 80%, 100% { opacity: 0.25; transform: scale(0.8); }
    40%           { opacity: 1;    transform: scale(1); }
}
@media (prefers-reduced-motion: reduce) {
    .polls-waiting-dots i { animation: none; opacity: 0.6; }
}

/* Countdown above the active question, with spacing below */
.polls-countdown {
    margin-block-end: 1rem;
}

/* Quiz opt-in (checkbox + nickname) above the answer options */
.polls-quiz-optin {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-block-end: 1rem;
    padding: 0.75rem;
    border: 1px dashed var(--quorum-petrol);
    border-radius: var(--quorum-radius);
    background: color-mix(in srgb, var(--quorum-petrol) 6%, var(--quorum-bg));
}
.polls-quiz-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}
.polls-quiz-nickname input {
    inline-size: 100%;
    box-sizing: border-box;
    min-block-size: 44px; /* touch target */
    padding: 0.5rem 0.75rem;
    font: inherit;
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
    color: var(--quorum-fg);
}

/* Leaderboard in the thanks view, left-aligned for readability */
.polls-leaderboard {
    margin-block-start: 1rem;
    text-align: start;
}

/*
 * Fluid font sizes for the question:
 *   375 px viewport → ~1.15 rem (~18 px)
 *  1280 px viewport → ~1.5 rem  (~24 px)
 * `hyphens: auto` breaks long compound words; requires a `lang` attribute on an
 * ancestor — see `<main :lang="…">`. `overflow-wrap: break-word` is the
 * fallback when the browser cannot hyphenate or a word has no break pattern.
 */
.polls-headline {
    font-size: clamp(1.15rem, 1rem + 1.2vw, 1.5rem);
    line-height: 1.3;
    margin: 0 0 1rem;
    hyphens: auto;
    overflow-wrap: break-word;
    word-break: normal;
}

.polls-question {
    /* slight hint for long questions */
    text-wrap: balance;
}

/* Outline around question + answers: outer container is the frame, inner div
   holds the content area. */
.polls-form--aurora {
    /* Flat bordered frame. */
    background: var(--quorum-bg);
    border: 1px solid var(--quorum-border);
    padding: 0;
    border-radius: var(--quorum-radius);
    overflow: hidden;
    box-shadow: 0 1px 3px color-mix(in srgb, var(--quorum-fg) 12%, transparent);
}
.polls-form-inner {
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    padding: 1.25rem;
    border-radius: var(--quorum-radius);
}

.polls-submit-error {
    color: var(--quorum-error);
    font-weight: 600;
    margin: 0 0 0.75rem;
}

.polls-options {
    border: 0;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.polls-options:disabled .polls-option {
    opacity: 0.6;
    cursor: not-allowed;
}

.polls-option {
    min-height: 44px; /* touch target ≥ 44×44 px */
    padding: 0.75rem 1rem;
    font: inherit;
    text-align: start;
    cursor: pointer;
    border: 2px solid var(--quorum-acc, var(--quorum-accent));
    border-inline-start-width: 8px;
    background: color-mix(in srgb, var(--quorum-acc, var(--quorum-accent)) 8%, var(--quorum-bg));
    color: var(--quorum-fg);
    border-radius: var(--quorum-radius);
    overflow-wrap: break-word;
    hyphens: auto;
    transition: background-color 120ms ease, transform 80ms ease;
    font-weight: 500;
}

.polls-option:hover {
    background: color-mix(in srgb, var(--quorum-acc, var(--quorum-accent)) 18%, var(--quorum-bg));
}

/*
 * Visible keyboard focus (WCAG 2.4.7). `:focus-visible` applies only to
 * keyboard navigation (Tab), not mouse clicks — avoiding post-click focus-ring
 * noise while guaranteeing a visible marker for keyboard users.
 */
.polls-option:focus-visible {
    background: color-mix(in srgb, var(--quorum-acc, var(--quorum-accent)) 22%, var(--quorum-bg));
    outline: var(--quorum-focus-ring);
    outline-offset: 2px;
}

/* Visual tap feedback (even without JS state) */
.polls-option:active {
    transform: scale(0.98);
}

/* Reduced motion: no micro-animations for users who opted out */
@media (prefers-reduced-motion: reduce) {
    .polls-option {
        transition: none;
    }
    .polls-option:active {
        transform: none;
    }
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (prefers-contrast: more) {
    .polls-option {
        border-width: 3px;
    }
}

@media (forced-colors: active) {
    .polls-option {
        border-color: ButtonText;
        color: ButtonText;
    }
}
</style>
