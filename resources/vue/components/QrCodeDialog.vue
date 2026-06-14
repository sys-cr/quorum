<template>
    <Teleport to="body">
        <FocusTrap v-model="trapActive">
            <div
                :class="['quorum--qr-dialog', { 'is-fullscreen': isFullscreen }]"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                @keydown.esc.stop="onClose"
            >
                <div class="quorum--qr-dialog-inner">
                    <header class="quorum--qr-dialog-header">
                        <h2 :id="titleId" class="quorum--qr-dialog-title">
                            {{ t('qrDialog.heading') }}
                        </h2>
                        <button
                            type="button"
                            class="quorum--qr-fullscreen"
                            :aria-pressed="isFullscreen"
                            @click="toggleFullscreen"
                        >
                            {{ isFullscreen ? t('qrDialog.exitFullscreen') : t('qrDialog.fullscreen') }}
                        </button>
                        <button
                            type="button"
                            class="quorum--qr-close button"
                            @click="onClose"
                        >
                            {{ t('qrDialog.close') }}
                        </button>
                    </header>

                    <div class="quorum--qr-dialog-body">
                        <div
                            class="quorum--qr-code-wrap"
                            role="img"
                            :aria-label="t('qrDialog.alt', { title: pollTitle })"
                        >
                            <QrcodeVue
                                ref="qrRef"
                                :value="displayUrl"
                                :size="qrSize"
                                render-as="svg"
                            />
                        </div>

                        <p class="quorum--qr-url">
                            <span class="quorum--qr-url-label">{{ t('qrDialog.url') }}:</span>
                            {{ displayUrl }}
                        </p>
                    </div>

                    <footer class="quorum--qr-dialog-footer">
                        <button
                            type="button"
                            class="quorum--qr-download-svg button"
                            @click="downloadSvg"
                        >
                            {{ t('qrDialog.downloadSvg') }}
                        </button>
                        <button
                            type="button"
                            class="quorum--qr-download-png button"
                            @click="downloadPng"
                        >
                            {{ t('qrDialog.downloadPng') }}
                        </button>
                    </footer>
                </div>
            </div>
        </FocusTrap>
    </Teleport>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { FocusTrap } from 'focus-trap-vue'
import QrcodeVue from 'qrcode.vue'

const props = defineProps({
    pollTitle: { type: String, required: true },
    pollUrl:   { type: String, required: true },
    shortUrl:  { type: String, default: null },
})

const emit = defineEmits(['close'])

const { t }      = useI18n()
const titleId    = useId()
const trapActive = ref(true)
const isFullscreen = ref(false)
const qrRef      = ref(null)

const displayUrl = computed(() => props.shortUrl ?? props.pollUrl)
const qrSize     = computed(() => isFullscreen.value ? 600 : 400)

const onClose = () => emit('close')

const toggleFullscreen = () => {
    if (document.fullscreenEnabled && !isFullscreen.value) {
        document.documentElement.requestFullscreen().catch(() => {
            isFullscreen.value = true
        })
    } else if (document.fullscreenEnabled && isFullscreen.value) {
        document.exitFullscreen().catch(() => {})
    } else {
        // CSS overlay fallback when the Fullscreen API is unavailable
        isFullscreen.value = !isFullscreen.value
    }
}

const onFullscreenChange = () => {
    isFullscreen.value = !!document.fullscreenElement
}

onMounted(() => {
    document.addEventListener('fullscreenchange', onFullscreenChange)
})

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange)
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {})
})

const downloadSvg = () => {
    const svgEl = qrRef.value?.$el?.querySelector('svg') ?? qrRef.value?.$el
    if (!svgEl) return
    const blob = new Blob([svgEl.outerHTML], { type: 'image/svg+xml;charset=utf-8' })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = 'qrcode.svg'
    a.click()
    URL.revokeObjectURL(url)
}

const downloadPng = () => {
    const svgEl = qrRef.value?.$el?.querySelector('svg') ?? qrRef.value?.$el
    if (!svgEl) return

    const size   = qrSize.value
    const canvas = document.createElement('canvas')
    canvas.width  = size
    canvas.height = size
    const ctx    = canvas.getContext('2d')
    const img    = new Image()
    const svgBlob = new Blob([svgEl.outerHTML], { type: 'image/svg+xml;charset=utf-8' })
    const url     = URL.createObjectURL(svgBlob)

    img.onload = () => {
        ctx.drawImage(img, 0, 0, size, size)
        URL.revokeObjectURL(url)
        canvas.toBlob((blob) => {
            const pngUrl = URL.createObjectURL(blob)
            const a      = document.createElement('a')
            a.href       = pngUrl
            a.download   = 'qrcode.png'
            a.click()
            URL.revokeObjectURL(pngUrl)
        }, 'image/png')
    }
    img.src = url
}
</script>

<style scoped lang="scss">
.quorum--qr-dialog {
    position: fixed;
    inset: 0;
    z-index: 9000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, #000 50%, transparent);
    padding: 1rem;
    box-sizing: border-box;
}

.quorum--qr-dialog-inner {
    background: var(--quorum-bg);
    color: var(--quorum-fg);
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    padding: 1.5rem;
    inline-size: min(90vw, 36rem);
    max-block-size: 95vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.quorum--qr-dialog.is-fullscreen {
    padding: 0;

    .quorum--qr-dialog-inner {
        inline-size: 100vw;
        block-size: 100vh;
        max-block-size: 100vh;
        border-radius: 0;
        border: none;
        align-items: center;
        justify-content: center;
    }

    .quorum--qr-code-wrap { --qr-size: clamp(200px, 80vmin, 800px); }
}

.quorum--qr-dialog-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quorum--qr-dialog-title {
    flex: 1;
    font-size: 1.25rem;
    margin: 0;
    color: var(--quorum-fg);
}

.quorum--qr-dialog-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.quorum--qr-code-wrap {
    inline-size: clamp(200px, 80vw, 400px);
    aspect-ratio: 1;

    svg {
        inline-size: 100%;
        block-size: 100%;
    }
}

.quorum--qr-url {
    inline-size: 100%;
    word-break: break-all;
    font-size: 0.875rem;
    color: var(--quorum-muted);
    text-align: center;
    margin: 0;
}

.quorum--qr-url-label {
    font-weight: 600;
    color: var(--quorum-fg);
}

.quorum--qr-dialog-footer {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: center;
}

/* Mobile: min 200px QR on 375px viewport */
@media (max-width: 400px) {
    .quorum--qr-code-wrap {
        inline-size: clamp(200px, 85vw, 300px);
    }
    .quorum--qr-dialog-inner {
        padding: 1rem;
    }
}

@media (prefers-contrast: more) {
    .quorum--qr-dialog-inner {
        border-width: 3px;
        border-color: var(--quorum-fg);
    }
    .quorum--qr-dialog {
        background: rgba(0, 0, 0, 0.85);
    }
}

@media (forced-colors: active) {
    .quorum--qr-code-wrap {
        forced-color-adjust: none;
        background: white;
        padding: 4px;
    }
}
</style>
