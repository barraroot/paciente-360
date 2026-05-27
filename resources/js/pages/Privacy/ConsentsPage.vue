<script setup>
/**
 * **T033 (Fase 8 — Lote A US-13.1)** — Página de gestão de consentimentos LGPD.
 *
 * Acceptance cobertos:
 *   - AC-13.1.5: painel Admin Clínica lista consentimentos com filtros.
 *   - AC-13.1.6: exportação dos registros (botão futuro — Lote A T058).
 *
 * Estados: loading skeleton, empty state, error banner, paginação.
 */
import { onMounted, ref, computed } from 'vue';
import { usePrivacyStore } from '@/stores/privacyStore';

const store = usePrivacyStore();

const showRevokeModal = ref(false);
const revokeTarget = ref(null);
const revokeChannel = ref('manual');
const revokeScope = ref('all');

const totalActive = computed(() => store.activeConsents.length);
const totalRevoked = computed(() => store.revokedCount);

onMounted(() => {
    store.fetchConsents();
});

function applyFilter(key, value) {
    store.setFilter(key, value || null);
    store.fetchConsents();
}

function resetAllFilters() {
    store.resetFilters();
    store.fetchConsents();
}

function openRevoke(consent) {
    revokeTarget.value = consent;
    revokeChannel.value = consent.channel;
    revokeScope.value = 'all';
    showRevokeModal.value = true;
}

async function confirmRevoke() {
    if (!revokeTarget.value) return;

    try {
        await store.revoke({
            patient_id: revokeTarget.value.patient_id,
            finalidade: revokeTarget.value.finalidade,
            channel: revokeChannel.value,
            scope: revokeScope.value,
        });
        showRevokeModal.value = false;
        revokeTarget.value = null;
    } catch (_) {
        // Erro já tratado no store.
    }
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function stateClass(state) {
    return (
        {
            granted: 'bg-emerald-100 text-emerald-800 border-emerald-200',
            refused: 'bg-amber-100 text-amber-800 border-amber-200',
            revoked: 'bg-rose-100 text-rose-800 border-rose-200',
        }[state] || 'bg-gray-100 text-gray-800 border-gray-200'
    );
}
</script>

<template>
    <div class="space-y-6 p-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold">Privacidade — Consentimentos</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Registros LGPD por paciente e finalidade. Q24 — consentimento hierárquico
                    (transacional implícito, marketing/pesquisa opt-in explícito).
                </p>
            </div>
            <div class="flex flex-wrap gap-3 text-sm">
                <span class="rounded border border-emerald-200 bg-emerald-50 px-3 py-1">
                    <strong>{{ totalActive }}</strong> ativos
                </span>
                <span class="rounded border border-rose-200 bg-rose-50 px-3 py-1">
                    <strong>{{ totalRevoked }}</strong> revogados
                </span>
            </div>
        </header>

        <section class="rounded border bg-white p-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Finalidade</label>
                    <select
                        class="mt-1 w-full rounded border-gray-300 text-sm"
                        aria-label="Filtrar por finalidade"
                        :value="store.filters.finalidade"
                        @change="applyFilter('finalidade', $event.target.value)"
                    >
                        <option value="">Todas</option>
                        <option value="transacional">Transacional</option>
                        <option value="marketing">Marketing</option>
                        <option value="pesquisa">Pesquisa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Estado</label>
                    <select
                        class="mt-1 w-full rounded border-gray-300 text-sm"
                        aria-label="Filtrar por estado"
                        :value="store.filters.state"
                        @change="applyFilter('state', $event.target.value)"
                    >
                        <option value="">Todos</option>
                        <option value="granted">Concedido</option>
                        <option value="refused">Recusado</option>
                        <option value="revoked">Revogado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Canal</label>
                    <select
                        class="mt-1 w-full rounded border-gray-300 text-sm"
                        aria-label="Filtrar por canal"
                        :value="store.filters.channel"
                        @change="applyFilter('channel', $event.target.value)"
                    >
                        <option value="">Todos</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="instagram">Instagram</option>
                        <option value="web">Web (widget)</option>
                        <option value="form">Formulário</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button
                        type="button"
                        class="rounded border bg-gray-50 px-3 py-1.5 text-sm hover:bg-gray-100"
                        @click="resetAllFilters"
                    >
                        Limpar filtros
                    </button>
                </div>
            </div>
        </section>

        <section
            v-if="store.error"
            role="alert"
            class="rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"
        >
            {{ store.error }}
        </section>

        <section
            class="overflow-x-auto rounded border bg-white"
            tabindex="0"
            role="region"
            aria-label="Tabela de consentimentos"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Paciente</th>
                        <th class="px-4 py-2">Canal</th>
                        <th class="px-4 py-2">Finalidade</th>
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2">Concedido em</th>
                        <th class="px-4 py-2">Revogado em</th>
                        <th class="px-4 py-2">Termos</th>
                        <th class="px-4 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="store.loading">
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">
                            Carregando consentimentos…
                        </td>
                    </tr>
                    <tr v-else-if="store.consents.length === 0">
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">
                            Nenhum consentimento encontrado para os filtros aplicados.
                        </td>
                    </tr>
                    <tr v-else v-for="c in store.consents" :key="c.id" class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-xs">#{{ c.patient_id }}</td>
                        <td class="px-4 py-2">{{ c.channel }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{
                                c.finalidade_label
                            }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                :class="[
                                    'inline-block rounded border px-2 py-0.5 text-xs font-medium',
                                    stateClass(c.state),
                                ]"
                            >
                                {{ c.state }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-700">
                            {{ formatDate(c.granted_at) }}
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-700">
                            {{ formatDate(c.revoked_at) }}
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-500">v{{ c.terms_version }}</td>
                        <td class="px-4 py-2 text-right">
                            <button
                                v-if="c.is_active"
                                type="button"
                                class="text-xs text-rose-700 underline hover:text-rose-900"
                                @click="openRevoke(c)"
                            >
                                Revogar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <footer
                v-if="store.consentsPagination.total > 0"
                class="border-t bg-gray-50 px-4 py-2 text-xs text-gray-600"
            >
                Página {{ store.consentsPagination.current_page }} de
                {{ store.consentsPagination.last_page }} —
                {{ store.consentsPagination.total }} registro(s)
            </footer>
        </section>

        <!-- Modal de revogação manual — popover inline (Fase 6 UX pattern) -->
        <Teleport to="body">
            <div
                v-if="showRevokeModal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="revoke-title"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-4"
                @keydown.esc.prevent="showRevokeModal = false"
                @click.self="showRevokeModal = false"
            >
                <div class="w-full max-w-md rounded bg-white p-6 shadow-xl">
                    <h2 id="revoke-title" class="text-lg font-semibold">Revogar consentimento</h2>
                    <p class="mt-2 text-sm text-gray-700">
                        Você está prestes a revogar o consentimento de
                        <strong>{{ revokeTarget?.finalidade_label }}</strong> do paciente
                        <strong>#{{ revokeTarget?.patient_id }}</strong
                        >.
                    </p>

                    <div class="mt-4 space-y-3">
                        <label class="block text-xs font-medium text-gray-700">
                            Canal de origem da revogação
                            <input
                                v-model="revokeChannel"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300 text-sm"
                            />
                        </label>
                        <label class="block text-xs font-medium text-gray-700">
                            Escopo
                            <select
                                v-model="revokeScope"
                                class="mt-1 w-full rounded border-gray-300 text-sm"
                            >
                                <option value="all">Total — em todos os canais</option>
                                <option value="channel">Apenas este canal (metadata)</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-sm hover:bg-gray-50"
                            @click="showRevokeModal = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50"
                            :disabled="store.saving"
                            @click="confirmRevoke"
                        >
                            {{ store.saving ? 'Revogando…' : 'Confirmar revogação' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
