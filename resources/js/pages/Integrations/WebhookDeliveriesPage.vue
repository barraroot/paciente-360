<script setup>
import { onMounted, ref } from 'vue'
import { useWebhooksStore } from '@/stores/webhooks'

/**
 * T203 (Fase 8 — Lote D US-11.1) — Histórico + DLQ.
 */
const store = useWebhooksStore()
const toast = ref(null)

function showToast(msg, type = 'success') {
    toast.value = { message: msg, type }
    setTimeout(() => { toast.value = null }, 5000)
}

async function resend(dlq) {
    if (!window.confirm(`Reenviar evento ${dlq.event_type} (ID ${dlq.event_id})?`)) return
    try {
        await store.resendDlq(dlq.id)
        showToast('Reenfileirado.')
    } catch (e) {
        showToast('Falha ao reenviar.', 'error')
    }
}

onMounted(() => store.loadDeadLetter())
</script>

<template>
    <section class="page">
        <h1>Dead Letter Queue (Webhooks)</h1>
        <p class="hint">Eventos que esgotaram 5 tentativas. Retenção 30 dias.</p>

        <table class="dlq-table">
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>Falhou em</th>
                    <th>Expira em</th>
                    <th>Reenviado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in store.deadLetter" :key="row.id">
                    <td>
                        <code>{{ row.event_type }}</code><br>
                        <small>{{ row.event_id }}</small>
                    </td>
                    <td>{{ row.failed_at }}</td>
                    <td>{{ row.expires_at }}</td>
                    <td>
                        <span v-if="row.resent_at" class="badge badge--success">Sim — {{ row.resent_at }}</span>
                        <span v-else class="badge badge--warning">Não</span>
                    </td>
                    <td>
                        <button type="button" class="btn btn--small" @click="resend(row)">Reenviar</button>
                    </td>
                </tr>
                <tr v-if="store.deadLetter.length === 0">
                    <td colspan="5" class="empty">Sem eventos no DLQ.</td>
                </tr>
            </tbody>
        </table>

        <div v-if="toast" class="toast" :class="`toast--${toast.type}`" role="alert" aria-live="assertive">
            {{ toast.message }}
        </div>
    </section>
</template>

<style scoped>
.page { padding: 1.5rem; }
.hint { color: #64748b; font-size: 0.875rem; margin-bottom: 1rem; }
.dlq-table { width: 100%; border-collapse: collapse; }
.dlq-table th, .dlq-table td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; }
.badge { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; }
.badge--success { background: #d1fae5; color: #065f46; }
.badge--warning { background: #fef3c7; color: #92400e; }
.empty { color: #94a3b8; text-align: center; padding: 2rem; }
.btn { padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
.btn--small { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
.toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 0.75rem 1rem; border-radius: 0.5rem; color: white; }
.toast--success { background: #059669; }
.toast--error { background: #dc2626; }
</style>
