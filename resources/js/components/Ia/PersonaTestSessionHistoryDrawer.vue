<script setup>
import { onMounted, ref, watch } from 'vue';
import { useIaStore } from '@/stores/ia.js';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';

/**
 * **T191 (Fase 18 — US6, FR-043)** — drawer lateral com sessões de teste
 * sandbox do admin autenticado.
 *
 * FR-043: isolamento por admin — o backend já filtra por `admin_user_id`;
 * o frontend confia no escopo e apenas exibe.
 *
 * Ações:
 *  - arquivar uma sessão `closed` (transição para `archived`);
 *  - recarregar lista.
 *
 * Não há "abrir sessão arquivada" no MVP: cada teste reabre uma sessão nova
 * (a anterior fica de histórico para auditoria pessoal do admin).
 */
const props = defineProps({
    open: { type: Boolean, required: true },
});

const emit = defineEmits(['close']);

const store = useIaStore();
const drawerEl = ref(null);
const activeRef = ref(false);
const archiving = ref(null); // id sendo arquivado

watch(
    () => props.open,
    async (next) => {
        activeRef.value = next;
        if (next) {
            await store.listPersonaTestSessions();
        }
    },
    { immediate: false },
);

onMounted(async () => {
    if (props.open) {
        activeRef.value = true;
        await store.listPersonaTestSessions();
    }
});

useShellFocusTrap(drawerEl, activeRef);

async function refresh() {
    await store.listPersonaTestSessions();
}

async function archive(session) {
    archiving.value = session.id;
    try {
        await store.archivePersonaTestSession(session.id);
    } finally {
        archiving.value = null;
    }
}

function close() {
    emit('close');
}

function statusLabel(status) {
    return (
        {
            open: 'Aberta',
            closed: 'Encerrada',
            archived: 'Arquivada',
        }[status] ?? status
    );
}

function statusClasses(status) {
    return (
        {
            open: 'bg-success-100 text-success-700',
            closed: 'bg-surface-muted text-foreground-muted',
            archived: 'bg-amber-100 text-amber-800',
        }[status] ?? 'bg-surface-muted text-foreground-muted'
    );
}

function formatDateTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] bg-black/40"
            role="dialog"
            aria-modal="true"
            aria-labelledby="persona-test-history-title"
            @click.self="close"
            @keydown.esc="close"
        >
            <aside
                ref="drawerEl"
                class="ml-auto flex h-full w-full max-w-md flex-col bg-white shadow-xl"
            >
                <header
                    class="flex items-start justify-between gap-3 border-b border-border px-5 py-4"
                >
                    <div>
                        <h2
                            id="persona-test-history-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Histórico de testes
                        </h2>
                        <p class="mt-0.5 text-xs text-foreground-muted">
                            Suas últimas sessões sandbox (até 50). Visível só para você.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-foreground-muted hover:bg-surface-muted"
                        aria-label="Fechar histórico"
                        @click="close"
                    >
                        ✕
                    </button>
                </header>

                <div class="flex items-center justify-between px-5 py-3 text-xs">
                    <span class="text-foreground-muted"
                        >{{ store.personaTestSessions.length }} sessões</span
                    >
                    <button
                        type="button"
                        class="font-medium text-primary-600 hover:underline"
                        @click="refresh"
                    >
                        Atualizar
                    </button>
                </div>

                <section class="flex-1 divide-y divide-border overflow-y-auto">
                    <div
                        v-if="store.loading"
                        class="px-5 py-12 text-center text-sm text-foreground-muted"
                    >
                        Carregando…
                    </div>
                    <div
                        v-else-if="store.personaTestSessions.length === 0"
                        class="px-5 py-12 text-center text-sm text-foreground-muted"
                    >
                        Nenhuma sessão sandbox ainda.
                    </div>
                    <article
                        v-for="session in store.personaTestSessions"
                        v-else
                        :key="session.id"
                        class="space-y-2 px-5 py-3 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusClasses(session.status)"
                                >{{ statusLabel(session.status) }}</span
                            >
                            <span class="text-xs text-foreground-muted">{{
                                formatDateTime(session.created_at)
                            }}</span>
                        </div>
                        <div class="text-xs text-foreground-muted">
                            <span>Persona #{{ session.persona_id }}</span>
                            <span v-if="session.closed_at" class="ml-2"
                                >· encerrada {{ formatDateTime(session.closed_at) }}</span
                            >
                        </div>
                        <div v-if="session.status === 'closed'" class="pt-1">
                            <button
                                type="button"
                                :disabled="archiving === session.id"
                                class="text-xs font-medium text-primary-600 hover:underline disabled:opacity-50"
                                @click="archive(session)"
                            >
                                {{ archiving === session.id ? 'Arquivando…' : 'Arquivar' }}
                            </button>
                        </div>
                    </article>
                </section>
            </aside>
        </div>
    </Teleport>
</template>
