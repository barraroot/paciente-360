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
    store.fetchPersonas();
});

function goCreate() {
    router.push({ name: 'ia.personas.new' });
}

function goEdit(persona) {
    router.push({ name: 'ia.personas.edit', params: { id: persona.id } });
}

async function toggleActive(persona) {
    if (persona.is_active) {
        confirmTarget.value = persona;
        confirmMode.value = 'deactivate';
        return;
    }
    await store.setPersonaActive(persona.id, true);
}

function askDelete(persona) {
    confirmTarget.value = persona;
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
            await store.deletePersona(confirmTarget.value.id);
        } else if (confirmMode.value === 'deactivate') {
            await store.setPersonaActive(confirmTarget.value.id, false);
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
                <h1 class="text-xl font-semibold text-gray-900">Personas de IA</h1>
                <p class="text-sm text-gray-500">Bots que atendem seus canais conforme a matriz.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                @click="goCreate"
            >
                Nova persona
            </button>
        </div>

        <div v-if="store.loading" class="py-12 text-center text-gray-500">Carregando…</div>

        <div v-else-if="store.error" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
            {{ store.error }}
        </div>

        <div v-else-if="store.personas.length === 0" class="rounded-lg border border-dashed border-gray-300 py-12 text-center">
            <p class="text-gray-500">Nenhuma persona criada ainda.</p>
            <button type="button" class="mt-3 text-sm font-medium text-indigo-600 hover:underline" @click="goCreate">
                Criar a primeira persona
            </button>
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Modelo</th>
                        <th class="px-4 py-3">Canais</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="persona in store.personas" :key="persona.id" class="text-sm text-gray-700">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ persona.name }}</td>
                        <td class="px-4 py-3">{{ persona.model?.name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ persona.channels_count ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <span
                                :class="persona.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            >
                                {{ persona.is_active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <button type="button" class="text-indigo-600 hover:underline" @click="goEdit(persona)">Editar</button>
                            <button type="button" class="text-gray-600 hover:underline" @click="toggleActive(persona)">
                                {{ persona.is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button type="button" class="text-red-600 hover:underline" @click="askDelete(persona)">Excluir</button>
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
                aria-labelledby="ia-confirm-title"
                @click.self="closeConfirm"
                @keydown.esc="closeConfirm"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h2 id="ia-confirm-title" class="text-lg font-semibold text-gray-900">
                        {{ confirmMode === 'delete' ? 'Excluir persona' : 'Desativar persona' }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        <template v-if="confirmMode === 'delete'">
                            Tem certeza que deseja excluir <strong>{{ confirmTarget.name }}</strong>? Esta ação remove a persona da matriz.
                        </template>
                        <template v-else>
                            Desativar <strong>{{ confirmTarget.name }}</strong>? Ela deixará de receber novas conversas.
                        </template>
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="closeConfirm">
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="busy"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
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
