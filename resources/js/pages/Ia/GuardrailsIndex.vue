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
    store.fetchGuardrails();
});

function goCreate() {
    router.push({ name: 'ia.guardrails.new' });
}

function goEdit(guardrail) {
    router.push({ name: 'ia.guardrails.edit', params: { id: guardrail.id } });
}

async function toggleActive(guardrail) {
    if (guardrail.is_active) {
        confirmTarget.value = guardrail;
        confirmMode.value = 'deactivate';
        return;
    }
    await store.setGuardrailActive(guardrail.id, true);
}

function askDelete(guardrail) {
    confirmTarget.value = guardrail;
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
            await store.deleteGuardrail(confirmTarget.value.id);
        } else if (confirmMode.value === 'deactivate') {
            await store.setGuardrailActive(confirmTarget.value.id, false);
        }
        closeConfirm();
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-foreground">Guardrails da Clínica</h1>
                <p class="text-sm text-foreground-muted">
                    Restrições adicionais sobre o piso de segurança obrigatório da IA.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                @click="goCreate"
            >
                Novo guardrail
            </button>
        </div>

        <div class="mb-4 rounded-lg bg-warning-50 p-3 text-xs text-warning-800">
            As regras médicas mínimas (sem diagnóstico, sem prescrição, escalar urgências, etc.) são
            sempre aplicadas, mesmo sem nenhum guardrail cadastrado aqui.
        </div>

        <div v-if="store.loading" class="py-12 text-center text-foreground-muted">Carregando…</div>

        <div v-else-if="store.error" class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700">
            {{ store.error }}
        </div>

        <div
            v-else-if="store.guardrails.length === 0"
            class="rounded-lg border border-dashed border-border-strong py-12 text-center"
        >
            <p class="text-foreground-muted">Nenhum guardrail criado ainda.</p>
            <button
                type="button"
                class="mt-3 text-sm font-medium text-primary-600 hover:underline"
                @click="goCreate"
            >
                Criar o primeiro guardrail
            </button>
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-border bg-white">
            <table class="min-w-full divide-y divide-border">
                <thead class="bg-surface-muted">
                    <tr
                        class="text-left text-xs font-medium uppercase tracking-wide text-foreground-muted"
                    >
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Categoria</th>
                        <th class="px-4 py-3">Personas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="guardrail in store.guardrails"
                        :key="guardrail.id"
                        class="text-sm text-foreground"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">{{ guardrail.name }}</td>
                        <td class="px-4 py-3">
                            <span
                                v-if="guardrail.category"
                                class="inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-xs text-foreground-muted"
                            >
                                {{ guardrail.category }}
                            </span>
                            <span v-else class="text-foreground-subtle">—</span>
                        </td>
                        <td class="px-4 py-3">{{ guardrail.personas_count ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <span
                                :class="
                                    guardrail.is_active
                                        ? 'bg-success-100 text-success-700'
                                        : 'bg-surface-muted text-foreground-muted'
                                "
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            >
                                {{ guardrail.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <button
                                type="button"
                                class="text-primary-600 hover:underline"
                                @click="goEdit(guardrail)"
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                class="text-foreground-muted hover:underline"
                                @click="toggleActive(guardrail)"
                            >
                                {{ guardrail.is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button
                                type="button"
                                class="text-danger-600 hover:underline"
                                @click="askDelete(guardrail)"
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
                aria-labelledby="guardrail-confirm-title"
                @click.self="closeConfirm"
                @keydown.esc="closeConfirm"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h2 id="guardrail-confirm-title" class="text-lg font-semibold text-foreground">
                        {{ confirmMode === 'delete' ? 'Excluir guardrail' : 'Desativar guardrail' }}
                    </h2>
                    <p class="mt-2 text-sm text-foreground-muted">
                        <template v-if="confirmMode === 'delete'">
                            Tem certeza que deseja excluir <strong>{{ confirmTarget.name }}</strong
                            >?
                        </template>
                        <template v-else>
                            Desativar <strong>{{ confirmTarget.name }}</strong
                            >? A IA deixará de aplicá-lo em novas respostas.
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
