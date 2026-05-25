<script setup>
import { ref, watch, onBeforeUnmount, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCanaisStore } from '@/stores/canais.js';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';

const props = defineProps({
    open: { type: Boolean, required: true },
});

const emit = defineEmits(['close', 'connected']);

const { t } = useI18n();
const canais = useCanaisStore();

const dialogRef = useTemplateRef('dialogRef');
const openRef = ref(false);
useShellFocusTrap(dialogRef, openRef);

const step = ref('form'); // 'form' | 'qr'
const name = ref('');
const qr = ref(null);
const channelId = ref(null);
const loading = ref(false);
const error = ref(null);
const connected = ref(false);

let pollInterval = null;

watch(() => props.open, (isOpen) => {
    openRef.value = isOpen;
    if (isOpen) {
        reset();
    } else {
        stopPolling();
    }
});

function reset() {
    step.value = 'form';
    name.value = '';
    qr.value = null;
    channelId.value = null;
    loading.value = false;
    error.value = null;
    connected.value = false;
}

function close() {
    stopPolling();
    emit('close');
}

function handleOverlayClick(event) {
    if (event.target === event.currentTarget) { close(); }
}

function handleKeydown(event) {
    if (event.key === 'Escape') { close(); }
}

async function generate() {
    if (name.value.trim().length < 2) {
        error.value = t('canais.evolution.name_required');
        return;
    }
    loading.value = true;
    error.value = null;
    try {
        const { channel, qr: qrPayload } = await canais.connectEvolution(name.value.trim());
        channelId.value = channel.id;
        qr.value = qrPayload?.base64 ?? null;
        step.value = 'qr';
        startPolling();
    } catch (err) {
        const status = err.response?.status;
        if (status === 409) {
            error.value = t('canais.evolution.already_active');
        } else if (status === 403) {
            error.value = t('canais.errors.no_permission');
        } else {
            error.value = t('common.error_generic');
        }
    } finally {
        loading.value = false;
    }
}

async function regenerate() {
    if (! channelId.value) { return; }
    try {
        const qrPayload = await canais.regenerateQr(channelId.value);
        qr.value = qrPayload?.base64 ?? qr.value;
    } catch {
        // mantém o QR atual
    }
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(async () => {
        if (! channelId.value) { return; }
        try {
            const { status } = await canais.fetchConnectionState(channelId.value);
            if (status === 'ativo') {
                connected.value = true;
                stopPolling();
                emit('connected', channelId.value);
                setTimeout(close, 1200);
            }
        } catch {
            // ignora falhas transitórias de polling
        }
    }, 4000);
}

function stopPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

onBeforeUnmount(stopPolling);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="evo-qr-title"
            @click="handleOverlayClick"
            @keydown="handleKeydown"
        >
            <div
                ref="dialogRef"
                class="w-full max-w-md rounded-xl border border-border bg-surface-elevated p-6 shadow-xl"
                tabindex="-1"
            >
                <h2 id="evo-qr-title" class="mb-1 text-lg font-semibold text-foreground">
                    {{ t('canais.evolution.titulo') }}
                </h2>
                <p class="mb-4 text-sm text-foreground-muted">
                    {{ t('canais.evolution.subtitulo') }}
                </p>

                <!-- ── Aviso de risco (FR-003) ────────────────────────────────── -->
                <div
                    class="mb-4 rounded-lg border border-warning-400 bg-warning-50 px-3 py-2.5 text-sm text-warning-800"
                    role="note"
                >
                    <strong>{{ t('canais.evolution.aviso_titulo') }}</strong>
                    {{ t('canais.evolution.aviso_texto') }}
                </div>

                <!-- ── Passo 1: nome ──────────────────────────────────────────── -->
                <div v-if="step === 'form'">
                    <label for="evo-name" class="block text-sm font-medium text-foreground">
                        {{ t('canais.evolution.nome_label') }}
                    </label>
                    <input
                        id="evo-name"
                        v-model="name"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :placeholder="t('canais.evolution.nome_placeholder')"
                        @keydown.enter="generate"
                    />
                </div>

                <!-- ── Passo 2: QR Code + polling ─────────────────────────────── -->
                <div v-else class="flex flex-col items-center gap-3">
                    <div
                        v-if="connected"
                        class="flex flex-col items-center gap-2 py-6 text-center"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-success-100 text-2xl text-success-700">✓</span>
                        <p class="text-sm font-semibold text-success-700">{{ t('canais.evolution.conectado') }}</p>
                    </div>
                    <template v-else>
                        <img
                            v-if="qr"
                            :src="qr"
                            :alt="t('canais.evolution.qr_alt')"
                            class="h-64 w-64 rounded-lg bg-white"
                        />
                        <p class="text-center text-sm text-foreground-muted" aria-live="polite">
                            {{ t('canais.evolution.instrucao') }}
                        </p>
                        <button
                            type="button"
                            class="text-xs font-medium text-primary-700 underline transition hover:text-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="regenerate"
                        >
                            {{ t('canais.evolution.regenerar') }}
                        </button>
                    </template>
                </div>

                <!-- ── Erro ───────────────────────────────────────────────────── -->
                <div
                    v-if="error"
                    role="alert"
                    aria-live="assertive"
                    class="mt-3 rounded-lg border border-danger-400 bg-danger-50 px-3 py-2 text-sm text-danger-700"
                >
                    {{ error }}
                </div>

                <!-- ── Ações ──────────────────────────────────────────────────── -->
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="close"
                    >
                        {{ step === 'qr' && !connected ? t('common.close') : t('common.cancel') }}
                    </button>
                    <button
                        v-if="step === 'form'"
                        type="button"
                        :disabled="loading"
                        class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="generate"
                    >
                        {{ loading ? t('common.loading') : t('canais.evolution.gerar_qr') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
