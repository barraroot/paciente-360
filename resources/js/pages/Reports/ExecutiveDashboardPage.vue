<script setup>
import { onMounted, ref, computed } from 'vue'
import { useReportsStore } from '@/stores/reportsStore'
import KpiCardWithTrend from '@/components/Reports/KpiCardWithTrend.vue'

/**
 * T258 (Fase 8 — Lote E US-10.1) — Dashboard Executivo.
 *
 * 5 cards principais (Q8) + filtros de período + drill-down + export PDF.
 * Padrão Fase 6: modal a11y + toast local + skeleton + aria-live para erros.
 */
const store = useReportsStore()

const preset = ref('30d')
const drillModalOpen = ref(false)
const drillTrigger = ref(null)
const toast = ref(null)

const periodLabel = computed(() => {
    return { '7d': '7 dias', '30d': '30 dias', '90d': '90 dias' }[preset.value] ?? '30 dias'
})

const isStale = computed(() => {
    const lag = store.executive.data?.aggregation_lag_seconds
    if (typeof lag !== 'number') return false
    return lag > 7200 // 2h
})

function showToast(message, type = 'success') {
    toast.value = { message, type }
    setTimeout(() => { toast.value = null }, 5000)
}

function formatBRL(cents) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100)
}

async function reload() {
    await store.loadExecutive({ preset: preset.value })
    if (store.executive.error) showToast(store.executive.error, 'error')
}

async function drillInto(metric, evt) {
    drillTrigger.value = evt?.currentTarget ?? null
    await store.loadDrillDown(metric, { preset: preset.value })
    if (store.drillDown.error) {
        showToast(store.drillDown.error, 'error')
        return
    }
    drillModalOpen.value = true
}

async function exportPdf() {
    const ok = await store.exportPdf({ preset: preset.value })
    showToast(ok ? 'PDF exportado.' : (store.pdfExport.error ?? 'Falha ao exportar.'), ok ? 'success' : 'error')
}

function closeDrill() {
    drillModalOpen.value = false
    if (drillTrigger.value) drillTrigger.value.focus()
}

onMounted(() => reload())
</script>

<template>
    <section class="dashboard">
        <header class="dashboard__header">
            <h1>Dashboard Executivo</h1>
            <div class="dashboard__filters">
                <label for="period">Período:</label>
                <select id="period" v-model="preset" @change="reload">
                    <option value="7d">7 dias</option>
                    <option value="30d">30 dias</option>
                    <option value="90d">90 dias</option>
                </select>
                <button
                    type="button"
                    class="btn btn--secondary"
                    :disabled="store.pdfExport.loading"
                    @click="exportPdf"
                >
                    {{ store.pdfExport.loading ? 'Gerando…' : 'Exportar PDF' }}
                </button>
            </div>
        </header>

        <div
            v-if="isStale"
            class="banner banner--warning"
            role="status"
            aria-live="polite"
        >
            ⚠ Dados agregados estão desatualizados (lag &gt; 2h). Considere recarregar.
        </div>

        <div class="dashboard__period">Mostrando {{ periodLabel }}</div>

        <div class="dashboard__grid">
            <KpiCardWithTrend
                label="Leads"
                :value="store.executive.data?.leads_by_channel?.value ?? 0"
                :delta-percent="store.executive.data?.leads_by_channel?.delta_percent ?? null"
                :loading="store.executive.loading"
                drillable
                @drill="(e) => drillInto('leads_by_channel', e)"
            />
            <KpiCardWithTrend
                label="Conversão"
                :value="store.executive.data?.conversion_rate?.value ?? 0"
                unit="%"
                :delta-percent="store.executive.data?.conversion_rate?.delta_percent ?? null"
                :loading="store.executive.loading"
                drillable
                @drill="(e) => drillInto('conversion_rate', e)"
            />
            <KpiCardWithTrend
                label="No-show"
                :value="store.executive.data?.no_show_rate?.value ?? 0"
                unit="%"
                :delta-percent="store.executive.data?.no_show_rate?.delta_percent ?? null"
                :loading="store.executive.loading"
                drillable
                @drill="(e) => drillInto('no_show_rate', e)"
            />
            <KpiCardWithTrend
                label="Faturamento Estimado"
                :value="formatBRL(store.executive.data?.estimated_revenue?.value)"
                :delta-percent="store.executive.data?.estimated_revenue?.delta_percent ?? null"
                :loading="store.executive.loading"
            />
            <KpiCardWithTrend
                label="Resposta P95 (s)"
                :value="store.executive.data?.response_time_first_p95?.value ?? '—'"
                :delta-percent="store.executive.data?.response_time_first_p95?.delta_percent ?? null"
                :loading="store.executive.loading"
            />
        </div>

        <!-- Modal drill-down a11y (padrão Fase 6) -->
        <Teleport to="body">
            <div
                v-if="drillModalOpen"
                class="modal-overlay"
                role="dialog"
                aria-modal="true"
                aria-labelledby="drill-title"
                @click.self="closeDrill"
                @keydown.esc.prevent="closeDrill"
            >
                <div class="modal-panel">
                    <header class="modal-panel__header">
                        <h2 id="drill-title">Drill-down: {{ store.drillDown.metric }}</h2>
                        <button type="button" class="btn-icon" aria-label="Fechar" @click="closeDrill">×</button>
                    </header>
                    <div class="modal-panel__body">
                        <pre v-if="store.drillDown.data">{{ JSON.stringify(store.drillDown.data, null, 2) }}</pre>
                        <p v-else>Carregando…</p>
                    </div>
                </div>
            </div>
        </Teleport>

        <div
            v-if="toast"
            class="toast"
            :class="`toast--${toast.type}`"
            role="alert"
            aria-live="assertive"
        >
            {{ toast.message }}
        </div>
    </section>
</template>

<style scoped>
.dashboard { padding: 1.5rem; }
.dashboard__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.dashboard__filters { display: flex; align-items: center; gap: 0.75rem; }
.dashboard__period { color: #64748b; font-size: 0.875rem; margin-bottom: 1rem; }
.dashboard__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.banner { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
.banner--warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
.btn { padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; }
.btn--secondary { background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
.modal-panel { background: white; border-radius: 0.75rem; padding: 1.25rem; max-width: 720px; width: 90%; max-height: 80vh; overflow: auto; }
.modal-panel__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.btn-icon { background: transparent; border: none; font-size: 1.5rem; cursor: pointer; }
.toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 0.75rem 1rem; border-radius: 0.5rem; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.toast--success { background: #059669; }
.toast--error { background: #dc2626; }
</style>
