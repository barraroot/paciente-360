<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useIaStore } from '@/stores/ia.js';

const store = useIaStore();

const filters = reactive({ status: '', action: '' });
const selected = ref(null);

const STATUSES = ['success', 'escalated', 'failed'];

function statusClass(status) {
    return (
        {
            success: 'bg-green-100 text-green-700',
            escalated: 'bg-amber-100 text-amber-700',
            failed: 'bg-red-100 text-red-700',
        }[status] ?? 'bg-gray-100 text-gray-600'
    );
}

async function load() {
    const params = {};
    if (filters.status) params.status = filters.status;
    if (filters.action) params.action = filters.action;
    await store.fetchExecutionLogs(params);
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR');
}

onMounted(load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Logs de Execução da IA</h1>
            <p class="text-sm text-gray-500">
                Auditoria das decisões da IA (conteúdo pseudonimizado).
            </p>
        </div>

        <div class="mb-4 flex items-center gap-3">
            <select
                v-model="filters.status"
                aria-label="Filtrar por status"
                class="rounded-lg border-gray-300 text-sm"
                @change="load"
            >
                <option value="">Todos os status</option>
                <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>

        <div v-if="store.loading" class="py-12 text-center text-gray-500">Carregando…</div>

        <div v-else-if="store.error" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
            {{ store.error }}
        </div>

        <div
            v-else-if="store.executionLogs.length === 0"
            class="rounded-lg border border-dashed border-gray-300 py-12 text-center text-gray-500"
        >
            Nenhum log de execução ainda.
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Persona</th>
                        <th class="px-4 py-3">Intenção</th>
                        <th class="px-4 py-3">Confiança</th>
                        <th class="px-4 py-3">Ação</th>
                        <th class="px-4 py-3">Latência</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="log in store.executionLogs"
                        :key="log.id"
                        class="text-sm text-gray-700"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">{{ fmtDate(log.created_at) }}</td>
                        <td class="px-4 py-3">{{ log.persona?.name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ log.classified_intent ?? '—' }}</td>
                        <td class="px-4 py-3">
                            {{
                                log.confidence_score != null
                                    ? (log.confidence_score * 100).toFixed(0) + '%'
                                    : '—'
                            }}
                        </td>
                        <td class="px-4 py-3">{{ log.action ?? '—' }}</td>
                        <td class="px-4 py-3">
                            {{ log.latency_ms != null ? log.latency_ms + ' ms' : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="statusClass(log.status)"
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            >
                                {{ log.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="text-indigo-600 hover:underline"
                                @click="selected = log"
                            >
                                Detalhes
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Drawer simples de detalhe (a11y) -->
        <Teleport to="body">
            <div
                v-if="selected"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                role="dialog"
                aria-modal="true"
                aria-labelledby="log-detail-title"
                @click.self="selected = null"
                @keydown.esc="selected = null"
            >
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 id="log-detail-title" class="text-lg font-semibold text-gray-900">
                            Detalhe do log
                        </h2>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600"
                            @click="selected = null"
                            aria-label="Fechar"
                        >
                            ✕
                        </button>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-gray-500">Correlation ID</dt>
                            <dd class="font-mono text-xs text-gray-800">
                                {{ selected.correlation_id }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Intenção</dt>
                            <dd class="text-gray-800">{{ selected.classified_intent ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Ação / Status</dt>
                            <dd class="text-gray-800">
                                {{ selected.action ?? '—' }} / {{ selected.status }}
                            </dd>
                        </div>
                        <div v-if="selected.error_message">
                            <dt class="text-gray-500">Erro</dt>
                            <dd class="text-red-700">{{ selected.error_message }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </Teleport>
    </div>
</template>
