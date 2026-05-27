<script setup>
import { onMounted } from 'vue';
import { useReportsStore } from '@/stores/reportsStore';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';

/**
 * T270 (Fase 8 — Lote E US-10.2) — Relatório Operacional.
 */
const store = useReportsStore();

onMounted(() => store.loadOperational());
</script>

<template>
    <section class="report">
        <h1>Relatório Operacional</h1>

        <LoadingState v-if="store.operational.loading" :rows="4" />
        <ErrorState
            v-else-if="store.operational.error"
            retryable
            :message="store.operational.error"
            @retry="store.loadOperational()"
        />

        <div v-else-if="store.operational.data" class="report__sections">
            <section>
                <h2>Tempo de Primeira Resposta</h2>
                <p>P50: {{ store.operational.data.first_response_time?.p50_seconds ?? '—' }}s</p>
                <p>P95: {{ store.operational.data.first_response_time?.p95_seconds ?? '—' }}s</p>
            </section>

            <section>
                <h2>Tempo de Resolução</h2>
                <p>P50: {{ store.operational.data.resolution_time?.p50_minutes ?? '—' }}min</p>
                <p>P95: {{ store.operational.data.resolution_time?.p95_minutes ?? '—' }}min</p>
            </section>

            <section>
                <h2>Volume por Atendente</h2>
                <div
                    class="table-scroll"
                    tabindex="0"
                    role="region"
                    aria-label="Volume por atendente"
                >
                    <table>
                        <thead>
                            <tr>
                                <th>Atendente</th>
                                <th>Mensagens</th>
                                <th>Conversas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in store.operational.data.volume_per_attendant ?? []"
                                :key="row.user_id"
                            >
                                <td>{{ row.user_name }}</td>
                                <td>{{ row.messages_count }}</td>
                                <td>{{ row.conversations_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2>Performance da IA</h2>
                <p>
                    Resoluções autônomas:
                    {{ store.operational.data.ai_performance?.autonomous_resolutions ?? 0 }}
                </p>
                <p>
                    Taxa de handoff:
                    {{ store.operational.data.ai_performance?.handoff_rate_percent ?? 0 }}%
                </p>
            </section>
        </div>
    </section>
</template>

<style scoped>
.report {
    padding: 1.5rem;
}
.report__sections {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1.5rem;
}
.report__sections .table-scroll {
    overflow-x: auto;
}
.report__sections section {
    background: var(--color-surface-elevated);
    border: 1px solid var(--color-border);
    border-radius: 0.5rem;
    padding: 1rem;
}
.report__sections h2 {
    font-size: 1rem;
    color: var(--color-foreground-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th,
td {
    text-align: left;
    padding: 0.5rem;
    border-bottom: 1px solid var(--color-border);
}
</style>
