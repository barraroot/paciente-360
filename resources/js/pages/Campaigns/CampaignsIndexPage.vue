<script setup>
/**
 * **T169 (Fase 8 — Lote C US-9.1)** — Listagem de campanhas (AC-9.1.1).
 */
import { onMounted } from 'vue';
import { useCampaignsStore } from '@/stores/campaignsStore';

const store = useCampaignsStore();

onMounted(() => store.fetchCampaigns());

function applyFilter(key, value) {
    store.setFilter(key, value || null);
    store.fetchCampaigns();
}

function resetAll() {
    store.resetFilters();
    store.fetchCampaigns();
}

function statusClass(status) {
    return (
        {
            draft: 'bg-surface-muted text-foreground border-border-strong',
            scheduled: 'bg-primary-100 text-primary-800 border-primary-300',
            dispatching: 'bg-warning-100 text-warning-800 border-warning-300',
            completed: 'bg-success-100 text-success-800 border-success-300',
            canceled: 'bg-danger-100 text-danger-800 border-danger-300',
        }[status] || 'bg-surface-muted text-foreground border-border'
    );
}

function fmtDate(iso) {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—';
}
</script>

<template>
    <div class="space-y-6 p-6">
        <header class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Campanhas</h1>
                <p class="mt-1 text-sm text-foreground-muted">
                    Disparos em massa com guardrails LGPD + Meta (Princípio VI).
                </p>
            </div>
            <RouterLink
                to="/campaigns/new"
                class="rounded bg-success-700 px-4 py-2 text-sm font-medium text-white hover:bg-success-800"
            >
                Nova campanha
            </RouterLink>
        </header>

        <section class="rounded border bg-white p-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-foreground">Status</label>
                    <select
                        :value="store.filters.status"
                        @change="applyFilter('status', $event.target.value)"
                        aria-label="Filtrar por status"
                        class="mt-1 w-full rounded border-border-strong text-sm"
                    >
                        <option value="">Todos</option>
                        <option value="draft">Rascunho</option>
                        <option value="scheduled">Agendada</option>
                        <option value="dispatching">Em disparo</option>
                        <option value="completed">Concluída</option>
                        <option value="canceled">Cancelada</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-foreground">Canal</label>
                    <select
                        :value="store.filters.channel"
                        @change="applyFilter('channel', $event.target.value)"
                        aria-label="Filtrar por canal"
                        class="mt-1 w-full rounded border-border-strong text-sm"
                    >
                        <option value="">Todos</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="instagram">Instagram</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button
                        type="button"
                        @click="resetAll"
                        class="rounded border bg-surface-muted px-3 py-1.5 text-sm hover:bg-surface-muted"
                    >
                        Limpar filtros
                    </button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead
                    class="bg-surface-muted text-left text-xs uppercase tracking-wider text-foreground-muted"
                >
                    <tr>
                        <th class="px-4 py-2">Nome</th>
                        <th class="px-4 py-2">Canal</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Elegíveis</th>
                        <th class="px-4 py-2 text-right">Enviados</th>
                        <th class="px-4 py-2 text-right">Bloqueados</th>
                        <th class="px-4 py-2">Agendada</th>
                        <th class="px-4 py-2">Criada</th>
                        <th class="px-4 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="store.loading">
                        <td
                            colspan="9"
                            class="px-4 py-12 text-center text-sm text-foreground-muted"
                        >
                            Carregando…
                        </td>
                    </tr>
                    <tr v-else-if="store.campaigns.length === 0">
                        <td
                            colspan="9"
                            class="px-4 py-12 text-center text-sm text-foreground-muted"
                        >
                            Nenhuma campanha.
                        </td>
                    </tr>
                    <tr
                        v-else
                        v-for="c in store.campaigns"
                        :key="c.id"
                        class="hover:bg-surface-muted"
                    >
                        <td class="px-4 py-2">{{ c.name }}</td>
                        <td class="px-4 py-2 text-xs">{{ c.channel }}</td>
                        <td class="px-4 py-2">
                            <span
                                :class="[
                                    'inline-block rounded border px-2 py-0.5 text-xs font-medium',
                                    statusClass(c.status),
                                ]"
                            >
                                {{ c.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-xs">{{ c.total_eligible ?? '—' }}</td>
                        <td class="px-4 py-2 text-right text-xs">{{ c.total_dispatched }}</td>
                        <td class="px-4 py-2 text-right text-xs">{{ c.total_blocked }}</td>
                        <td class="px-4 py-2 text-xs">{{ fmtDate(c.scheduled_for) }}</td>
                        <td class="px-4 py-2 text-xs">{{ fmtDate(c.created_at) }}</td>
                        <td class="px-4 py-2 text-right">
                            <RouterLink
                                :to="`/campaigns/${c.id}`"
                                class="text-xs text-primary-700 underline"
                                >Ver</RouterLink
                            >
                            <RouterLink
                                v-if="c.status !== 'draft'"
                                :to="`/campaigns/${c.id}/report`"
                                class="ml-2 text-xs text-success-700 underline"
                                >Relatório</RouterLink
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
