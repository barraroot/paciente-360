<script setup>
/**
 * **T171 (Fase 8 — Lote C US-9.1)** — Relatório com polling 30s (Q6 / AC-9.1.4).
 *
 * Durante `dispatching`, polling automático refresh dos contadores.
 * `completed/canceled` interrompe o polling.
 */
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useCampaignsStore } from '@/stores/campaignsStore';

const route = useRoute();
const store = useCampaignsStore();
const id = computed(() => route.params.id);
const r = computed(() => store.report);

let pollIntervalId = null;

async function refresh() {
    await store.fetchReport(id.value);

    // Polling 30s apenas enquanto dispatching.
    if (r.value && r.value.status === 'dispatching' && pollIntervalId === null) {
        pollIntervalId = setInterval(refresh, 30000);
    } else if (r.value && r.value.status !== 'dispatching' && pollIntervalId !== null) {
        clearInterval(pollIntervalId);
        pollIntervalId = null;
    }
}

onMounted(refresh);
onUnmounted(() => {
    if (pollIntervalId) {
        clearInterval(pollIntervalId);
        pollIntervalId = null;
    }
});

function fmtDate(iso) {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—';
}
</script>

<template>
    <div v-if="!r" class="p-6 text-sm text-foreground-muted">Carregando relatório…</div>

    <div v-else class="space-y-6 p-6">
        <header class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Relatório da Campanha #{{ r.campaign_id }}</h1>
                <p class="mt-1 text-sm text-foreground-muted">
                    Status: <strong>{{ r.status }}</strong> • Disparada em:
                    {{ fmtDate(r.dispatched_at) }}
                </p>
                <p v-if="r.status === 'dispatching'" class="mt-1 text-xs text-warning-700">
                    ⚡ Em disparo — relatório atualiza automaticamente a cada 30 segundos.
                </p>
            </div>
            <RouterLink
                :to="`/campaigns/${r.campaign_id}`"
                class="text-sm text-foreground-muted underline"
                >Voltar</RouterLink
            >
        </header>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded border bg-white p-4">
                <h3 class="text-xs font-medium text-foreground-muted">Elegíveis</h3>
                <p class="mt-1 text-2xl font-bold">{{ r.total_eligible ?? '—' }}</p>
            </div>
            <div class="rounded border bg-white p-4">
                <h3 class="text-xs font-medium text-foreground-muted">Enviados</h3>
                <p class="mt-1 text-2xl font-bold text-success-700">{{ r.total_dispatched }}</p>
            </div>
            <div class="rounded border bg-white p-4">
                <h3 class="text-xs font-medium text-foreground-muted">Bloqueados</h3>
                <p class="mt-1 text-2xl font-bold text-danger-700">{{ r.total_blocked }}</p>
            </div>
            <div class="rounded border bg-white p-4">
                <h3 class="text-xs font-medium text-foreground-muted">Agendamentos atribuíveis</h3>
                <p class="mt-1 text-2xl font-bold text-primary-700">
                    {{ r.attributed_appointments }}
                </p>
            </div>
        </div>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded border bg-white p-4">
                <h2 class="text-sm font-semibold">Status detalhado</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    <li
                        v-for="(count, status) in r.status_breakdown"
                        :key="status"
                        class="flex justify-between"
                    >
                        <span>{{ status }}</span>
                        <span class="font-mono">{{ count }}</span>
                    </li>
                </ul>
            </div>

            <div class="rounded border bg-white p-4">
                <h2 class="text-sm font-semibold">Motivos de bloqueio</h2>
                <ul v-if="Object.keys(r.blocked_breakdown).length" class="mt-3 space-y-1 text-sm">
                    <li
                        v-for="(count, reason) in r.blocked_breakdown"
                        :key="reason"
                        class="flex justify-between"
                    >
                        <span class="text-danger-700">{{ reason }}</span>
                        <span class="font-mono">{{ count }}</span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-success-700">✓ Nenhum bloqueio.</p>
            </div>
        </section>
    </div>
</template>
