<script setup>
/**
 * **T059 (Fase 8 — Lote A US-13.2)** — Painel Admin Clínica de Direito ao Esquecimento.
 *
 * Acceptance:
 *   - AC-13.2.2: tarefa prioritária com countdown D+15.
 *   - AC-13.2.3: confirmação de execução revisa mapa Q26 antes.
 *   - AC-13.2.6/7: notificações progressivas vêm do cron `privacy:notify-deadlines`.
 */
import { onMounted, ref } from 'vue';
import { usePrivacyStore } from '@/stores/privacyStore';

const store = usePrivacyStore();

const showExecuteModal = ref(false);
const showDenyModal = ref(false);
const target = ref(null);
const denyReason = ref('');

onMounted(() => {
    store.fetchForgettingRequests({ only_open: true });
});

function openExecute(req) {
    target.value = req;
    showExecuteModal.value = true;
}

function openDeny(req) {
    target.value = req;
    denyReason.value = '';
    showDenyModal.value = true;
}

async function confirmExecute() {
    if (!target.value) return;
    try {
        await store.executeForgetting(target.value.id);
        showExecuteModal.value = false;
        target.value = null;
    } catch (_) {
        /* erro tratado no store */
    }
}

async function confirmDeny() {
    if (!target.value || denyReason.value.trim().length < 10) return;
    try {
        await store.denyForgetting(target.value.id, denyReason.value.trim());
        showDenyModal.value = false;
        target.value = null;
    } catch (_) {
        /* erro tratado no store */
    }
}

function urgencyClass(days) {
    if (days <= 2) return 'bg-danger-100 text-danger-800 border-danger-300';
    if (days <= 5) return 'bg-warning-100 text-warning-800 border-warning-300';
    return 'bg-success-100 text-success-800 border-success-300';
}

function fmtDate(iso) {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—';
}
</script>

<template>
    <div class="space-y-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Direito ao Esquecimento</h1>
            <p class="mt-1 text-sm text-foreground-muted">
                LGPD Art. 18º — solicitações abertas com countdown de 15 dias úteis.
            </p>
        </header>

        <section
            v-if="store.error"
            role="alert"
            class="rounded border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800"
        >
            {{ store.error }}
        </section>

        <section class="overflow-hidden rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead
                    class="bg-surface-muted text-left text-xs uppercase tracking-wider text-foreground-muted"
                >
                    <tr>
                        <th class="px-4 py-2">Paciente</th>
                        <th class="px-4 py-2">Solicitada em</th>
                        <th class="px-4 py-2">Canal</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Prazo</th>
                        <th class="px-4 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="store.loading">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-sm text-foreground-muted"
                        >
                            Carregando…
                        </td>
                    </tr>
                    <tr v-else-if="store.forgettingRequests.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-sm text-foreground-muted"
                        >
                            Sem solicitações abertas. Solicitações vencidas/executadas ficam em
                            "Histórico" (filtro futuro).
                        </td>
                    </tr>
                    <tr
                        v-else
                        v-for="r in store.forgettingRequests"
                        :key="r.id"
                        class="hover:bg-surface-muted"
                    >
                        <td class="px-4 py-2 font-mono text-xs">#{{ r.patient_id }}</td>
                        <td class="px-4 py-2 text-xs">{{ fmtDate(r.requested_at) }}</td>
                        <td class="px-4 py-2 text-xs">{{ r.channel_of_request }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded bg-surface-muted px-2 py-0.5 text-xs">{{
                                r.status_label
                            }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                :class="[
                                    'inline-block rounded border px-2 py-0.5 text-xs font-medium',
                                    urgencyClass(r.days_until_deadline),
                                ]"
                            >
                                {{ r.days_until_deadline }} dia(s)
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button
                                type="button"
                                class="mr-2 text-xs text-success-700 underline"
                                @click="openExecute(r)"
                            >
                                Executar
                            </button>
                            <button
                                type="button"
                                class="text-xs text-danger-700 underline"
                                @click="openDeny(r)"
                            >
                                Negar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Execute Modal -->
        <Teleport to="body">
            <div
                v-if="showExecuteModal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="exec-title"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-4"
                @keydown.esc.prevent="showExecuteModal = false"
                @click.self="showExecuteModal = false"
            >
                <div class="w-full max-w-lg rounded bg-white p-6 shadow-xl">
                    <h2 id="exec-title" class="text-lg font-semibold">
                        Confirmar execução do esquecimento
                    </h2>
                    <p class="mt-2 text-sm text-foreground">
                        Paciente <strong>#{{ target?.patient_id }}</strong> será anonimizado segundo
                        o mapa Q26:
                    </p>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-foreground">
                        <li>
                            <strong>Anonimizar</strong>: nome, CPF, telefone, e-mail, data
                            nascimento
                        </li>
                        <li><strong>Deletar</strong>: endereço, anotações livres</li>
                        <li>
                            <strong>Preservar</strong>: receitas controladas (2a), billing (5a),
                            audit (1a), consentimentos (5a)
                        </li>
                    </ul>
                    <p
                        class="mt-3 rounded border border-warning-200 bg-warning-50 p-2 text-xs text-warning-800"
                    >
                        Esta ação é <strong>irreversível</strong>. Audit log será gerado.
                    </p>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-sm hover:bg-surface-muted"
                            @click="showExecuteModal = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded bg-success-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-success-700 disabled:opacity-50"
                            :disabled="store.saving"
                            @click="confirmExecute"
                        >
                            {{ store.saving ? 'Executando…' : 'Executar esquecimento' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Deny Modal -->
            <div
                v-if="showDenyModal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="deny-title"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-4"
                @keydown.esc.prevent="showDenyModal = false"
                @click.self="showDenyModal = false"
            >
                <div class="w-full max-w-lg rounded bg-white p-6 shadow-xl">
                    <h2 id="deny-title" class="text-lg font-semibold">Negar solicitação</h2>
                    <p class="mt-2 text-sm text-foreground">Motivo (≥ 10 caracteres):</p>
                    <textarea
                        v-model="denyReason"
                        rows="4"
                        class="mt-2 w-full rounded border-border-strong text-sm"
                        placeholder="Ex.: Identidade não confirmada via documento."
                    ></textarea>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-sm hover:bg-surface-muted"
                            @click="showDenyModal = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded bg-danger-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-danger-700 disabled:opacity-50"
                            :disabled="store.saving || denyReason.trim().length < 10"
                            @click="confirmDeny"
                        >
                            Negar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
