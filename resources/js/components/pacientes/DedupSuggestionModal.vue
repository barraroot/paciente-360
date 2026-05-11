<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    candidatos: {
        type: Array,
        default: () => [],
    },
    payloadOriginal: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'merge', 'criar-paralelo', 'abrir-existente']);

const { t } = useI18n();
const router = useRouter();

/**
 * Verifica se o CPF do payload bate com o de algum candidato.
 * Se sim, esconde o botão "criar paralelo" pois o backend rejeitará.
 */
const cpfConflita = computed(() => {
    if (!props.payloadOriginal?.cpf) { return false; }
    const cpfOriginalDigits = props.payloadOriginal.cpf.replace(/\D/g, '');
    return props.candidatos.some((c) => {
        const cDigits = (c.cpf ?? '').replace(/\D/g, '');
        return cDigits.length === 11 && cDigits === cpfOriginalDigits;
    });
});

function handleOverlayClick(event) {
    if (event.target === event.currentTarget) {
        emit('close');
    }
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

function handleMerge(candidatoId) {
    emit('merge', candidatoId);
    router.push(`/panel/pacientes/mesclagem?alvo=${candidatoId}`);
}

function handleAbrirExistente(candidatoId) {
    emit('abrir-existente', candidatoId);
    router.push(`/panel/pacientes/${candidatoId}`);
}

function statusLabel(status) {
    const map = {
        lead: t('paciente.status.lead'),
        ativo: t('paciente.status.ativo'),
        inativo: t('paciente.status.inativo'),
        bloqueado: t('paciente.status.bloqueado'),
    };
    return map[status] ?? status;
}

function statusClass(status) {
    const map = {
        lead: 'bg-warning-50 text-warning-700',
        ativo: 'bg-success-50 text-success-700',
        inativo: 'bg-surface text-foreground-muted',
        bloqueado: 'bg-danger-50 text-danger-700',
    };
    return map[status] ?? 'bg-surface text-foreground-muted';
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="dedup-modal-title"
            @click="handleOverlayClick"
            @keydown="handleKeydown"
        >
            <div class="w-full max-w-lg rounded-xl border border-border bg-surface-elevated shadow-xl">
                <!-- Cabeçalho -->
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h2 id="dedup-modal-title" class="text-lg font-semibold text-foreground">
                        {{ t('mesclagem.dedup_modal_title') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded p-1 text-foreground-muted transition hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :aria-label="t('common.cancel')"
                        @click="emit('close')"
                    >
                        ✕
                    </button>
                </div>

                <!-- Candidatos -->
                <div class="divide-y divide-border overflow-y-auto max-h-72 px-6 py-2">
                    <div
                        v-for="candidato in candidatos"
                        :key="candidato.id"
                        class="py-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-sm text-foreground truncate">{{ candidato.nome }}</p>
                                <p class="text-xs text-foreground-muted mt-0.5">
                                    CPF: {{ candidato.cpf ?? '—' }}
                                </p>
                                <p class="text-xs text-foreground-muted">
                                    Tel: {{ candidato.telefone_primario ?? '—' }}
                                </p>
                            </div>
                            <span
                                class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusClass(candidato.status)"
                            >
                                {{ statusLabel(candidato.status) }}
                            </span>
                        </div>

                        <!-- Ações por candidato -->
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="handleMerge(candidato.id)"
                            >
                                {{ t('mesclagem.actions.mesclar') }}
                            </button>

                            <button
                                v-if="!cpfConflita"
                                type="button"
                                class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="emit('criar-paralelo')"
                            >
                                {{ t('mesclagem.actions.criar_paralelo') }}
                            </button>
                            <p
                                v-else
                                class="self-center text-xs text-foreground-muted italic"
                            >
                                CPF duplicado — use Mesclar ou modifique o CPF.
                            </p>

                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium text-primary-600 underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="handleAbrirExistente(candidato.id)"
                            >
                                {{ t('mesclagem.actions.abrir_existente') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Rodapé -->
                <div class="flex justify-end border-t border-border px-6 py-4">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="emit('close')"
                    >
                        {{ t('common.cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
