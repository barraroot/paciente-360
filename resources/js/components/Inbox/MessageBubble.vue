<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatDateTime } from '@/composables/useI18nFormat.js';

const { t } = useI18n();

const props = defineProps({
    /** @type {Object} MessageResource */
    message: {
        type: Object,
        required: true,
    },
    /**
     * Mensagem anterior para agrupar sender.
     * @type {Object|null}
     */
    previousMessage: {
        type: Object,
        default: null,
    },
});

// ─── Direção e agrupamento ────────────────────────────────────────────────────

const isOutbound = computed(() => props.message.direction === 'out');

/** Agrupa com a mensagem anterior se mesmo sender e < 3 min de diferença */
const isGrouped = computed(() => {
    if (!props.previousMessage) { return false; }
    if (props.previousMessage.direction !== props.message.direction) { return false; }
    if (props.previousMessage.sender_type !== props.message.sender_type) { return false; }

    const prev = new Date(props.previousMessage.created_at).getTime();
    const curr = new Date(props.message.created_at).getTime();
    return curr - prev < 3 * 60 * 1000; // 3 minutos
});

const senderName = computed(() => {
    if (props.message.sender_type === 'patient') {
        return props.message.sender?.nome_completo ?? props.message.sender?.name ?? 'Paciente';
    }
    if (props.message.sender_type === 'ai') {
        return 'IA';
    }
    if (props.message.sender_type === 'system') {
        return 'Sistema';
    }
    return props.message.sender?.name ?? 'Atendente';
});

// ─── Status de envio ──────────────────────────────────────────────────────────

const statusConfig = computed(() => {
    const s = props.message.status;
    if (!s || !isOutbound.value) { return null; }

    const map = {
        queued: { label: t('inbox.mensagem.fila'), icon: '⏱', color: 'text-foreground-muted' },
        sent: { label: t('inbox.mensagem.enviado'), icon: '✓', color: 'text-foreground-muted' },
        delivered: { label: t('inbox.mensagem.entregue'), icon: '✓✓', color: 'text-foreground-muted' },
        read: { label: t('inbox.mensagem.lido'), icon: '✓✓', color: 'text-primary-600' },
        failed: {
            label: t('inbox.mensagem.falhou'),
            icon: '⚠',
            color: 'text-danger-600',
            reason: props.message.failure_reason ?? '',
        },
    };
    return map[s] ?? null;
});

// ─── Mídia ────────────────────────────────────────────────────────────────────

const hasMedia = computed(() => {
    const media = props.message.media ?? [];
    return media.length > 0;
});

const mediaItems = computed(() => props.message.media ?? []);

/** Controle do aviso LGPD por item de mídia */
const lgpdAccepted = ref({});

function lgpdKey(mediaId) {
    const convId = props.message.conversation_id;
    // Persiste por (conversa_id, mídia_id) no localStorage
    return `lgpd_media_${convId}_${mediaId}`;
}

function isLgpdAccepted(mediaId) {
    if (lgpdAccepted.value[mediaId]) { return true; }
    return localStorage.getItem(lgpdKey(mediaId)) === '1';
}

function acceptLgpd(mediaId) {
    localStorage.setItem(lgpdKey(mediaId), '1');
    lgpdAccepted.value = { ...lgpdAccepted.value, [mediaId]: true };
}

/** Modal de media full-size */
const mediaModal = ref({ open: false, url: null, type: null, filename: null });

function openMedia(media) {
    if (!isLgpdAccepted(media.id)) { return; } // Não abre sem aceitar LGPD
    mediaModal.value = {
        open: true,
        url: media.url,
        type: media.mime_type ?? '',
        filename: media.filename ?? 'mídia',
    };
}

function closeMediaModal() {
    mediaModal.value = { open: false, url: null, type: null, filename: null };
}

function isImage(mimeType) {
    return (mimeType ?? '').startsWith('image/');
}

function isAudio(mimeType) {
    return (mimeType ?? '').startsWith('audio/');
}

function isVideo(mimeType) {
    return (mimeType ?? '').startsWith('video/');
}

// ─── Template ─────────────────────────────────────────────────────────────────

const isTemplate = computed(() => props.message.type === 'template');

const templateName = computed(() => {
    return props.message.template?.friendly_name
        ?? props.message.template?.name
        ?? props.message.template_name
        ?? null;
});

// ─── Hora e tooltip ───────────────────────────────────────────────────────────

const timeDisplay = computed(() => {
    const ts = props.message.created_at;
    if (!ts) { return '' }
    const d = new Date(ts);
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', hour12: false });
});

const timeTooltip = computed(() => {
    const ts = props.message.created_at;
    if (!ts) { return '' }
    return formatDateTime(ts);
});
</script>

<template>
    <div
        class="flex gap-2 px-4"
        :class="[isOutbound ? 'justify-end' : 'justify-start', isGrouped ? 'mt-0.5' : 'mt-3']"
    >
        <!-- Avatar do sender (apenas não-outbound e não agrupado) -->
        <div
            v-if="!isOutbound && !isGrouped"
            class="shrink-0 h-7 w-7 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-semibold"
            aria-hidden="true"
        >
            {{ senderName.slice(0, 2).toUpperCase() }}
        </div>
        <!-- Spacer quando agrupado -->
        <div v-else-if="!isOutbound && isGrouped" class="shrink-0 w-7" aria-hidden="true"></div>

        <!-- Bubble -->
        <div
            class="max-w-xs md:max-w-md lg:max-w-lg"
            :class="isOutbound ? 'items-end' : 'items-start'"
        >
            <!-- Nome do sender (apenas quando não agrupado) -->
            <p
                v-if="!isGrouped"
                class="text-[11px] font-medium mb-0.5 px-1"
                :class="isOutbound ? 'text-right text-foreground-muted' : 'text-left text-foreground-muted'"
            >
                {{ senderName }}
                <span v-if="isTemplate && templateName" class="ml-1 text-foreground-subtle">(template: {{ templateName }})</span>
            </p>

            <!-- Conteúdo do bubble -->
            <div
                class="rounded-2xl px-3 py-2 text-sm"
                :class="[
                    isOutbound
                        ? 'bg-primary-100 text-foreground rounded-br-sm'
                        : 'bg-surface-elevated text-foreground border border-border rounded-bl-sm',
                ]"
            >
                <!-- Indicador de template -->
                <div v-if="isTemplate" class="flex items-center gap-1 mb-1.5 text-xs text-foreground-muted">
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                    </svg>
                    <span>Template aprovado</span>
                </div>

                <!-- Texto da mensagem -->
                <p
                    v-if="message.body"
                    class="whitespace-pre-wrap break-words leading-relaxed"
                    :lang="'pt-BR'"
                >{{ message.body }}</p>

                <!-- Mídia anexada -->
                <div v-if="hasMedia" class="mt-2 space-y-2">
                    <div
                        v-for="media in mediaItems"
                        :key="media.id"
                    >
                        <!-- Aviso LGPD antes da visualização -->
                        <div
                            v-if="!isLgpdAccepted(media.id)"
                            class="rounded-lg border border-warning-300 bg-warning-50 p-3"
                            role="alert"
                        >
                            <p class="text-xs text-warning-800 font-medium mb-2">
                                {{ t('inbox.mensagem.preview_lgpd') }}
                            </p>
                            <button
                                type="button"
                                class="text-xs font-semibold text-warning-700 underline hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click.stop="acceptLgpd(media.id)"
                            >
                                {{ t('inbox.mensagem.lgpd_aceito') }}
                            </button>
                        </div>

                        <!-- Imagem thumbnail -->
                        <div v-else-if="isImage(media.mime_type)">
                            <button
                                type="button"
                                class="rounded-lg overflow-hidden focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                :aria-label="`Visualizar imagem: ${media.filename ?? 'imagem'}`"
                                @click.stop="openMedia(media)"
                            >
                                <img
                                    :src="media.thumbnail_url ?? media.url"
                                    :alt="media.filename ?? 'imagem'"
                                    class="max-w-48 max-h-48 object-cover rounded-lg"
                                    loading="lazy"
                                />
                            </button>
                        </div>

                        <!-- Áudio inline -->
                        <div v-else-if="isAudio(media.mime_type)">
                            <!-- eslint-disable-next-line vuejs-accessibility/media-has-caption -->
                            <audio
                                :src="media.url"
                                controls
                                class="w-full max-w-xs"
                                :aria-label="media.filename ?? 'áudio'"
                            ></audio>
                        </div>

                        <!-- Vídeo inline -->
                        <div v-else-if="isVideo(media.mime_type)">
                            <!-- eslint-disable-next-line vuejs-accessibility/media-has-caption -->
                            <video
                                :src="media.url"
                                controls
                                class="w-full max-w-xs rounded-lg"
                                :aria-label="media.filename ?? 'vídeo'"
                            ></video>
                        </div>

                        <!-- Documento / PDF — link -->
                        <div v-else>
                            <a
                                :href="media.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 text-xs text-primary-700 underline hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                </svg>
                                {{ media.filename ?? 'Documento' }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Hora + status (rodapé do bubble) -->
                <div
                    class="flex items-center gap-1 mt-1.5"
                    :class="isOutbound ? 'justify-end' : 'justify-start'"
                >
                    <time
                        :datetime="message.created_at"
                        class="text-[10px] text-foreground-muted"
                        :title="timeTooltip"
                    >
                        {{ timeDisplay }}
                    </time>

                    <!-- Ícone de status (apenas outbound) -->
                    <span
                        v-if="statusConfig"
                        class="text-[11px]"
                        :class="statusConfig.color"
                        :title="statusConfig.reason ? `${statusConfig.label}: ${statusConfig.reason}` : statusConfig.label"
                        :aria-label="statusConfig.label"
                    >
                        {{ statusConfig.icon }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal full-size para imagem -->
    <Teleport to="body">
        <div
            v-if="mediaModal.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
            role="dialog"
            aria-modal="true"
            :aria-label="mediaModal.filename"
            @click.self="closeMediaModal"
        >
            <button
                type="button"
                class="absolute right-4 top-4 rounded-full bg-white/20 p-2 text-white hover:bg-white/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                aria-label="Fechar"
                @click="closeMediaModal"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            <img
                v-if="isImage(mediaModal.type)"
                :src="mediaModal.url"
                :alt="mediaModal.filename"
                class="max-h-screen max-w-full rounded-lg object-contain p-4"
            />
            <div v-else class="text-white text-sm p-4">
                <a :href="mediaModal.url" target="_blank" rel="noopener noreferrer" class="underline">
                    Abrir {{ mediaModal.filename }}
                </a>
            </div>
        </div>
    </Teleport>
</template>
