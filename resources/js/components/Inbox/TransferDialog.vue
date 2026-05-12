<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';

const { t } = useI18n();
const store = useInboxStore();

const props = defineProps({
    conversationId: {
        type: [String, Number],
        required: true,
    },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['transferred']);

// ─── Estado ───────────────────────────────────────────────────────────────────

const activeTab = ref('usuario'); // 'usuario' | 'perfil'
const selectedUserId = ref(null);
const selectedRole = ref(null);
const transferNote = ref('');
const loading = ref(false);
const validationErrors = ref({});

const toastMessage = ref(null);
const toastType = ref('success');
let toastTimer = null;

// ─── Constantes ───────────────────────────────────────────────────────────────

const NOTE_MAX = 500;
const NOTE_MIN = 10;

const ROLES = [
    { value: 'medico', label: t('inbox.regras.perfis.medico') },
    { value: 'atendente', label: t('inbox.regras.perfis.atendente') },
    { value: 'recepcionista', label: t('inbox.regras.perfis.recepcionista') },
    { value: 'admin-clinica', label: t('inbox.regras.perfis.admin-clinica') },
];

// ─── Computed ─────────────────────────────────────────────────────────────────

const users = computed(() => store.assignableUsers);
const loadingUsers = computed(() => store.loadingAssignableUsers);

const noteLength = computed(() => transferNote.value.length);
const noteOverLimit = computed(() => noteLength.value > NOTE_MAX);
const noteUnderMin = computed(() => noteLength.value > 0 && noteLength.value < NOTE_MIN);

const targetSelected = computed(() => {
    if (activeTab.value === 'usuario') { return !!selectedUserId.value; }
    return !!selectedRole.value;
});

const canSubmit = computed(() => {
    return (
        !loading.value
        && targetSelected.value
        && noteLength.value >= NOTE_MIN
        && !noteOverLimit.value
    );
});

// ─── Watchers ─────────────────────────────────────────────────────────────────

watch(open, async (isOpen) => {
    if (isOpen) {
        resetForm();
        if (users.value.length === 0) {
            await store.loadAssignableUsers();
        }
    }
});

watch(activeTab, () => {
    selectedUserId.value = null;
    selectedRole.value = null;
    validationErrors.value = {};
});

watch(transferNote, () => {
    if (validationErrors.value.transfer_note) {
        delete validationErrors.value.transfer_note;
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function resetForm() {
    activeTab.value = 'usuario';
    selectedUserId.value = null;
    selectedRole.value = null;
    transferNote.value = '';
    loading.value = false;
    validationErrors.value = {};
}

function showToast(message, type = 'success') {
    toastMessage.value = message;
    toastType.value = type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastMessage.value = null;
    }, 4000);
}

function close() {
    if (!loading.value) { open.value = false; }
}

function onKeydown(event) {
    if (event.key === 'Escape') { close(); }
}

function roleLabel(roles) {
    const map = {
        'admin-clinica': t('inbox.regras.perfis.admin-clinica'),
        medico: t('inbox.regras.perfis.medico'),
        atendente: t('inbox.regras.perfis.atendente'),
        recepcionista: t('inbox.regras.perfis.recepcionista'),
    };
    const first = (roles ?? [])[0];
    return first ? (map[first] ?? first) : '';
}

// ─── Submit ───────────────────────────────────────────────────────────────────

async function submit() {
    if (!canSubmit.value) { return; }

    validationErrors.value = {};
    loading.value = true;

    try {
        const payload = { transfer_note: transferNote.value };
        if (activeTab.value === 'usuario') {
            payload.user_id = selectedUserId.value;
        } else {
            payload.role = selectedRole.value;
        }

        const result = await store.transfer(props.conversationId, payload);
        showToast(t('inbox.transferir.sucesso'), 'success');
        emit('transferred', result);
        open.value = false;
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 422) {
            if (body?.errors) {
                validationErrors.value = body.errors;
            } else if (body?.code === 'cross_tenant') {
                showToast(t('common.error_forbidden'), 'error');
            } else {
                showToast(body?.message ?? t('common.error_generic'), 'error');
            }
        } else if (status === 403) {
            showToast(t('common.error_forbidden'), 'error');
        } else {
            showToast(t('common.error_generic'), 'error');
        }
    } finally {
        loading.value = false;
    }
}

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? null;
}
</script>

<template>
    <Teleport to="body">
        <!-- Toast global -->
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

        <!-- Modal overlay -->
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="transfer-dialog-title"
            @click.self="close"
            @keydown="onKeydown"
        >
            <div class="w-full max-w-md rounded-xl border border-border bg-surface-elevated shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 id="transfer-dialog-title" class="text-base font-semibold text-foreground">
                        {{ t('inbox.transferir.titulo') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :aria-label="t('common.cancel')"
                        @click="close"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-border px-5" role="tablist">
                    <button
                        role="tab"
                        :aria-selected="activeTab === 'usuario'"
                        class="py-3 px-2 mr-4 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="activeTab === 'usuario'
                            ? 'border-b-2 border-primary-700 text-primary-700'
                            : 'text-foreground-muted hover:text-foreground'"
                        @click="activeTab = 'usuario'"
                    >
                        {{ t('inbox.transferir.para_usuario') }}
                    </button>
                    <button
                        role="tab"
                        :aria-selected="activeTab === 'perfil'"
                        class="py-3 px-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="activeTab === 'perfil'
                            ? 'border-b-2 border-primary-700 text-primary-700'
                            : 'text-foreground-muted hover:text-foreground'"
                        @click="activeTab = 'perfil'"
                    >
                        {{ t('inbox.transferir.para_perfil') }}
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-4">
                    <!-- Tab: Para usuário -->
                    <div
                        v-if="activeTab === 'usuario'"
                        role="tabpanel"
                        aria-label="Para usuário"
                    >
                        <div class="max-h-44 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                            <!-- Loading -->
                            <div
                                v-if="loadingUsers"
                                class="flex items-center justify-center py-6"
                                aria-live="polite"
                                aria-busy="true"
                            >
                                <svg class="h-5 w-5 animate-spin text-primary-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>

                            <div
                                v-else-if="users.length === 0"
                                class="py-6 text-center text-sm text-foreground-muted"
                            >
                                {{ t('common.no_results') }}
                            </div>

                            <template v-else>
                                <button
                                    v-for="user in users"
                                    :key="user.id"
                                    type="button"
                                    :disabled="loading"
                                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    :class="selectedUserId === user.id
                                        ? 'bg-primary-50'
                                        : 'hover:bg-surface'"
                                    @click="selectedUserId = user.id"
                                >
                                    <!-- Avatar -->
                                    <div
                                        class="h-8 w-8 shrink-0 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-semibold"
                                        aria-hidden="true"
                                    >
                                        {{ (user.name ?? '?').slice(0, 2).toUpperCase() }}
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-foreground truncate">{{ user.name }}</p>
                                        <p class="text-xs text-foreground-muted">{{ roleLabel(user.roles) }}</p>
                                    </div>

                                    <!-- Check selecionado -->
                                    <svg
                                        v-if="selectedUserId === user.id"
                                        class="h-4 w-4 shrink-0 text-primary-700"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>
                        </div>

                        <p v-if="fieldError('user_id')" role="alert" class="mt-1 text-xs text-danger-600">
                            {{ fieldError('user_id') }}
                        </p>
                    </div>

                    <!-- Tab: Para perfil -->
                    <div
                        v-if="activeTab === 'perfil'"
                        role="tabpanel"
                        aria-label="Para perfil"
                    >
                        <p class="mb-3 text-xs text-foreground-muted italic">
                            {{ t('inbox.transferir.primeiro_online') }}
                        </p>
                        <fieldset class="space-y-2">
                            <legend class="sr-only">{{ t('inbox.transferir.para_perfil') }}</legend>
                            <label
                                v-for="role in ROLES"
                                :key="role.value"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition"
                                :class="selectedRole === role.value
                                    ? 'border-primary-400 bg-primary-50'
                                    : 'border-border hover:bg-surface'"
                            >
                                <input
                                    type="radio"
                                    name="transfer-role"
                                    :value="role.value"
                                    v-model="selectedRole"
                                    class="h-4 w-4 border-border text-primary-600 accent-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                />
                                <span class="text-sm font-medium text-foreground">{{ role.label }}</span>
                            </label>
                        </fieldset>

                        <p v-if="fieldError('role')" role="alert" class="mt-1 text-xs text-danger-600">
                            {{ fieldError('role') }}
                        </p>
                    </div>

                    <!-- Nota interna (obrigatória) -->
                    <div>
                        <label for="transfer-note" class="mb-1.5 block text-sm font-medium text-foreground">
                            {{ t('inbox.transferir.nota_interna') }}
                            <span class="text-danger-600 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <textarea
                            id="transfer-note"
                            v-model="transferNote"
                            rows="3"
                            :disabled="loading"
                            :maxlength="NOTE_MAX"
                            :placeholder="t('inbox.transferir.nota_placeholder')"
                            :aria-invalid="noteUnderMin || !!fieldError('transfer_note')"
                            :aria-describedby="noteUnderMin ? 'note-error' : undefined"
                            class="w-full resize-none rounded-lg border px-3 py-2.5 text-sm text-foreground placeholder-foreground-muted outline-none transition"
                            :class="(noteUnderMin || fieldError('transfer_note'))
                                ? 'border-danger-500 focus:border-danger-500 focus:ring-2 focus:ring-danger-200'
                                : 'border-border focus:border-primary-500 focus:ring-2 focus:ring-primary-200'"
                        ></textarea>

                        <!-- Rodapé: erro + contador -->
                        <div class="flex items-center justify-between mt-1 px-0.5">
                            <div class="flex-1">
                                <p
                                    v-if="noteUnderMin"
                                    id="note-error"
                                    role="alert"
                                    class="text-xs text-danger-600"
                                >
                                    {{ t('inbox.transferir.nota_min') }}
                                </p>
                                <p
                                    v-else-if="fieldError('transfer_note')"
                                    role="alert"
                                    class="text-xs text-danger-600"
                                >
                                    {{ fieldError('transfer_note') }}
                                </p>
                            </div>
                            <span
                                class="text-xs"
                                :class="noteOverLimit ? 'text-danger-600 font-semibold' : 'text-foreground-muted'"
                            >
                                {{ t('inbox.transferir.nota_chars', { used: noteLength }) }}
                            </span>
                        </div>
                    </div>

                    <!-- Ações -->
                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            :disabled="loading"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60"
                            @click="close"
                        >
                            {{ t('inbox.regras.cancelar') }}
                        </button>
                        <button
                            type="button"
                            :disabled="!canSubmit"
                            class="flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="submit"
                        >
                            <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ t('inbox.transferir.submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
