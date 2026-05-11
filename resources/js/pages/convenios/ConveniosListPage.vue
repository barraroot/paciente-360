<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import { useAuthStore } from '@/stores/auth.js';

/**
 * T248 — Listagem CRUD de convênios (Admin Clínica).
 */

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

const convenios = ref([]);
const loading = ref(false);
const erro = ref(null);
const filtroAtivo = ref(true);
const deletandoId = ref(null);
const erroDelete = ref(null);

async function carregarConvenios() {
    loading.value = true;
    erro.value = null;
    try {
        const { data } = await api.get('/convenios', {
            params: { is_active: filtroAtivo.value },
        });
        convenios.value = data.data ?? data ?? [];
    } catch {
        erro.value = 'Erro ao carregar convênios.';
    } finally {
        loading.value = false;
    }
}

async function excluirConvenio(id) {
    deletandoId.value = id;
    erroDelete.value = null;
    try {
        await api.delete(`/convenios/${id}`);
        await carregarConvenios();
    } catch (err) {
        const status = err.response?.status;
        if (status === 409) {
            erroDelete.value = 'Convênio está em uso por pacientes e não pode ser excluído.';
        } else if (status === 403) {
            erroDelete.value = 'Sem permissão para excluir convênios.';
        } else {
            erroDelete.value = 'Erro ao excluir convênio.';
        }
    } finally {
        deletandoId.value = null;
    }
}

async function toggleAtivo(convenio) {
    try {
        await api.patch(`/convenios/${convenio.id}`, { is_active: !convenio.is_active });
        await carregarConvenios();
    } catch {
        erroDelete.value = 'Erro ao alterar status do convênio.';
    }
}

onMounted(() => carregarConvenios());
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-xl font-bold text-foreground">Convênios</h1>
                <button
                    v-if="auth.hasRole('admin-clinica')"
                    type="button"
                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="router.push('/panel/convenios/novo')"
                >
                    + Novo convênio
                </button>
            </div>

            <!-- Filtro ativo/inativo -->
            <div class="mb-4 flex gap-2">
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-medium transition"
                    :class="filtroAtivo ? 'border-primary-700 bg-primary-50 text-primary-700' : 'border-border text-foreground-muted hover:bg-surface-elevated'"
                    @click="filtroAtivo = true; carregarConvenios()"
                >
                    Ativos
                </button>
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-medium transition"
                    :class="!filtroAtivo ? 'border-primary-700 bg-primary-50 text-primary-700' : 'border-border text-foreground-muted hover:bg-surface-elevated'"
                    @click="filtroAtivo = false; carregarConvenios()"
                >
                    Inativos
                </button>
            </div>

            <!-- Erro delete -->
            <div
                v-if="erroDelete"
                role="alert"
                class="mb-4 rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-600"
            >
                {{ erroDelete }}
                <button type="button" class="ml-2 underline" @click="erroDelete = null">Fechar</button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20" aria-live="polite">
                <span class="text-sm text-foreground-muted">Carregando…</span>
            </div>

            <!-- Erro -->
            <div v-else-if="erro" class="flex items-center justify-center py-12">
                <p class="text-sm text-danger-600">{{ erro }}</p>
            </div>

            <!-- Tabela -->
            <div v-else-if="convenios.length > 0" class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-surface-elevated">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-foreground-muted">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-foreground-muted">Código ANS</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-foreground-muted">Status</th>
                            <th v-if="auth.hasRole('admin-clinica')" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-foreground-muted">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="convenio in convenios" :key="convenio.id" class="hover:bg-surface-elevated">
                            <td class="px-4 py-3 font-medium text-foreground">{{ convenio.nome }}</td>
                            <td class="px-4 py-3 text-foreground-muted">{{ convenio.codigo_ans ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="convenio.is_active ? 'bg-success-50 text-success-700' : 'bg-surface text-foreground-muted'"
                                >
                                    {{ convenio.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td v-if="auth.hasRole('admin-clinica')" class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="text-xs text-primary-700 underline"
                                        @click="router.push(`/panel/convenios/${convenio.id}/editar`)"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs text-foreground-muted underline"
                                        @click="toggleAtivo(convenio)"
                                    >
                                        {{ convenio.is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs text-danger-600 underline"
                                        :disabled="deletandoId === convenio.id"
                                        @click="excluirConvenio(convenio.id)"
                                    >
                                        {{ deletandoId === convenio.id ? 'Excluindo…' : 'Excluir' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty -->
            <div v-else class="flex items-center justify-center py-16">
                <p class="text-sm text-foreground-muted">
                    Nenhum convênio {{ filtroAtivo ? 'ativo' : 'inativo' }} cadastrado.
                </p>
            </div>
        </div>
    </main>
</template>
