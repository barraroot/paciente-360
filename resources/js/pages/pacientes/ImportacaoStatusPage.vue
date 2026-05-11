<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const importacaoId = computed(() => route.params.id);

const importacao = ref(null);
const loading = ref(true);
const error = ref(null);

const STATUS_FINAIS = ['completed', 'partial_failure', 'failed'];

let pollInterval = null;

async function fetchStatus() {
    try {
        const { data } = await api.get(`/pacientes/importacao/${importacaoId.value}`);
        importacao.value = data.data ?? data;

        if (STATUS_FINAIS.includes(importacao.value?.status)) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    } catch (err) {
        error.value = t('common.error_generic');
        clearInterval(pollInterval);
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await fetchStatus();

    if (importacao.value && !STATUS_FINAIS.includes(importacao.value.status)) {
        pollInterval = setInterval(fetchStatus, 3000);
    }
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

// ─── Computed ─────────────────────────────────────────────────────────────────

const statusLabel = computed(() => {
    const map = {
        pending: t('import.status_pending'),
        processing: t('import.status_processing'),
        completed: t('import.status_completed'),
        partial_failure: t('import.status_partial_failure'),
        failed: t('import.status_failed'),
        retrying: t('import.status_retrying'),
    };
    return map[importacao.value?.status] ?? importacao.value?.status ?? '';
});

const statusClass = computed(() => {
    const map = {
        pending: 'bg-warning-50 text-warning-700',
        processing: 'bg-primary-50 text-primary-700',
        completed: 'bg-success-50 text-success-700',
        partial_failure: 'bg-warning-50 text-warning-700',
        failed: 'bg-danger-50 text-danger-700',
        retrying: 'bg-warning-50 text-warning-700',
    };
    return map[importacao.value?.status] ?? 'bg-surface text-foreground-muted';
});

const isRunning = computed(() =>
    ['pending', 'processing', 'retrying'].includes(importacao.value?.status),
);

const progressPercent = computed(() => importacao.value?.progress_percent ?? 0);

const contadores = computed(() => importacao.value?.contadores ?? null);

const relatorio = computed(() => importacao.value?.relatorio ?? []);

const relatorioErros = computed(() =>
    relatorio.value.filter((r) => r.resultado === 'erro'),
);

const relatorioSucesso = computed(() =>
    relatorio.value.filter((r) => r.resultado !== 'erro'),
);
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-4xl">

            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-foreground">
                    {{ t('import.status_title') }}
                </h1>
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="router.push('/panel/pacientes')"
                >
                    {{ t('import.voltar_pacientes') }}
                </button>
            </div>

            <!-- Loading inicial -->
            <div
                v-if="loading"
                class="flex items-center justify-center py-16"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
            </div>

            <!-- Erro -->
            <div
                v-else-if="error"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
            >
                {{ error }}
            </div>

            <template v-else-if="importacao">

                <!-- ── Status card ──────────────────────────────────────────── -->
                <div class="mb-6 rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">

                    <div class="mb-4 flex items-center gap-3">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold"
                            :class="statusClass"
                        >
                            {{ statusLabel }}
                        </span>
                        <span class="text-sm text-foreground-muted">
                            {{ importacao.arquivo_nome_original }}
                        </span>
                    </div>

                    <!-- Barra de progresso -->
                    <div class="mb-4">
                        <div class="mb-1 flex items-center justify-between text-xs text-foreground-muted">
                            <span>{{ t('import.progress_label') }}</span>
                            <span>{{ progressPercent }}%</span>
                        </div>
                        <div class="h-2.5 w-full rounded-full bg-surface">
                            <div
                                class="h-2.5 rounded-full bg-primary-600 transition-all duration-500"
                                :style="{ width: `${progressPercent}%` }"
                                role="progressbar"
                                :aria-valuenow="progressPercent"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            />
                        </div>
                    </div>

                    <!-- Indicador de polling -->
                    <p v-if="isRunning" class="text-xs text-foreground-muted" aria-live="polite">
                        {{ t('import.aguardando') }}
                    </p>

                    <!-- Contadores finais -->
                    <div v-if="contadores" class="mt-4 grid grid-cols-3 gap-4">
                        <div class="rounded-lg bg-success-50 p-3 text-center">
                            <p class="text-2xl font-bold text-success-700">{{ contadores.criados ?? 0 }}</p>
                            <p class="text-xs text-success-700">{{ t('import.contagem_criados') }}</p>
                        </div>
                        <div class="rounded-lg bg-primary-50 p-3 text-center">
                            <p class="text-2xl font-bold text-primary-700">{{ contadores.atualizados ?? 0 }}</p>
                            <p class="text-xs text-primary-700">{{ t('import.contagem_atualizados') }}</p>
                        </div>
                        <div class="rounded-lg bg-danger-50 p-3 text-center">
                            <p class="text-2xl font-bold text-danger-700">{{ contadores.erros ?? 0 }}</p>
                            <p class="text-xs text-danger-700">{{ t('import.contagem_erros') }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Relatório ────────────────────────────────────────────── -->
                <template v-if="relatorio.length > 0">

                    <!-- Erros -->
                    <div v-if="relatorioErros.length > 0" class="mb-6">
                        <h2 class="mb-3 text-base font-semibold text-danger-700">
                            {{ t('import.relatorio_erros_titulo') }} ({{ relatorioErros.length }})
                        </h2>
                        <div class="overflow-x-auto rounded-xl border border-danger-200 bg-surface-elevated shadow-[var(--shadow-card)]">
                            <table class="w-full text-sm">
                                <thead class="border-b border-danger-200 bg-danger-50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-danger-700">
                                            {{ t('import.col_linha') }}
                                        </th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-danger-700">
                                            {{ t('import.col_motivo') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-danger-100">
                                    <tr v-for="item in relatorioErros" :key="item.linha">
                                        <td class="px-4 py-2 text-foreground-muted">{{ item.linha }}</td>
                                        <td class="px-4 py-2 text-danger-600">{{ item.motivo }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Importados com sucesso (colapsável) -->
                    <div v-if="relatorioSucesso.length > 0">
                        <h2 class="mb-3 text-base font-semibold text-success-700">
                            {{ t('import.relatorio_sucesso_titulo') }} ({{ relatorioSucesso.length }})
                        </h2>
                        <div class="overflow-x-auto rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)]">
                            <table class="w-full text-sm">
                                <thead class="border-b border-border bg-surface">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                            {{ t('import.col_linha') }}
                                        </th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                            {{ t('import.col_resultado') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="item in relatorioSucesso" :key="item.linha">
                                        <td class="px-4 py-2 text-foreground-muted">{{ item.linha }}</td>
                                        <td class="px-4 py-2">
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="item.resultado === 'criado'
                                                    ? 'bg-success-50 text-success-700'
                                                    : 'bg-primary-50 text-primary-700'"
                                            >
                                                {{ item.resultado === 'criado' ? t('import.resultado_criado') : t('import.resultado_atualizado') }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </template>
        </div>
    </main>
</template>
