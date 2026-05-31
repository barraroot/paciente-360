<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePrivacyStore } from '@/stores/privacyStore.js';

/**
 * **T209 (Fase 18 — Polish, FR-055a/b/c, Q-clarify-2=B)** — toggle de consent
 * `Transcricao` por paciente.
 *
 * Estados:
 *  - Vazio (nunca concedido): off + texto curto + link "saiba mais"
 *  - Concedido: on + data + revogar (com confirmação informando audios_to_purge)
 *  - Revogado: off + data de revogação + conceder novamente
 *
 * Confirmação a11y (Teleport + role=alertdialog + Esc) — proibido confirm()
 * nativo (Fase 6 — pattern).
 */

const props = defineProps({
    patientId: { type: [String, Number], required: true },
});

const emit = defineEmits(['changed']);

const privacy = usePrivacyStore();

const consent = ref(null);
const loading = ref(false);
const showInfoModal = ref(false);
const confirmRevoke = ref(false);
const confirmRevokeContext = ref(null); // { audios_to_purge }
const saving = ref(false);

const granted = computed(() => Boolean(consent.value?.granted));
const revokedAt = computed(() => consent.value?.revoked_at ?? null);
const grantedAt = computed(() => consent.value?.granted_at ?? null);
const description = computed(() => consent.value?.description ?? '');
const defaultDays = computed(() => consent.value?.default_retention_days ?? 90);
const extendedDays = computed(() => consent.value?.extended_retention_days ?? 365);

onMounted(load);

async function load() {
    loading.value = true;
    try {
        consent.value = await privacy.fetchTranscricaoConsent(props.patientId);
    } finally {
        loading.value = false;
    }
}

async function grant() {
    saving.value = true;
    try {
        consent.value = await privacy.grantTranscricaoConsent(props.patientId);
        emit('changed', consent.value);
    } finally {
        saving.value = false;
    }
}

async function askRevoke() {
    // Busca state pra mostrar quantos áudios serão apagados ANTES de revogar.
    confirmRevokeContext.value = await privacy.fetchTranscricaoConsent(props.patientId);
    confirmRevoke.value = true;
}

async function doRevoke() {
    saving.value = true;
    try {
        consent.value = await privacy.revokeTranscricaoConsent(props.patientId);
        emit('changed', consent.value);
    } finally {
        saving.value = false;
        confirmRevoke.value = false;
        confirmRevokeContext.value = null;
    }
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return iso;
    }
}
</script>

<template>
    <div class="rounded-lg border border-border bg-white p-4">
        <div v-if="loading" class="text-sm text-foreground-muted">Carregando consentimento…</div>

        <div v-else class="space-y-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-foreground">
                            Retenção prolongada de áudio
                        </h3>
                        <span
                            v-if="granted"
                            class="inline-flex items-center rounded-full bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700"
                        >
                            Autorizado
                        </span>
                        <span
                            v-else-if="revokedAt"
                            class="inline-flex items-center rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-foreground-muted"
                        >
                            Revogado
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-foreground-muted"
                        >
                            Padrão (sem autorização extra)
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-foreground-muted">
                        Sem essa autorização, áudios são apagados após
                        <strong>{{ defaultDays }} dias</strong>; com ela, podem ser mantidos por até
                        <strong>{{ extendedDays }} dias</strong> para auditoria interna.
                        <button
                            type="button"
                            class="ml-1 font-medium text-primary-600 hover:underline"
                            @click="showInfoModal = true"
                        >
                            Saiba mais
                        </button>
                    </p>
                    <p
                        v-if="granted && grantedAt"
                        class="mt-1 text-xs text-success-700"
                        aria-live="polite"
                    >
                        Concedido em {{ formatDate(grantedAt) }}
                    </p>
                    <p
                        v-else-if="revokedAt"
                        class="mt-1 text-xs text-foreground-muted"
                        aria-live="polite"
                    >
                        Revogado em {{ formatDate(revokedAt) }}
                    </p>
                </div>

                <label class="relative inline-flex cursor-pointer items-center pt-1">
                    <input
                        :checked="granted"
                        :disabled="saving"
                        type="checkbox"
                        class="peer sr-only"
                        :aria-label="
                            granted
                                ? 'Revogar autorização de retenção prolongada de áudio'
                                : 'Conceder autorização de retenção prolongada de áudio'
                        "
                        @change.prevent="granted ? askRevoke() : grant()"
                    />
                    <span
                        class="h-6 w-11 rounded-full bg-surface-muted transition peer-checked:bg-primary-600 peer-disabled:opacity-50"
                    ></span>
                    <span
                        class="absolute left-0.5 top-1.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5"
                    ></span>
                </label>
            </div>
        </div>

        <!-- Modal "Saiba mais" -->
        <Teleport to="body">
            <div
                v-if="showInfoModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                role="dialog"
                aria-modal="true"
                aria-labelledby="transcricao-info-title"
                @click.self="showInfoModal = false"
                @keydown.esc="showInfoModal = false"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h2 id="transcricao-info-title" class="text-lg font-semibold text-foreground">
                        Retenção prolongada de áudio
                    </h2>
                    <p class="mt-3 text-sm text-foreground">{{ description }}</p>
                    <p class="mt-3 text-xs text-foreground-muted">
                        Em qualquer momento o paciente pode revogar essa autorização — áudios além
                        do prazo padrão serão apagados imediatamente.
                    </p>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            @click="showInfoModal = false"
                        >
                            Entendi
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Confirmação de revogação -->
        <Teleport to="body">
            <div
                v-if="confirmRevoke"
                class="fixed inset-0 z-[55] flex items-center justify-center bg-black/40"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="transcricao-revoke-title"
                @click.self="confirmRevoke = false"
                @keydown.esc="confirmRevoke = false"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h2
                        id="transcricao-revoke-title"
                        class="text-lg font-semibold text-foreground"
                    >
                        Revogar autorização?
                    </h2>
                    <p class="mt-2 text-sm text-foreground-muted">
                        Ao revogar, áudios com mais de
                        <strong>{{ defaultDays }} dias</strong> serão apagados permanentemente. A
                        transcrição em texto continua disponível.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm text-foreground-muted hover:bg-surface-muted"
                            @click="confirmRevoke = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="saving"
                            class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-medium text-white hover:bg-danger-700 disabled:opacity-50"
                            @click="doRevoke"
                        >
                            {{ saving ? 'Revogando…' : 'Revogar e apagar áudios' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
