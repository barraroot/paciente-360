<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';
import { useInboxStore } from '@/stores/inbox.js';
import { useCanaisStore } from '@/stores/canais.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

const { t } = useI18n();
const store = useInboxStore();
const canaisStore = useCanaisStore();

// ─── Canais para dropdown ─────────────────────────────────────────────────────

const channels = computed(() => canaisStore.channels ?? []);

async function loadChannels() {
    try {
        await canaisStore.loadChannels();
    } catch {
        // Canais são opcionais na listagem — ignora erro
    }
}

// ─── Regras ───────────────────────────────────────────────────────────────────

const rules = ref([]);
const loadingRules = computed(() => store.loadingAssignmentRules);
const savingRules = computed(() => store.savingAssignmentRules);

let saveDebounce = null;

async function loadRules() {
    try {
        await store.loadAssignmentRules();
        rules.value = store.assignmentRules.map((r, idx) => ({
            ...r,
            priority: idx + 1,
            _configRaw: r.config ? JSON.stringify(r.config, null, 2) : '{}',
        }));
    } catch {
        saveError.value = t('common.error_generic');
    }
}

async function saveRules() {
    clearTimeout(saveDebounce);

    const payload = rules.value.map((r, idx) => ({
        id: r.id ?? undefined,
        strategy: r.strategy,
        channel_id: r.channel_id ?? null,
        is_active: r.is_active,
        priority: idx + 1,
        config: parseConfig(r._configRaw),
    }));

    try {
        await store.updateAssignmentRules(payload);
        showToast(t('inbox.regras.salvas'), 'success');
    } catch {
        showToast(t('common.error_generic'), 'error');
    }
}

function debouncedSave() {
    clearTimeout(saveDebounce);
    saveDebounce = setTimeout(() => saveRules(), 1000);
}

function parseConfig(raw) {
    try {
        return JSON.parse(raw ?? '{}');
    } catch {
        return {};
    }
}

function isConfigValid(raw) {
    try {
        JSON.parse(raw ?? '{}');
        return true;
    } catch {
        return false;
    }
}

// ─── Toast ────────────────────────────────────────────────────────────────────

const toastMessage = ref(null);
const toastType = ref('success');
let toastTimer = null;
const saveError = ref(null);

function showToast(msg, type = 'success') {
    toastMessage.value = msg;
    toastType.value = type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastMessage.value = null; }, 4000);
}

// ─── Modal criar/editar ───────────────────────────────────────────────────────

const STRATEGIES = [
    {
        value: 'round_robin',
        label: t('inbox.regras.estrategia.round_robin'),
        help: 'Distribui em sequência circular entre atendentes disponíveis.',
    },
    {
        value: 'patient_owner',
        label: t('inbox.regras.estrategia.patient_owner'),
        help: 'Atribui ao profissional responsável vinculado ao paciente.',
    },
    {
        value: 'manual',
        label: t('inbox.regras.estrategia.manual'),
        help: 'Nenhuma atribuição automática — cai na fila sem atendente.',
    },
];

const editModal = ref({
    open: false,
    isNew: false,
    index: null,
    rule: null,
});

function openCreateModal() {
    editModal.value = {
        open: true,
        isNew: true,
        index: null,
        rule: {
            strategy: 'round_robin',
            channel_id: null,
            is_active: true,
            _configRaw: '{}',
        },
    };
}

function openEditModal(idx) {
    const r = rules.value[idx];
    editModal.value = {
        open: true,
        isNew: false,
        index: idx,
        rule: { ...r },
    };
}

function closeEditModal() {
    editModal.value.open = false;
}

const editConfigError = computed(() => {
    if (!editModal.value.rule) { return null; }
    return isConfigValid(editModal.value.rule._configRaw)
        ? null
        : t('inbox.regras.config_json_invalid');
});

function saveEditModal() {
    if (editConfigError.value) { return; }

    if (editModal.value.isNew) {
        rules.value.push({ ...editModal.value.rule, id: null });
    } else {
        rules.value[editModal.value.index] = { ...editModal.value.rule };
    }

    closeEditModal();
    debouncedSave();
}

// ─── Excluir ──────────────────────────────────────────────────────────────────

const deleteModal = ref({ open: false, index: null });

function openDeleteModal(idx) {
    deleteModal.value = { open: true, index: idx };
}

function closeDeleteModal() {
    deleteModal.value.open = false;
}

function confirmDelete() {
    rules.value.splice(deleteModal.value.index, 1);
    closeDeleteModal();
    debouncedSave();
}

// ─── Toggle ativo ─────────────────────────────────────────────────────────────

function toggleActive(idx) {
    rules.value[idx].is_active = !rules.value[idx].is_active;
    debouncedSave();
}

// ─── Drag end ─────────────────────────────────────────────────────────────────

function onDragEnd() {
    debouncedSave();
}

// ─── Estratégia label/badge ───────────────────────────────────────────────────

function strategyBadge(strategy) {
    const map = {
        round_robin: { label: t('inbox.regras.estrategia.round_robin'), cls: 'bg-primary-50 text-primary-700' },
        patient_owner: { label: t('inbox.regras.estrategia.patient_owner'), cls: 'bg-success-50 text-success-700' },
        manual: { label: t('inbox.regras.estrategia.manual'), cls: 'bg-surface border border-border text-foreground-muted' },
    };
    return map[strategy] ?? { label: strategy, cls: 'bg-surface border border-border text-foreground-muted' };
}

function channelLabel(channelId) {
    if (!channelId) { return t('inbox.regras.todos_canais'); }
    const ch = channels.value.find((c) => String(c.id) === String(channelId));
    return ch?.name ?? `Canal #${channelId}`;
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
    await Promise.all([loadRules(), loadChannels()]);
});
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-3xl">

            <!-- Toast -->
            <div
                v-if="toastMessage"
                role="alert"
                aria-live="assertive"
                class="fixed bottom-5 right-5 z-[200] flex items-center gap-2 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium max-w-xs"
                :class="toastType === 'success'
                    ? 'border-success-300 bg-success-50 text-success-800'
                    : 'border-danger-300 bg-danger-50 text-danger-800'"
            >
                <svg v-if="toastType === 'success'" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ toastMessage }}
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="Breadcrumb" class="mb-4 flex items-center gap-1.5 text-xs text-foreground-muted">
                <a href="/panel/inbox" class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded">
                    {{ t('inbox.titulo') }}
                </a>
                <span aria-hidden="true">›</span>
                <span class="text-foreground">{{ t('inbox.regras.titulo') }}</span>
            </nav>

            <!-- Header -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ t('inbox.regras.titulo') }}</h1>
                    <p class="mt-1 text-sm text-foreground-muted">{{ t('inbox.regras.descricao') }}</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <!-- Indicador salvando -->
                    <span
                        v-if="savingRules"
                        class="text-xs text-foreground-muted flex items-center gap-1.5"
                        role="status"
                        aria-live="polite"
                    >
                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ t('inbox.regras.salvando') }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="openCreateModal"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        {{ t('inbox.regras.adicionar') }}
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div
                v-if="loadingRules"
                class="py-12 flex items-center justify-center"
                aria-live="polite"
                aria-busy="true"
            >
                <svg class="h-6 w-6 animate-spin text-primary-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="rules.length === 0"
                class="flex flex-col items-center gap-4 rounded-xl border border-border bg-surface-elevated py-14 px-8 text-center"
                role="status"
            >
                <svg class="h-10 w-10 text-foreground-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" />
                </svg>
                <p class="text-sm text-foreground-muted max-w-sm">{{ t('inbox.regras.vazio') }}</p>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="openCreateModal"
                >
                    {{ t('inbox.regras.adicionar_primeira') }}
                </button>
            </div>

            <!-- Lista de regras com drag-and-drop -->
            <draggable
                v-else
                v-model="rules"
                item-key="id"
                handle=".drag-handle"
                ghost-class="opacity-40"
                class="space-y-2"
                @end="onDragEnd"
            >
                <template #item="{ element: rule, index: idx }">
                    <div
                        class="flex items-start gap-3 rounded-xl border border-border bg-surface-elevated p-4 shadow-sm transition"
                        :class="rule.is_active ? '' : 'opacity-60'"
                    >
                        <!-- Drag handle -->
                        <button
                            type="button"
                            class="drag-handle mt-0.5 flex h-8 w-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-foreground-muted transition hover:bg-surface active:cursor-grabbing focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            :aria-label="`Reordenar regra ${idx + 1}`"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 6zm0-6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 0zm0 12a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 12z" />
                            </svg>
                        </button>

                        <!-- Conteúdo -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <!-- Priority -->
                                <span class="text-xs font-semibold text-foreground-muted tabular-nums w-5 text-right shrink-0">
                                    {{ idx + 1 }}.
                                </span>

                                <!-- Strategy badge -->
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="strategyBadge(rule.strategy).cls"
                                >
                                    {{ strategyBadge(rule.strategy).label }}
                                </span>

                                <!-- Canal -->
                                <span class="text-xs text-foreground-muted">
                                    {{ channelLabel(rule.channel_id) }}
                                </span>

                                <!-- Ativa badge -->
                                <span
                                    v-if="rule.is_active"
                                    class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700"
                                >
                                    {{ t('inbox.regras.ativa') }}
                                </span>
                            </div>
                        </div>

                        <!-- Ações -->
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Toggle is_active -->
                            <button
                                type="button"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                :class="rule.is_active ? 'bg-primary-600' : 'bg-border'"
                                role="switch"
                                :aria-checked="rule.is_active"
                                :aria-label="`${t('inbox.regras.ativa')} ${idx + 1}`"
                                @click="toggleActive(idx)"
                            >
                                <span
                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="rule.is_active ? 'translate-x-4' : 'translate-x-0'"
                                    aria-hidden="true"
                                ></span>
                            </button>

                            <button
                                type="button"
                                class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openEditModal(idx)"
                            >
                                {{ t('inbox.regras.editar') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-danger-300 px-3 py-1.5 text-xs font-medium text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openDeleteModal(idx)"
                            >
                                {{ t('inbox.regras.excluir') }}
                            </button>
                        </div>
                    </div>
                </template>
            </draggable>
        </div>

        <!-- ─── Modal criar/editar regra ─────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="editModal.open"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="edit-rule-title"
                @click.self="closeEditModal"
                @keydown.escape="closeEditModal"
            >
                <div class="w-full max-w-md rounded-xl border border-border bg-surface-elevated shadow-xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-border px-5 py-4">
                        <h2 id="edit-rule-title" class="text-base font-semibold text-foreground">
                            {{ editModal.isNew ? t('inbox.regras.adicionar') : t('inbox.regras.editar') }}
                        </h2>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            :aria-label="t('inbox.regras.cancelar')"
                            @click="closeEditModal"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <div class="p-5 space-y-4">
                        <!-- Estratégia -->
                        <div>
                            <label for="rule-strategy" class="mb-1.5 block text-sm font-medium text-foreground">
                                Estratégia
                            </label>
                            <select
                                id="rule-strategy"
                                v-model="editModal.rule.strategy"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            >
                                <option
                                    v-for="s in STRATEGIES"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                            <p
                                v-if="STRATEGIES.find((s) => s.value === editModal.rule.strategy)"
                                class="mt-1 text-xs text-foreground-muted"
                            >
                                {{ STRATEGIES.find((s) => s.value === editModal.rule.strategy)?.help }}
                            </p>
                        </div>

                        <!-- Canal -->
                        <div>
                            <label for="rule-channel" class="mb-1.5 block text-sm font-medium text-foreground">
                                Canal
                            </label>
                            <select
                                id="rule-channel"
                                v-model="editModal.rule.channel_id"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            >
                                <option :value="null">{{ t('inbox.regras.todos_canais') }}</option>
                                <option
                                    v-for="ch in channels"
                                    :key="ch.id"
                                    :value="ch.id"
                                >
                                    {{ ch.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Config JSON -->
                        <div>
                            <label for="rule-config" class="mb-1.5 block text-sm font-medium text-foreground">
                                {{ t('inbox.regras.config_json') }}
                            </label>
                            <textarea
                                id="rule-config"
                                v-model="editModal.rule._configRaw"
                                rows="4"
                                spellcheck="false"
                                class="w-full resize-none rounded-lg border px-3 py-2.5 font-mono text-xs text-foreground outline-none transition"
                                :class="editConfigError
                                    ? 'border-danger-500 focus:border-danger-500 focus:ring-2 focus:ring-danger-200'
                                    : 'border-border focus:border-primary-500 focus:ring-2 focus:ring-primary-200'"
                            ></textarea>
                            <p v-if="editConfigError" role="alert" class="mt-1 text-xs text-danger-600">
                                {{ editConfigError }}
                            </p>
                        </div>

                        <!-- Toggle is_active -->
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                :class="editModal.rule.is_active ? 'bg-primary-600' : 'bg-border'"
                                role="switch"
                                :aria-checked="editModal.rule.is_active"
                                aria-label="Regra ativa"
                                @click="editModal.rule.is_active = !editModal.rule.is_active"
                            >
                                <span
                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="editModal.rule.is_active ? 'translate-x-4' : 'translate-x-0'"
                                    aria-hidden="true"
                                ></span>
                            </button>
                            <span class="text-sm text-foreground">{{ t('inbox.regras.ativa') }}</span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 border-t border-border px-5 py-4">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="closeEditModal"
                        >
                            {{ t('inbox.regras.cancelar') }}
                        </button>
                        <button
                            type="button"
                            :disabled="!!editConfigError"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="saveEditModal"
                        >
                            {{ t('inbox.regras.salvar') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal confirmar exclusão ──────────────────────────────────── -->
        <ConfirmModal
            :open="deleteModal.open"
            :title="t('inbox.regras.excluir')"
            @close="closeDeleteModal"
            @confirm="confirmDelete"
        >
            <p class="text-sm text-foreground-muted">
                Esta regra será removida permanentemente. A ordem das demais regras será atualizada.
            </p>
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="confirmDelete"
                >
                    {{ t('inbox.regras.excluir') }}
                </button>
            </template>
        </ConfirmModal>
    </main>
</template>
