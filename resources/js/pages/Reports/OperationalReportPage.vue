<script setup>
import { onMounted } from 'vue'
import { useReportsStore } from '@/stores/reportsStore'

/**
 * T270 (Fase 8 — Lote E US-10.2) — Relatório Operacional.
 */
const store = useReportsStore()

onMounted(() => store.loadOperational())
</script>

<template>
    <section class="report">
        <h1>Relatório Operacional</h1>

        <p v-if="store.operational.loading" aria-busy="true">Carregando…</p>
        <p v-else-if="store.operational.error" role="alert">{{ store.operational.error }}</p>

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
                <table>
                    <thead><tr><th>Atendente</th><th>Mensagens</th><th>Conversas</th></tr></thead>
                    <tbody>
                        <tr v-for="row in (store.operational.data.volume_per_attendant ?? [])" :key="row.user_id">
                            <td>{{ row.user_name }}</td>
                            <td>{{ row.messages_count }}</td>
                            <td>{{ row.conversations_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section>
                <h2>Performance da IA</h2>
                <p>Resoluções autônomas: {{ store.operational.data.ai_performance?.autonomous_resolutions ?? 0 }}</p>
                <p>Taxa de handoff: {{ store.operational.data.ai_performance?.handoff_rate_percent ?? 0 }}%</p>
            </section>
        </div>
    </section>
</template>

<style scoped>
.report { padding: 1.5rem; }
.report__sections { display: grid; gap: 1.5rem; }
.report__sections section { background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; }
.report__sections h2 { font-size: 1rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; }
</style>
