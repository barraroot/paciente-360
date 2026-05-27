<script setup>
import { onMounted, ref } from 'vue';
import { useWebhooksStore } from '@/stores/webhooks';
import WebhookFormModal from '@/components/Integrations/WebhookFormModal.vue';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

/**
 * T203 (Fase 8 — Lote D US-11.1) — Configurações de webhooks.
 */
const store = useWebhooksStore();
const modalOpen = ref(false);
const editing = ref(null);
const toast = ref(null);
const deleteTarget = ref(null);

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => {
        toast.value = null;
    }, 5000);
}

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(endpoint) {
    editing.value = endpoint;
    modalOpen.value = true;
}

async function onSave(payload) {
    try {
        if (editing.value) {
            await store.updateEndpoint(editing.value.id, payload);
            showToast('Webhook atualizado.');
        } else {
            await store.createEndpoint(payload);
            showToast('Webhook criado. Copie o segredo agora!');
        }
        modalOpen.value = false;
    } catch (e) {
        showToast(e?.response?.data?.message ?? 'Falha ao salvar.', 'error');
    }
}

async function togglePause(endpoint) {
    try {
        await store.pauseResume(endpoint.id);
        showToast(endpoint.is_active ? 'Webhook pausado.' : 'Webhook reativado.');
    } catch (e) {
        showToast('Falha ao alterar status.', 'error');
    }
}

async function doDelete() {
    const endpoint = deleteTarget.value;
    deleteTarget.value = null;
    if (!endpoint) {
        return;
    }
    try {
        await store.deleteEndpoint(endpoint.id);
        showToast('Webhook removido.');
    } catch (e) {
        showToast('Falha ao remover.', 'error');
    }
}

onMounted(() => store.loadEndpoints());
</script>

<template>
    <section class="page">
        <header class="page__header">
            <h1>Webhooks</h1>
            <button type="button" class="btn btn--primary" @click="openCreate">
                + Novo webhook
            </button>
        </header>

        <div
            v-if="store.lastSecretPlaintext"
            class="banner banner--success"
            role="status"
            aria-live="polite"
        >
            <strong>Segredo gerado:</strong>
            <code>{{ store.lastSecretPlaintext }}</code>
            <button type="button" class="btn btn--small" @click="store.clearSecret">
                Copiei, ocultar
            </button>
            <p>Este é o único momento que você verá o segredo. Guarde-o agora.</p>
        </div>

        <p v-if="store.loading" aria-busy="true">Carregando…</p>
        <p v-else-if="store.error" role="alert">{{ store.error }}</p>

        <div
            v-else
            class="overflow-x-auto"
            tabindex="0"
            role="region"
            aria-label="Tabela de webhooks"
        >
            <table class="endpoints-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>URL</th>
                        <th>Eventos</th>
                        <th>Status</th>
                        <th>Falhas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ep in store.endpoints" :key="ep.id">
                        <td>{{ ep.name }}</td>
                        <td>
                            <code>{{ ep.url }}</code>
                        </td>
                        <td>
                            <small>{{ (ep.events_subscribed ?? []).length }} evento(s)</small>
                        </td>
                        <td>
                            <span
                                class="badge"
                                :class="ep.is_active ? 'badge--success' : 'badge--warning'"
                            >
                                {{ ep.is_active ? 'Ativo' : 'Pausado' }}
                            </span>
                        </td>
                        <td>{{ ep.failure_count }}</td>
                        <td class="actions">
                            <button type="button" class="btn btn--small" @click="openEdit(ep)">
                                Editar
                            </button>
                            <button type="button" class="btn btn--small" @click="togglePause(ep)">
                                {{ ep.is_active ? 'Pausar' : 'Reativar' }}
                            </button>
                            <button
                                type="button"
                                class="btn btn--small btn--danger"
                                @click="deleteTarget = ep"
                            >
                                Remover
                            </button>
                        </td>
                    </tr>
                    <tr v-if="store.endpoints.length === 0">
                        <td colspan="6" class="empty">Nenhum webhook cadastrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <WebhookFormModal
            :open="modalOpen"
            :endpoint="editing"
            @close="modalOpen = false"
            @save="onSave"
        />

        <ConfirmModal
            :open="!!deleteTarget"
            title="Remover webhook"
            @close="deleteTarget = null"
            @confirm="doDelete"
        >
            <p>
                Remover o webhook "<strong>{{ deleteTarget?.name }}</strong
                >"? Entregas pendentes serão canceladas.
            </p>
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700"
                    @click="doDelete"
                >
                    Remover
                </button>
            </template>
        </ConfirmModal>

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
.page {
    padding: 1.5rem;
}
.page__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.banner {
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}
.banner--success {
    background: var(--color-success-50);
    color: var(--color-success-800);
    border-left: 4px solid var(--color-success-500);
}
.banner code {
    display: block;
    padding: 0.5rem;
    background: #fff;
    border-radius: 0.25rem;
    margin: 0.5rem 0;
    word-break: break-all;
}
.endpoints-table {
    width: 100%;
    border-collapse: collapse;
}
.endpoints-table th,
.endpoints-table td {
    text-align: left;
    padding: 0.5rem;
    border-bottom: 1px solid var(--color-border);
}
.actions {
    display: flex;
    gap: 0.25rem;
}
.empty {
    color: var(--color-foreground-muted);
    text-align: center;
    padding: 2rem;
}
.badge {
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.badge--success {
    background: var(--color-success-50);
    color: var(--color-success-800);
}
.badge--warning {
    background: var(--color-warning-50);
    color: var(--color-warning-800);
}
.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
}
.btn--primary {
    background: var(--color-primary-700);
    color: white;
    border: none;
}
.btn--small {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.btn--danger {
    color: var(--color-danger-600);
}
.toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    color: white;
}
.toast--success {
    background: var(--color-success-600);
}
.toast--error {
    background: var(--color-danger-600);
}
</style>
