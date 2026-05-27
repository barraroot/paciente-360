<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';
import api from '@/lib/api.js';

const { t } = useI18n();

const colunas = ref([]);
const loading = ref(true);
const salvando = ref({});
const erros = ref({});
const mensagemSucesso = ref('');

async function carregarColunas() {
    loading.value = true;

    try {
        const { data } = await api.get('/funil/colunas');
        colunas.value = [...(data.data ?? [])].sort((a, b) => a.posicao - b.posicao);
    } finally {
        loading.value = false;
    }
}

onMounted(carregarColunas);

async function salvarColuna(coluna) {
    salvando.value[coluna.id] = true;
    erros.value[coluna.id] = null;

    try {
        await api.patch(`/funil/colunas/${coluna.id}`, {
            nome: coluna.nome,
            motivo_obrigatorio: coluna.motivo_obrigatorio,
            cor: coluna.cor,
        });

        mensagemSucesso.value = 'Coluna salva com sucesso.';
        setTimeout(() => {
            mensagemSucesso.value = '';
        }, 3000);
    } catch (e) {
        erros.value[coluna.id] = e?.response?.data?.message ?? 'Erro ao salvar. Tente novamente.';
    } finally {
        salvando.value[coluna.id] = false;
    }
}

/**
 * Ao reordenar via drag-and-drop, atualiza a `posicao` de cada coluna
 * na ordem local e salva cada uma via PATCH.
 */
async function onReorder() {
    const atualizacoes = colunas.value.map((col, idx) => ({
        ...col,
        posicao: idx + 1,
    }));

    colunas.value = atualizacoes;

    // Salva as novas posições em paralelo
    await Promise.allSettled(
        atualizacoes.map((col) =>
            api.patch(`/funil/colunas/${col.id}`, { posicao: col.posicao }).catch(() => null),
        ),
    );
}
</script>

<template>
    <div class="p-6 max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-foreground">
                {{ t('funil.config_title') }}
            </h1>
            <a href="/panel/pacientes/funil" class="text-sm text-primary-600 hover:underline">
                ← Voltar ao Kanban
            </a>
        </div>

        <p
            v-if="mensagemSucesso"
            class="mb-4 rounded-lg bg-success-50 border border-success-200 px-4 py-2 text-success-700 text-sm"
            role="status"
        >
            {{ mensagemSucesso }}
        </p>

        <div v-if="loading" class="py-10 text-center text-foreground-muted">Carregando…</div>

        <div v-else>
            <p class="text-sm text-foreground-muted mb-4">
                Arraste as linhas para reordenar as colunas do Kanban.
            </p>

            <draggable
                v-model="colunas"
                item-key="id"
                handle=".drag-handle"
                class="space-y-3"
                @end="onReorder"
            >
                <template #item="{ element: coluna }">
                    <div
                        class="flex items-center gap-4 rounded-xl border border-border bg-surface px-4 py-3 shadow-sm"
                    >
                        <!-- Handle -->
                        <button
                            type="button"
                            class="drag-handle cursor-grab active:cursor-grabbing text-foreground-muted hover:text-foreground"
                            title="Arrastar para reordenar"
                            aria-label="Reordenar"
                        >
                            ⠿
                        </button>

                        <!-- Cor -->
                        <input
                            v-model="coluna.cor"
                            type="color"
                            class="w-8 h-8 rounded cursor-pointer border border-border"
                            :title="`Cor da coluna ${coluna.nome}`"
                        />

                        <!-- Nome -->
                        <input
                            v-model="coluna.nome"
                            type="text"
                            maxlength="80"
                            class="flex-1 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary-500"
                            :placeholder="`Nome da coluna`"
                            :disabled="false"
                        />

                        <!-- Slug (read-only) -->
                        <span
                            class="text-xs text-foreground-muted font-mono px-2 py-1 bg-zinc-100 dark:bg-zinc-800 rounded"
                        >
                            {{ coluna.slug }}
                        </span>

                        <!-- Motivo obrigatório -->
                        <label
                            class="flex items-center gap-2 text-sm text-foreground whitespace-nowrap"
                        >
                            <input
                                v-model="coluna.motivo_obrigatorio"
                                type="checkbox"
                                class="rounded border-zinc-300 text-primary-600 focus:ring-primary-500"
                            />
                            Motivo obrig.
                        </label>

                        <!-- Salvar -->
                        <button
                            type="button"
                            class="rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-800 disabled:opacity-50"
                            :disabled="salvando[coluna.id]"
                            @click="salvarColuna(coluna)"
                        >
                            {{ salvando[coluna.id] ? '…' : 'Salvar' }}
                        </button>
                    </div>

                    <p
                        v-if="erros[coluna.id]"
                        class="text-danger-600 text-xs mt-1 ml-16"
                        role="alert"
                    >
                        {{ erros[coluna.id] }}
                    </p>
                </template>
            </draggable>
        </div>
    </div>
</template>
