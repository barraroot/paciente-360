<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';
import { formatRelative, formatDateTime } from '@/composables/useI18nFormat.js';

const { t } = useI18n();
const store = useInboxStore();

const props = defineProps({
    conversationId: {
        type: [String, Number],
        required: true,
    },
});

const open = defineModel({ type: Boolean, default: false });

// ─── Estado ───────────────────────────────────────────────────────────────────

const error = ref(null);
const expandedNotes = ref(new Set());

// ─── Data ─────────────────────────────────────────────────────────────────────

const assignments = computed(
    () => store.assignmentsByConversationId[String(props.conversationId)] ?? [],
);
const loading = computed(() => store.loadingAssignments);

async function loadHistory() {
    error.value = null;
    try {
        await store.loadAssignments(props.conversationId);
    } catch {
        error.value = t('inbox.historico.erro');
    }
}

watch(open, (isOpen) => {
    if (isOpen) { loadHistory(); }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function close() {
    open.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') { close(); }
}

function toggleNote(id) {
    const s = new Set(expandedNotes.value);
    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }
    expandedNotes.value = s;
}

function isNoteExpanded(id) {
    return expandedNotes.value.has(id);
}

function userName(assignment) {
    return assignment.user?.name ?? t('inbox.historico.sem_atendente');
}

function assignedByName(assignment) {
    if (!assignment.assigned_by_id) { return t('inbox.historico.sistema'); }
    return assignment.assigned_by?.name ?? t('inbox.historico.sistema');
}

function assignmentTypeBadge(type) {
    const map = {
        inicial: { label: t('inbox.historico.tipo.inicial'), cls: 'bg-surface-elevated border border-border text-foreground-muted' },
        manual: { label: t('inbox.historico.tipo.manual'), cls: 'bg-primary-50 text-primary-700' },
        transferencia: { label: t('inbox.historico.tipo.transferencia'), cls: 'bg-warning-50 text-warning-700' },
        reassign_offline: { label: t('inbox.historico.tipo.reassign_offline'), cls: 'bg-danger-50 text-danger-700' },
        auto_atribuicao: { label: t('inbox.historico.tipo.auto_atribuicao'), cls: 'bg-success-50 text-success-700' },
    };
    return map[type] ?? { label: type, cls: 'bg-surface border border-border text-foreground-muted' };
}

function timeLabel(isoDate) {
    if (!isoDate) { return '—'; }
    try {
        return formatRelative(isoDate);
    } catch {
        return formatDateTime(isoDate);
    }
}

function userInitials(assignment) {
    const name = assignment.user?.name;
    if (!name) { return '?' }
    return name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <transition name="backdrop">
            <div
                v-if="open"
                class="fixed inset-0 z-[90] bg-black/40"
                aria-hidden="true"
                @click="close"
            ></div>
        </transition>

        <!-- Drawer lateral -->
        <transition name="drawer">
            <div
                v-if="open"
                class="fixed inset-y-0 right-0 z-[95] flex w-full max-w-sm flex-col border-l border-border bg-surface-elevated shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="history-drawer-title"
                @keydown="onKeydown"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border px-5 py-4 shrink-0">
                    <h2 id="history-drawer-title" class="text-base font-semibold text-foreground">
                        {{ t('inbox.historico.titulo') }}
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

                <!-- Body scrollável -->
                <div class="flex-1 overflow-y-auto px-5 py-4">

                    <!-- Loading skeleton -->
                    <div v-if="loading" aria-busy="true" aria-live="polite" class="space-y-5">
                        <div
                            v-for="n in 3"
                            :key="n"
                            class="flex gap-3 animate-pulse"
                            aria-hidden="true"
                        >
                            <div class="flex flex-col items-center gap-1">
                                <div class="h-9 w-9 rounded-full bg-surface shrink-0"></div>
                                <div v-if="n < 3" class="h-12 w-px bg-surface"></div>
                            </div>
                            <div class="flex-1 space-y-2 pb-4">
                                <div class="h-3.5 w-1/3 rounded bg-surface"></div>
                                <div class="h-3 w-2/3 rounded bg-surface"></div>
                                <div class="h-3 w-1/2 rounded bg-surface"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Erro -->
                    <div
                        v-else-if="error"
                        role="alert"
                        aria-live="assertive"
                        class="flex flex-col items-center gap-3 py-10 text-center"
                    >
                        <svg class="h-8 w-8 text-danger-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <p class="text-sm text-danger-700">{{ error }}</p>
                        <button
                            type="button"
                            class="text-xs text-primary-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="loadHistory"
                        >
                            Tentar novamente
                        </button>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-else-if="assignments.length === 0"
                        class="flex flex-col items-center gap-3 py-12 text-center"
                        role="status"
                    >
                        <svg class="h-8 w-8 text-foreground-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <p class="text-sm text-foreground-muted">{{ t('inbox.historico.vazio') }}</p>
                    </div>

                    <!-- Timeline de atribuições -->
                    <ol
                        v-else
                        aria-label="Histórico de atribuições"
                        class="space-y-0"
                    >
                        <li
                            v-for="(assignment, idx) in assignments"
                            :key="assignment.id"
                            class="flex gap-3"
                        >
                            <!-- Coluna: avatar + linha conectora -->
                            <div class="flex flex-col items-center" aria-hidden="true">
                                <div
                                    class="h-9 w-9 shrink-0 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-semibold"
                                >
                                    {{ userInitials(assignment) }}
                                </div>
                                <!-- Linha conectora (exceto último) -->
                                <div
                                    v-if="idx < assignments.length - 1"
                                    class="mt-1 flex-1 w-px bg-border min-h-8"
                                ></div>
                            </div>

                            <!-- Conteúdo do item -->
                            <div class="flex-1 pb-5">
                                <!-- Nome + badge tipo -->
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-foreground">
                                        {{ userName(assignment) }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="assignmentTypeBadge(assignment.assignment_type).cls"
                                    >
                                        {{ assignmentTypeBadge(assignment.assignment_type).label }}
                                    </span>
                                </div>

                                <!-- Atribuído por + tempo -->
                                <p class="text-xs text-foreground-muted mb-2">
                                    {{ t('inbox.historico.por', { nome: assignedByName(assignment) }) }}
                                    <span class="mx-1" aria-hidden="true">&middot;</span>
                                    <time :datetime="assignment.assigned_at" :title="formatDateTime(assignment.assigned_at)">
                                        {{ timeLabel(assignment.assigned_at) }}
                                    </time>
                                </p>

                                <!-- Nota interna colapsável -->
                                <div v-if="assignment.transfer_note" class="mt-1">
                                    <button
                                        type="button"
                                        class="flex items-center gap-1.5 text-xs text-primary-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                        @click="toggleNote(assignment.id)"
                                        :aria-expanded="isNoteExpanded(assignment.id)"
                                    >
                                        <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': isNoteExpanded(assignment.id) }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                        {{ t('inbox.historico.nota_interna') }}
                                    </button>
                                    <div
                                        v-if="isNoteExpanded(assignment.id)"
                                        class="mt-2 rounded-lg border border-border bg-surface px-3 py-2.5 text-xs text-foreground-muted italic"
                                    >
                                        {{ assignment.transfer_note }}
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}

.drawer-enter-active,
.drawer-leave-active {
    transition: transform 0.25s ease;
}
.drawer-enter-from,
.drawer-leave-to {
    transform: translateX(100%);
}
</style>
