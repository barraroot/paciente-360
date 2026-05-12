<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';

const { t } = useI18n();
const store = useInboxStore();

// ─── Estado ───────────────────────────────────────────────────────────────────

const activeTab = ref('team'); // 'team' | 'mine'
const search = ref('');
const showModal = ref(false);

// ─── Form (criar/editar) ──────────────────────────────────────────────────────

const initialForm = () => ({ id: null, scope: 'private', shortcut: '', content: '' });
const form = ref(initialForm());
const formError = ref(null);
const saving = ref(false);
const confirmDeleteId = ref(null);
const deleting = ref(false);

// ─── Dados ────────────────────────────────────────────────────────────────────

const teamReplies = computed(() =>
    (store.quickReplies ?? []).filter((r) => r.scope === 'tenant'),
);

const myReplies = computed(() =>
    (store.quickReplies ?? []).filter((r) => r.scope === 'private'),
);

const filteredTeam = computed(() => {
    const q = search.value.toLowerCase();
    return q
        ? teamReplies.value.filter(
              (r) =>
                  r.shortcut.toLowerCase().includes(q) ||
                  r.content.toLowerCase().includes(q),
          )
        : teamReplies.value;
});

const filteredMine = computed(() => {
    const q = search.value.toLowerCase();
    return q
        ? myReplies.value.filter(
              (r) =>
                  r.shortcut.toLowerCase().includes(q) ||
                  r.content.toLowerCase().includes(q),
          )
        : myReplies.value;
});

// ─── Carregar ─────────────────────────────────────────────────────────────────

onMounted(async () => {
    await store.loadQuickReplies();
});

// ─── Modal ────────────────────────────────────────────────────────────────────

function openCreate() {
    form.value = initialForm();
    // Pré-seleciona scope conforme a aba ativa
    form.value.scope = activeTab.value === 'team' ? 'tenant' : 'private';
    formError.value = null;
    showModal.value = true;
}

function openEdit(reply) {
    form.value = { id: reply.id, scope: reply.scope, shortcut: reply.shortcut, content: reply.content };
    formError.value = null;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

async function saveForm() {
    if (!form.value.shortcut.startsWith('/')) {
        formError.value = t('inbox.respostas_rapidas.shortcut_deve_comecar_com_barra');
        return;
    }
    if (!form.value.content.trim()) {
        formError.value = t('inbox.respostas_rapidas.conteudo_obrigatorio');
        return;
    }

    saving.value = true;
    formError.value = null;

    try {
        if (form.value.id) {
            await store.updateQuickReply(form.value.id, {
                shortcut: form.value.shortcut,
                content: form.value.content,
            });
        } else {
            await store.createQuickReply({
                scope: form.value.scope,
                shortcut: form.value.shortcut,
                content: form.value.content,
            });
        }
        closeModal();
    } catch (err) {
        const status = err.response?.status;
        if (status === 409) {
            formError.value = t('inbox.respostas_rapidas.atalho_duplicado');
        } else if (status === 403) {
            formError.value = t('common.sem_permissao');
        } else {
            const msg = err.response?.data?.message ?? t('common.error_generic');
            formError.value = msg;
        }
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(id) {
    confirmDeleteId.value = id;
}

async function doDelete() {
    if (!confirmDeleteId.value) { return; }
    deleting.value = true;
    try {
        await store.deleteQuickReply(confirmDeleteId.value);
    } catch {
        // silencioso
    } finally {
        deleting.value = false;
        confirmDeleteId.value = null;
    }
}
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h1 class="text-lg font-semibold text-foreground">
                {{ t('inbox.respostas_rapidas.titulo') }}
            </h1>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="openCreate"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                </svg>
                {{ t('inbox.respostas_rapidas.nova') }}
            </button>
        </div>

        <!-- Busca + Tabs -->
        <div class="border-b border-border px-6 pt-4">
            <!-- Busca -->
            <div class="mb-4">
                <input
                    v-model="search"
                    type="search"
                    :placeholder="t('inbox.respostas_rapidas.buscar_placeholder')"
                    class="w-full max-w-xs rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground placeholder-foreground-muted outline-none focus:border-primary-400"
                />
            </div>

            <!-- Tabs -->
            <div class="flex gap-4" role="tablist">
                <button
                    role="tab"
                    :aria-selected="activeTab === 'team'"
                    class="border-b-2 pb-2 text-sm font-medium transition-colors focus-visible:outline-none"
                    :class="
                        activeTab === 'team'
                            ? 'border-primary-600 text-primary-700'
                            : 'border-transparent text-foreground-muted hover:text-foreground'
                    "
                    @click="activeTab = 'team'"
                >
                    {{ t('inbox.respostas_rapidas.tab_equipe') }}
                    <span class="ml-1.5 rounded-full bg-surface-raised px-1.5 py-0.5 text-xs text-foreground-muted">
                        {{ filteredTeam.length }}
                    </span>
                </button>
                <button
                    role="tab"
                    :aria-selected="activeTab === 'mine'"
                    class="border-b-2 pb-2 text-sm font-medium transition-colors focus-visible:outline-none"
                    :class="
                        activeTab === 'mine'
                            ? 'border-primary-600 text-primary-700'
                            : 'border-transparent text-foreground-muted hover:text-foreground'
                    "
                    @click="activeTab = 'mine'"
                >
                    {{ t('inbox.respostas_rapidas.tab_minhas') }}
                    <span class="ml-1.5 rounded-full bg-surface-raised px-1.5 py-0.5 text-xs text-foreground-muted">
                        {{ filteredMine.length }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Lista -->
        <div class="flex-1 overflow-y-auto">
            <div role="tabpanel">
                <!-- Da equipe -->
                <template v-if="activeTab === 'team'">
                    <div v-if="store.loadingQuickReplies" class="p-6 text-sm text-foreground-muted">
                        {{ t('common.carregando') }}
                    </div>
                    <div v-else-if="filteredTeam.length === 0" class="p-6 text-sm text-foreground-muted">
                        {{ t('inbox.respostas_rapidas.vazio') }}
                    </div>
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="reply in filteredTeam"
                            :key="reply.id"
                            class="flex items-start justify-between gap-4 px-6 py-4 hover:bg-surface-raised"
                        >
                            <div class="min-w-0 flex-1">
                                <span class="mr-2 inline-block rounded bg-primary-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-primary-700">
                                    {{ reply.shortcut }}
                                </span>
                                <p class="mt-1 truncate text-sm text-foreground">{{ reply.content }}</p>
                                <p v-if="reply.usage_count > 0" class="mt-0.5 text-xs text-foreground-muted">
                                    {{ t('inbox.respostas_rapidas.usado_vezes', { n: reply.usage_count }) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button
                                    type="button"
                                    class="rounded p-1 text-foreground-muted hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    :title="t('common.editar')"
                                    @click="openEdit(reply)"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-foreground-muted hover:text-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500"
                                    :title="t('common.excluir')"
                                    @click="confirmDelete(reply.id)"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </li>
                    </ul>
                </template>

                <!-- Minhas -->
                <template v-else>
                    <div v-if="store.loadingQuickReplies" class="p-6 text-sm text-foreground-muted">
                        {{ t('common.carregando') }}
                    </div>
                    <div v-else-if="filteredMine.length === 0" class="p-6 text-sm text-foreground-muted">
                        {{ t('inbox.respostas_rapidas.vazio_privado') }}
                    </div>
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="reply in filteredMine"
                            :key="reply.id"
                            class="flex items-start justify-between gap-4 px-6 py-4 hover:bg-surface-raised"
                        >
                            <div class="min-w-0 flex-1">
                                <span class="mr-2 inline-block rounded bg-surface-raised px-1.5 py-0.5 font-mono text-xs font-semibold text-foreground-muted">
                                    {{ reply.shortcut }}
                                </span>
                                <p class="mt-1 truncate text-sm text-foreground">{{ reply.content }}</p>
                                <p v-if="reply.usage_count > 0" class="mt-0.5 text-xs text-foreground-muted">
                                    {{ t('inbox.respostas_rapidas.usado_vezes', { n: reply.usage_count }) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button
                                    type="button"
                                    class="rounded p-1 text-foreground-muted hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    :title="t('common.editar')"
                                    @click="openEdit(reply)"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-foreground-muted hover:text-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500"
                                    :title="t('common.excluir')"
                                    @click="confirmDelete(reply.id)"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </li>
                    </ul>
                </template>
            </div>
        </div>

        <!-- Modal criar/editar -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="form.id ? t('inbox.respostas_rapidas.editar') : t('inbox.respostas_rapidas.nova')"
            >
                <div class="w-full max-w-md rounded-xl bg-surface shadow-xl">
                    <div class="flex items-center justify-between border-b border-border px-6 py-4">
                        <h2 class="text-base font-semibold text-foreground">
                            {{ form.id ? t('inbox.respostas_rapidas.editar') : t('inbox.respostas_rapidas.nova') }}
                        </h2>
                        <button
                            type="button"
                            class="rounded p-1 text-foreground-muted hover:text-foreground"
                            :aria-label="t('common.fechar')"
                            @click="closeModal"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        <!-- Escopo (apenas na criação) -->
                        <div v-if="!form.id">
                            <label class="mb-1 block text-sm font-medium text-foreground">
                                {{ t('inbox.respostas_rapidas.escopo') }}
                            </label>
                            <div class="flex gap-3">
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input v-model="form.scope" type="radio" value="private" class="accent-primary-600" />
                                    {{ t('inbox.respostas_rapidas.escopo_privada') }}
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input v-model="form.scope" type="radio" value="tenant" class="accent-primary-600" />
                                    {{ t('inbox.respostas_rapidas.escopo_equipe') }}
                                </label>
                            </div>
                        </div>

                        <!-- Atalho -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-foreground" for="qr-shortcut">
                                {{ t('inbox.respostas_rapidas.atalho') }}
                            </label>
                            <input
                                id="qr-shortcut"
                                v-model="form.shortcut"
                                type="text"
                                placeholder="/exemplo"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground font-mono placeholder-foreground-muted outline-none focus:border-primary-400"
                            />
                        </div>

                        <!-- Conteúdo -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-foreground" for="qr-content">
                                {{ t('inbox.respostas_rapidas.conteudo') }}
                            </label>
                            <textarea
                                id="qr-content"
                                v-model="form.content"
                                rows="4"
                                :placeholder="t('inbox.respostas_rapidas.conteudo_placeholder')"
                                class="w-full resize-none rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder-foreground-muted outline-none focus:border-primary-400"
                            ></textarea>
                            <p class="mt-1 text-xs text-foreground-muted">
                                {{ t('inbox.respostas_rapidas.variaveis_disponiveis') }}:
                                <code class="rounded bg-surface-raised px-1">{nome_paciente}</code>,
                                <code class="rounded bg-surface-raised px-1">{nome_clinica}</code>,
                                <code class="rounded bg-surface-raised px-1">{nome_atendente}</code>
                            </p>
                        </div>

                        <!-- Erro -->
                        <p v-if="formError" role="alert" class="text-sm text-danger-700">{{ formError }}</p>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-raised"
                            @click="closeModal"
                        >
                            {{ t('common.cancelar') }}
                        </button>
                        <button
                            type="button"
                            :disabled="saving"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800 disabled:opacity-60"
                            @click="saveForm"
                        >
                            {{ saving ? t('common.salvando') : t('common.salvar') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Confirmar exclusão -->
        <Teleport to="body">
            <div
                v-if="confirmDeleteId"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                role="alertdialog"
                aria-modal="true"
            >
                <div class="w-full max-w-sm rounded-xl bg-surface p-6 shadow-xl">
                    <h3 class="mb-2 text-base font-semibold text-foreground">
                        {{ t('inbox.respostas_rapidas.confirmar_excluir') }}
                    </h3>
                    <p class="mb-6 text-sm text-foreground-muted">
                        {{ t('inbox.respostas_rapidas.confirmar_excluir_descricao') }}
                    </p>
                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-raised"
                            @click="confirmDeleteId = null"
                        >
                            {{ t('common.cancelar') }}
                        </button>
                        <button
                            type="button"
                            :disabled="deleting"
                            class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-medium text-white hover:bg-danger-700 disabled:opacity-60"
                            @click="doDelete"
                        >
                            {{ deleting ? t('common.excluindo') : t('common.excluir') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
