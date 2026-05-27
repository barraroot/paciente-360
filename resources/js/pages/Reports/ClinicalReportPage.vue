<script setup>
import { onMounted } from 'vue';
import { useReportsStore } from '@/stores/reportsStore';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';

/**
 * T275 (Fase 8 — Lote E US-10.3) — Relatório Clínico.
 *
 * Escopo restrito (Médico vê só próprio) aplicado pelo backend (Q13).
 */
const store = useReportsStore();

onMounted(() => store.loadClinical());
</script>

<template>
    <section class="report">
        <h1>Relatório Clínico</h1>

        <LoadingState v-if="store.clinical.loading" :rows="4" />
        <ErrorState
            v-else-if="store.clinical.error"
            retryable
            :message="store.clinical.error"
            @retry="store.loadClinical()"
        />

        <div v-else-if="store.clinical.data" class="report__sections">
            <section>
                <h2>Taxa de Ocupação por Profissional</h2>
                <div
                    class="table-scroll"
                    tabindex="0"
                    role="region"
                    aria-label="Taxa de ocupação por profissional"
                >
                    <table>
                        <thead>
                            <tr>
                                <th>Profissional</th>
                                <th>Slots</th>
                                <th>Ocupação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in store.clinical.data.occupancy_by_professional ?? []"
                                :key="row.professional_id"
                            >
                                <td>{{ row.professional_name }}</td>
                                <td>{{ row.scheduled }}/{{ row.available }}</td>
                                <td>{{ row.occupancy_percent }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2>Top Procedimentos</h2>
                <ol>
                    <li
                        v-for="row in store.clinical.data.top_procedure_types ?? []"
                        :key="row.appointment_type_id"
                    >
                        {{ row.type_name }} — {{ row.count }}x
                    </li>
                </ol>
            </section>

            <section v-if="store.clinical.data.returns_stats">
                <h2>Cadências de Retorno</h2>
                <p>{{ store.clinical.data.returns_stats.summary ?? 'Sem dados.' }}</p>
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
.report__sections .table-scroll {
    overflow-x: auto;
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
