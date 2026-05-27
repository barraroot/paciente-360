<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '@/lib/api.js';

/**
 * T248 — Formulário criar/editar convênio (Admin Clínica).
 *
 * Modo criar: sem parâmetro `id` na rota.
 * Modo editar: `id` presente — carrega o convênio existente.
 */

const router = useRouter();
const route = useRoute();

const convenioId = computed(() => route.params.id ? Number(route.params.id) : null);
const modoEditar = computed(() => convenioId.value !== null);

const form = ref({
    nome: '',
    codigo_ans: '',
    is_active: true,
});

const loading = ref(false);
const loadingInit = ref(false);
const erros = ref({});
const erroGeral = ref(null);

async function carregarConvenio() {
    if (!modoEditar.value) { return; }
    loadingInit.value = true;
    try {
        const { data } = await api.get(`/convenios/${convenioId.value}`);
        const c = data.data ?? data;
        form.value.nome = c.nome;
        form.value.codigo_ans = c.codigo_ans ?? '';
        form.value.is_active = c.is_active;
    } catch {
        erroGeral.value = 'Erro ao carregar convênio.';
    } finally {
        loadingInit.value = false;
    }
}

async function salvar() {
    erros.value = {};
    erroGeral.value = null;
    loading.value = true;

    try {
        const payload = {
            nome: form.value.nome,
            codigo_ans: form.value.codigo_ans || null,
            is_active: form.value.is_active,
        };

        if (modoEditar.value) {
            await api.patch(`/convenios/${convenioId.value}`, payload);
        } else {
            await api.post('/convenios', payload);
        }

        router.push('/panel/convenios');
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            erros.value = err.response.data?.errors ?? {};
            erroGeral.value = err.response.data?.message ?? 'Verifique os campos.';
        } else if (status === 403) {
            erroGeral.value = 'Sem permissão para gerenciar convênios.';
        } else {
            erroGeral.value = 'Erro ao salvar convênio.';
        }
    } finally {
        loading.value = false;
    }
}

onMounted(() => carregarConvenio());
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-lg">
            <button
                type="button"
                class="mb-4 inline-flex items-center gap-1 text-sm text-foreground-muted transition hover:text-foreground"
                @click="router.push('/panel/convenios')"
            >
                ← Voltar
            </button>

            <h1 class="mb-6 text-xl font-bold text-foreground">
                {{ modoEditar ? 'Editar convênio' : 'Novo convênio' }}
            </h1>

            <!-- Loading init -->
            <div v-if="loadingInit" class="flex items-center justify-center py-20">
                <span class="text-sm text-foreground-muted">Carregando…</span>
            </div>

            <form v-else class="space-y-4" @submit.prevent="salvar">
                <!-- Nome -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                        Nome <span class="text-danger-600">*</span>
                    </label>
                    <input
                        v-model="form.nome"
                        type="text"
                        class="w-full rounded-lg border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:ring-2 focus:ring-primary-200"
                        :class="erros.nome ? 'border-danger-500' : 'border-border focus:border-primary-500'"
                        maxlength="150"
                        required
                    />
                    <p v-if="erros.nome" class="mt-1 text-xs text-danger-600">{{ erros.nome[0] }}</p>
                </div>

                <!-- Código ANS -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">Código ANS</label>
                    <input
                        v-model="form.codigo_ans"
                        type="text"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        maxlength="10"
                    />
                    <p v-if="erros.codigo_ans" class="mt-1 text-xs text-danger-600">{{ erros.codigo_ans[0] }}</p>
                </div>

                <!-- Status ativo -->
                <div class="flex items-center gap-2">
                    <input
                        id="is-active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-border text-primary-700"
                    />
                    <label for="is-active" class="text-sm text-foreground">Ativo</label>
                </div>

                <!-- Erro geral -->
                <div
                    v-if="erroGeral"
                    role="alert"
                    class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-600"
                >
                    {{ erroGeral }}
                </div>

                <!-- Ações -->
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface-elevated"
                        @click="router.push('/panel/convenios')"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 disabled:opacity-60"
                    >
                        {{ loading ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
