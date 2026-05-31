<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatRelative } from '@/composables/useI18nFormat.js';

const { t } = useI18n();

const props = defineProps({
    /** @type {Object} ConversationResource */
    conversation: {
        type: Object,
        required: true,
    },
    /** @type {boolean} se este item está selecionado */
    selected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click']);

// ─── Avatar ──────────────────────────────────────────────────────────────────

const patientName = computed(() => {
    const p = props.conversation.patient;
    if (!p) { return null; }
    return p.nome_completo ?? p.name ?? null;
});

const initials = computed(() => {
    if (!patientName.value) { return null; }
    const parts = patientName.value.trim().split(/\s+/);
    if (parts.length === 1) { return parts[0].slice(0, 2).toUpperCase(); }
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const avatarUrl = computed(() => {
    return props.conversation.patient?.avatar_url ?? null;
});

/** Identificador exibido quando o paciente é anônimo/desconhecido */
const displayName = computed(() => {
    if (patientName.value) { return patientName.value; }

    const threadId = props.conversation.external_thread_id;
    if (!threadId) { return t('inbox.contato_anonimo'); }

    // Mascarar telefone: +55 (11) 9****-7777
    if (/^\+/.test(threadId)) {
        return maskPhone(threadId);
    }
    return threadId;
});

function maskPhone(phone) {
    // E.164: +5511912345678 → +55 (11) 9****-5678
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 8) { return phone; }

    const country = digits.slice(0, 2); // 55
    const area = digits.slice(2, 4); // 11
    const mid = digits.slice(4, 5); // 9
    const last4 = digits.slice(-4); // 5678

    return `+${country} (${area}) ${mid}****-${last4}`;
}

// ─── Canal ───────────────────────────────────────────────────────────────────

const channelType = computed(() => {
    return props.conversation.channel?.type ?? props.conversation.channel_type ?? 'web';
});

const channelBadgeClass = computed(() => {
    const map = {
        whatsapp: 'bg-[#25D366] text-white',
        instagram: 'bg-gradient-to-br from-[#833ab4] via-[#fd1d1d] to-[#fcb045] text-white',
        web: 'bg-surface border border-border text-foreground-muted',
    };
    return map[channelType.value] ?? map.web;
});

function channelLabel(type) {
    return type === 'whatsapp' ? 'W' : type === 'instagram' ? 'I' : '⊙';
}

// ─── Janela 24h ──────────────────────────────────────────────────────────────

const window24h = computed(() => props.conversation.window_24h ?? null);

const windowBadge = computed(() => {
    const w = window24h.value;

    // Web não tem janela 24h
    if (channelType.value === 'web') { return null; }
    if (!w) { return null; }

    const seconds = w.seconds_remaining ?? 0;

    if (seconds <= 0) {
        return { color: 'gray', icon: '🔒', label: null };
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor(seconds / 60);

    if (seconds > 4 * 3600) {
        return { color: 'green', hours, label: `${hours}h` };
    }
    if (seconds > 1 * 3600) {
        return { color: 'yellow', hours, label: `${hours}h` };
    }
    return { color: 'red', minutes, label: `${minutes}min` };
});

const windowBadgeClass = computed(() => {
    if (!windowBadge.value) { return ''; }
    const map = {
        green: 'bg-success-100 text-success-700 border-success-300',
        yellow: 'bg-warning-100 text-warning-700 border-warning-300',
        red: 'bg-danger-100 text-danger-700 border-danger-300',
        gray: 'bg-surface text-foreground-muted border-border',
    };
    return map[windowBadge.value.color] ?? map.gray;
});

// ─── Status ───────────────────────────────────────────────────────────────────

const statusDotClass = computed(() => {
    const map = {
        aberta: 'bg-primary-500',
        pendente: 'bg-warning-500',
        resolvida: 'bg-foreground-subtle',
        reaberta: 'bg-primary-400',
    };
    return map[props.conversation.status] ?? 'bg-foreground-subtle';
});

// ─── Prioridade ──────────────────────────────────────────────────────────────

const priority = computed(() => props.conversation.priority ?? 'normal');

const priorityBadgeClass = computed(() => {
    const map = {
        alta: 'bg-danger-100 text-danger-700 border-danger-200',
        baixa: 'bg-surface text-foreground-muted border-border',
    };
    return map[priority.value] ?? '';
});

// ─── IA Pausada ───────────────────────────────────────────────────────────────

const aiPausedUntil = computed(() => {
    const val = props.conversation.ai_paused_until;
    if (!val) { return null; }
    return new Date(val);
});

const isAiPaused = computed(() => {
    if (!aiPausedUntil.value) { return false; }
    return aiPausedUntil.value > new Date();
});

// ─── Cooldown anti-abuso (Fase 18 — Polish T205, FR-008b) ────────────────────
const cooldownUntil = computed(() => {
    const val = props.conversation.cooldown_until;
    if (!val) { return null; }
    return new Date(val);
});

const isOnCooldown = computed(() => {
    if (props.conversation.is_on_cooldown === true) { return true; }
    if (!cooldownUntil.value) { return false; }
    return cooldownUntil.value > new Date();
});

const cooldownMinutesRemaining = computed(() => {
    if (!cooldownUntil.value) { return 0; }
    const diffMs = cooldownUntil.value.getTime() - Date.now();
    return Math.max(0, Math.ceil(diffMs / 60000));
});

// ─── Preview + hora ───────────────────────────────────────────────────────────

const lastMessagePreview = computed(() => {
    const preview = props.conversation.last_message_preview ?? '';
    if (preview.length <= 80) { return preview; }
    return preview.slice(0, 80) + '…';
});

const relativeTime = computed(() => {
    const ts = props.conversation.last_message_at;
    if (!ts) { return '' }
    return formatRelative(ts);
});

const unreadCount = computed(() => props.conversation.unread_count ?? 0);
</script>

<template>
    <button
        type="button"
        class="w-full text-left px-3 py-3 border-b border-border transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
        :class="[
            selected
                ? 'bg-primary-50 border-l-4 border-l-primary-500'
                : 'border-l-4 border-l-transparent hover:bg-surface-elevated',
        ]"
        :aria-current="selected ? 'true' : undefined"
        :aria-label="displayName"
        @click="emit('click')"
    >
        <div class="flex items-start gap-3">
            <!-- Avatar + channel badge -->
            <div class="relative shrink-0">
                <!-- Avatar circular -->
                <div
                    class="h-10 w-10 rounded-full overflow-hidden flex items-center justify-center bg-primary-100 text-primary-700 text-sm font-semibold"
                    aria-hidden="true"
                >
                    <img
                        v-if="avatarUrl"
                        :src="avatarUrl"
                        :alt="displayName"
                        class="h-full w-full object-cover"
                    />
                    <span v-else-if="initials">{{ initials }}</span>
                    <span v-else class="text-foreground-muted text-base">?</span>
                </div>

                <!-- Channel badge (canto inferior-direito do avatar) -->
                <span
                    class="absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full flex items-center justify-center text-[9px] font-bold border border-surface-elevated"
                    :class="channelBadgeClass"
                    :title="t(`inbox.canal.${channelType}`)"
                    aria-hidden="true"
                >
                    {{ channelLabel(channelType) }}
                </span>
            </div>

            <!-- Conteúdo principal -->
            <div class="min-w-0 flex-1">
                <!-- Linha 1: Nome + hora + status dot -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <!-- Status dot -->
                        <span
                            class="inline-block h-2 w-2 rounded-full shrink-0"
                            :class="statusDotClass"
                            :title="t(`inbox.status.${conversation.status}`)"
                            aria-hidden="true"
                        ></span>

                        <!-- Nome -->
                        <span
                            class="truncate text-sm font-semibold text-foreground"
                            :class="{ 'text-foreground-muted italic': !patientName }"
                        >
                            {{ displayName }}
                        </span>
                    </div>

                    <!-- Hora relativa -->
                    <span
                        class="shrink-0 text-xs text-foreground-muted whitespace-nowrap"
                        aria-label="Última mensagem"
                    >
                        {{ relativeTime }}
                    </span>
                </div>

                <!-- Linha 2: Preview da mensagem + badges -->
                <div class="mt-0.5 flex items-center justify-between gap-2">
                    <p class="truncate text-xs text-foreground-muted flex-1">
                        {{ lastMessagePreview || '—' }}
                    </p>

                    <div class="flex items-center gap-1 shrink-0">
                        <!-- Badge não lidas -->
                        <span
                            v-if="unreadCount > 0"
                            class="inline-flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-bold text-white"
                            :aria-label="`${unreadCount} mensagens não lidas`"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </div>
                </div>

                <!-- Linha 3: badges secundários (janela, prioridade, IA pausada, cooldown) -->
                <div
                    v-if="windowBadge || (priority !== 'normal') || isAiPaused || isOnCooldown"
                    class="mt-1 flex flex-wrap items-center gap-1"
                >
                    <!-- Window 24h badge -->
                    <span
                        v-if="windowBadge"
                        class="inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-[10px] font-medium"
                        :class="windowBadgeClass"
                        aria-label="Janela de 24h"
                    >
                        <template v-if="windowBadge.color === 'gray'">🔒</template>
                        <template v-else>⏱</template>
                        <span v-if="windowBadge.label">{{ windowBadge.label }}</span>
                    </span>

                    <!-- Prioridade (apenas alta/baixa) -->
                    <span
                        v-if="priority !== 'normal'"
                        class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[10px] font-medium"
                        :class="priorityBadgeClass"
                        :aria-label="`Prioridade ${priority}`"
                    >
                        {{ priority }}
                    </span>

                    <!-- IA pausada -->
                    <span
                        v-if="isAiPaused"
                        class="inline-flex items-center gap-0.5 rounded-full border border-warning-300 bg-warning-50 px-1.5 py-0.5 text-[10px] font-medium text-warning-700"
                        :title="`IA pausada até ${aiPausedUntil?.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`"
                        aria-label="IA pausada"
                    >
                        ⏸ IA
                    </span>

                    <!-- Cooldown anti-abuso (Fase 18 — Polish T205) -->
                    <span
                        v-if="isOnCooldown"
                        class="inline-flex items-center gap-0.5 rounded-full border border-danger-300 bg-danger-50 px-1.5 py-0.5 text-[10px] font-medium text-danger-700"
                        :title="`Em cooldown anti-abuso até ${cooldownUntil?.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`"
                        aria-label="Em cooldown anti-abuso"
                    >
                        🚫 cooldown {{ cooldownMinutesRemaining }}min
                    </span>
                </div>
            </div>
        </div>
    </button>
</template>
