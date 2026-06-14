<template>
    <Teleport to="body">
        <FocusTrap v-model="trap">
            <div class="studip-dialog" @keydown.esc="close">
                <div class="studip-dialog-backdrop">
                    <div
                        :class="['studip-dialog-body', dialogModifiers]"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="titleId"
                        :aria-describedby="descId"
                        :style="dialogStyle"
                        ref="dialogEl"
                    >
                        <header class="studip-dialog-header">
                            <span :id="titleId" class="studip-dialog-title" role="heading" aria-level="2">
                                {{ resolvedTitle }}
                            </span>
                            <slot name="dialogHeader" />
                            <button
                                class="studip-dialog-close-button"
                                :aria-label="t('dialog.closeAria')"
                                :title="t('dialog.close')"
                                @click="close"
                            />
                        </header>

                        <section :id="descId" class="studip-dialog-content">
                            <slot name="dialogContent" />
                            <div v-if="question || alert">{{ question || alert }}</div>
                        </section>

                        <footer class="studip-dialog-footer">
                            <div class="studip-dialog-footer-buttonset-left">
                                <slot name="dialogButtonsBefore" />
                            </div>
                            <div class="studip-dialog-footer-buttonset-center">
                                <button
                                    v-if="confirmText"
                                    type="button"
                                    class="button"
                                    :class="confirmClass"
                                    :disabled="confirmDisabled"
                                    @click="confirm"
                                >
                                    {{ confirmText }}
                                </button>
                                <button
                                    v-else-if="question || alert"
                                    type="button"
                                    class="button accept"
                                    @click="confirm"
                                >
                                    {{ t('dialog.confirm') }}
                                </button>
                                <slot name="dialogButtons" />
                                <button
                                    v-if="closeText"
                                    type="button"
                                    class="button cancel"
                                    ref="closeBtn"
                                    @click="close"
                                >
                                    {{ closeText }}
                                </button>
                                <button
                                    v-else-if="question || alert"
                                    type="button"
                                    class="button cancel"
                                    ref="closeBtn"
                                    @click="close"
                                >
                                    {{ t('dialog.cancel') }}
                                </button>
                            </div>
                            <div class="studip-dialog-footer-buttonset-right">
                                <slot name="dialogButtonsAfter" />
                            </div>
                        </footer>
                    </div>
                </div>
            </div>
        </FocusTrap>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { FocusTrap } from 'focus-trap-vue'

/**
 * Lightweight Stud.IP dialog without vue-resizable.
 *
 * Uses the same CSS classes as StudipDialog (studip-dialog-body,
 * studip-dialog-header etc.) so Stud.IP themes and global styles apply
 * automatically. Responsive instead of fixed size; max-width controllable via
 * prop.
 *
 * Slots: dialogHeader, dialogContent, dialogButtonsBefore,
 *        dialogButtons, dialogButtonsAfter
 * Props: title, maxWidth, confirmText, confirmClass, confirmDisabled,
 *        closeText, question, alert
 * Events: close, confirm
 */

const props = defineProps({
    title:            String,
    maxWidth:         { type: [String, Number], default: 520 },
    confirmText:      String,
    confirmClass:     { type: String, default: 'accept' },
    confirmDisabled:  { type: Boolean, default: false },
    closeText:        String,
    question:         String,
    alert:            String,
})

const emit = defineEmits(['close', 'confirm'])

const { t } = useI18n()

const trap     = ref(true)
const closeBtn = ref(null)
const dialogEl = ref(null)

const titleId = useId()
const descId  = useId()

const dialogModifiers = computed(() => ({
    'studip-dialog-warning': !!props.question,
    'studip-dialog-alert':   !!props.alert,
}))

const dialogStyle = computed(() => ({
    maxWidth: typeof props.maxWidth === 'number' ? `${props.maxWidth}px` : props.maxWidth,
}))

const resolvedTitle = computed(() => {
    if (props.title) return props.title
    if (props.alert || props.question) return t('dialog.confirmTitle')
    return ''
})

const close   = () => emit('close')
const confirm = () => emit('confirm')

onMounted(() => {
    nextTick(() => closeBtn.value?.focus())
})
</script>

<style scoped>
/*
 * Stud.IP provides `.studip-dialog-*` classes globally in `dialog.scss`.
 * Here only overrides for responsive behavior (no fixed left/top/width):
 * the backdrop flex centers the dialog, `max-width` constrains it.
 */
.studip-dialog-backdrop {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    box-sizing: border-box;
}

.studip-dialog-body {
    /* Overrides `position: absolute` from Stud.IP CSS —
       we use flexbox centering instead of manual left/top. */
    position: relative !important;
    width: 100%;
    max-height: 90vh;
}
</style>
