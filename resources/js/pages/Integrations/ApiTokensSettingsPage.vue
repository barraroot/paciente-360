<script setup>
import { onMounted, ref } from 'vue';
import api from '@/lib/api';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

/**
 * T232 (Fase 8 — Lote D US-11.2) — Gestão interna de tokens API públicos.
 *
 * Modal exibe plaintext UMA vez após criação.
 */
const tokens = ref([]);
const loading = ref(false);
const modalOpen = ref(false);
const lastToken = ref(null);
const form = ref({ name: '', abilities: ['*'] });
const error = ref(null);
const toast = ref(null);
const revokeTarget = ref(null);

function showToast(msg, type = 'success') {
    toast.value = { message: msg, type };
    setTimeout(() => {
        toast.value = null;
    }, 5000);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/integrations/api-tokens');
        tokens.value = data.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Falha ao carregar tokens.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    form.value = { name: '', abilities: ['*'] };
    lastToken.value = null;
    modalOpen.value = true;
}

async function onSubmit() {
    try {
        const { data } = await api.post('/integrations/api-tokens', form.value);
        lastToken.value = data.meta?.token_plaintext ?? null;
        await load();
        showToast('Token criado. Copie agora!');
    } catch (e) {
        showToast(e?.response?.data?.message ?? 'Falha ao criar.', 'error');
    }
}

async function confirmRevoke() {
    const token = revokeTarget.value;
    revokeTarget.value = null;
    if (!token) {
        return;
    }
    try {
        await api.delete(`/integrations/api-tokens/${token.id}`);
        showToast('Token revogado.');
        await load();
    } catch (e) {
        showToast('Falha ao revogar.', 'error');
    }
}

onMounted(load);
</script>

<template>
    <section class="page">
        <header class="page__header">
            <h1>Tokens da API Pública</h1>
            <button type="button" class="btn btn--primary" @click="openCreate">+ Novo token</button>
        </header>

        <p v-if="loading" aria-busy="true">Carregando…</p>
        <p v-else-if="error" role="alert">{{ error }}</p>

        <div
            v-else
            class="overflow-x-auto"
            tabindex="0"
            role="region"
            aria-label="Tabela de tokens de API"
        >
            <table class="tokens-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Abilities</th>
                        <th>Último uso</th>
                        <th>Criado em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tokens" :key="t.id">
                        <td>{{ t.name }}</td>
                        <td>
                            <small>{{ (t.abilities ?? []).join(', ') }}</small>
                        </td>
                        <td>{{ t.last_used_at ?? '—' }}</td>
                        <td>{{ t.created_at }}</td>
                        <td>
                            <button
                                type="button"
                                class="btn btn--small btn--danger"
                                @click="revokeTarget = t"
                            >
                                Revogar
                            </button>
                        </td>
                    </tr>
                    <tr v-if="tokens.length === 0">
                        <td colspan="5" class="empty">Nenhum token criado.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ConfirmModal
            :open="!!revokeTarget"
            title="Revogar token"
            @close="revokeTarget = null"
            @confirm="confirmRevoke"
        >
            <p>
                Revogar o token "<strong>{{ revokeTarget?.name }}</strong
                >"? Integrações que o utilizam falharão.
            </p>
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700"
                    @click="confirmRevoke"
                >
                    Revogar
                </button>
            </template>
        </ConfirmModal>

        <Teleport to="body">
            <div
                v-if="modalOpen"
                class="modal-overlay"
                role="dialog"
                aria-modal="true"
                aria-labelledby="token-form-title"
                @click.self="modalOpen = false"
                @keydown.esc.prevent="modalOpen = false"
            >
                <div class="modal-panel">
                    <header class="modal-panel__header">
                        <h2 id="token-form-title">Novo token API</h2>
                        <button
                            type="button"
                            class="btn-icon"
                            aria-label="Fechar"
                            @click="modalOpen = false"
                        >
                            ×
                        </button>
                    </header>

                    <div v-if="lastToken" class="banner banner--success">
                        <p><strong>Token criado — copie agora:</strong></p>
                        <code>{{ lastToken }}</code>
                        <p>Este é o único momento que ele será exibido.</p>
                        <button
                            type="button"
                            class="btn btn--primary"
                            @click="
                                modalOpen = false;
                                lastToken = null;
                            "
                        >
                            Fechei
                        </button>
                    </div>

                    <form v-else @submit.prevent="onSubmit">
                        <div class="field">
                            <label for="t-name">Nome (identifica o uso, ex.: "Zapier prod")</label>
                            <input
                                id="t-name"
                                v-model="form.name"
                                type="text"
                                maxlength="120"
                                required
                            />
                        </div>
                        <footer class="modal-panel__footer">
                            <button
                                type="button"
                                class="btn btn--secondary"
                                @click="modalOpen = false"
                            >
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn--primary">Criar</button>
                        </footer>
                    </form>
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
.page {
    padding: 1.5rem;
}
.page__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.tokens-table {
    width: 100%;
    border-collapse: collapse;
}
.tokens-table th,
.tokens-table td {
    text-align: left;
    padding: 0.5rem;
    border-bottom: 1px solid #f1f5f9;
}
.empty {
    color: #94a3b8;
    text-align: center;
    padding: 2rem;
}
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
}
.modal-panel {
    background: white;
    border-radius: 0.75rem;
    padding: 1.25rem;
    max-width: 540px;
    width: 90%;
}
.modal-panel__header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}
.modal-panel__footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 1rem;
}
.field {
    margin-bottom: 1rem;
}
.field label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.25rem;
}
.field input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
}
.banner {
    padding: 1rem;
    border-radius: 0.5rem;
}
.banner--success {
    background: #d1fae5;
    color: #065f46;
}
.banner code {
    display: block;
    padding: 0.75rem;
    background: white;
    border-radius: 0.25rem;
    word-break: break-all;
    margin: 0.5rem 0;
    font-size: 0.875rem;
}
.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    cursor: pointer;
    font-weight: 500;
}
.btn--primary {
    background: #2563eb;
    color: white;
    border: none;
}
.btn--secondary {
    background: #f1f5f9;
    color: #0f172a;
    border: 1px solid #cbd5e1;
}
.btn--small {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.btn--danger {
    color: #dc2626;
}
.btn-icon {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
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
    background: #059669;
}
.toast--error {
    background: #dc2626;
}
</style>
