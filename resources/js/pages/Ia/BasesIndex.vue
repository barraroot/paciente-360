<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useIaStore } from '@/stores/ia.js';

const router = useRouter();
const store = useIaStore();

const confirmTarget = ref(null);
const confirmMode = ref(null); // 'delete' | 'deactivate'
const busy = ref(false);

onMounted(() => {
    store.fetchKnowledgeBases();
});

function goCreate() {
    router.push({ name: 'ia.bases.new' });
}

function goEdit(base) {
    router.push({ name: 'ia.bases.edit', params: { id: base.id } });
}

async function toggleActive(base) {
    if (base.is_active) {
        confirmTarget.value = base;
        confirmMode.value = 'deactivate';
        return;
    }
    await store.setKnowledgeBaseActive(base.id, true);
}

function askDelete(base) {
    confirmTarget.value = base;
    confirmMode.value = 'delete';
}

function closeConfirm() {
    confirmTarget.value = null;
    confirmMode.value = null;
}

async function confirmAction() {
    if (!confirmTarget.value) return;
    busy.value = true;
    try {
        if (confirmMode.value === 'delete') {
            await store.deleteKnowledgeBase(confirmTarget.value.id);
        } else if (confirmMode.value === 'deactivate') {
            await store.setKnowledgeBaseActive(confirmTarget.value.id, false);
        }
        closeConfirm();
    } finally {
        busy.value = false;
    }
}

function indexStatus(base) {
    return base.indexed_at ? 'Indexada' : 'Indexando…';
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-foreground">Bases de Conhecimento</h1>
                <p class="text-sm text-foreground-muted">
                    Conteúdo que a IA consulta para responder (RAG). Associe as bases às personas.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                @click="goCreate"
            >
                Nova base
            </button>
        </div>

        <div v-if="store.loading" class="py-12 text-center text-foreground-muted">Carregando…</div>

        <div v-else-if="store.error" class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700">
            {{ store.error }}
        </div>

        <div
            v-else-if="store.knowledgeBases.length === 0"
            class="rounded-lg border border-dashed border-border-strong py-12 text-center"
        >
            <p class="text-foreground-muted">Nenhuma base de conhecimento criada ainda.</p>
            <button
                type="button"
                class="mt-3 text-sm font-medium text-indigo-600 hover:underline"
                @click="goCreate"
            >
                Criar a primeira base
            </button>
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-border bg-white">
            <table class="min-w-full divide-y divide-border">
                <thead class="bg-surface-muted">
                    <tr
                        class="text-left text-xs font-medium uppercase tracking-wide text-foreground-muted"
                    >
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Personas</th>
                        <th class="px-4 py-3">Indexação</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="base in store.knowledgeBases"
                        :key="base.id"
                        class="text-sm text-foreground"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">{{ base.name }}</td>
                        <td class="px-4 py-3">{{ base.personas_count ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <span
                                :class="
                                    base.indexed_at ? 'text-foreground-muted' : 'text-warning-600'
                                "
                                class="text-xs"
                            >
                                {{ indexStatus(base) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="
                                    base.is_active
                                        ? 'bg-success-100 text-success-700'
                                        : 'bg-surface-muted text-foreground-muted'
                                "
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            >
                                {{ base.is_active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <button
                                type="button"
                                class="text-indigo-600 hover:underline"
                                @click="goEdit(base)"
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                class="text-foreground-muted hover:underline"
                                @click="toggleActive(base)"
                            >
                                {{ base.is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button
                                type="button"
                                class="text-danger-600 hover:underline"
                                @click="askDelete(base)"
                            >
                                Excluir
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Confirmação (a11y) — sem window.confirm -->
        <Teleport to="body">
            <div
                v-if="confirmTarget"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="base-confirm-title"
                @click.self="closeConfirm"
                @keydown.esc="closeConfirm"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h2 id="base-confirm-title" class="text-lg font-semibold text-foreground">
                        {{ confirmMode === 'delete' ? 'Excluir base' : 'Desativar base' }}
                    </h2>
                    <p class="mt-2 text-sm text-foreground-muted">
                        <template v-if="confirmMode === 'delete'">
                            Tem certeza que deseja excluir <strong>{{ confirmTarget.name }}</strong
                            >? Os trechos indexados também serão removidos.
                        </template>
                        <template v-else>
                            Desativar <strong>{{ confirmTarget.name }}</strong
                            >? A IA deixará de consultá-la em novas respostas.
                        </template>
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm text-foreground-muted hover:bg-surface-muted"
                            @click="closeConfirm"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="busy"
                            class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-medium text-white hover:bg-danger-700 disabled:opacity-50"
                            @click="confirmAction"
                        >
                            {{ confirmMode === 'delete' ? 'Excluir' : 'Desativar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
