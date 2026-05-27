<script setup>
/**
 * T169 — Página de configuração Google Calendar sync (US-6.7).
 *
 * Refinamentos UX (auditoria 2026-05-13 — Lote C):
 *  - Loading skeleton com aria-busy (P0)
 *  - Removido prompt() para professional_id → dropdown de profissional (P0)
 *  - Removido confirm() nativo no disconnect → modal acessível (P0)
 *  - last_synced_at formatado em relativo pt-BR via Luxon (P0)
 *  - Estado "Não conectado" com benefícios + CTA claro (P1)
 *  - Estado "Erro" com CTA "Reconectar" e mensagem específica pt-BR (P1)
 *  - Toast feedback connect/disconnect (P1)
 *  - Placeholder Outlook "Em breve — Fase 6" com ícone disabled claro (clarify nº 11) (P1)
 *  - Mobile: cards empilhados full-width (P1)
 */
import { ref, onMounted, computed } from 'vue';
import { DateTime } from 'luxon';
import api from '@/lib/api';

// ─── Estado principal ─────────────────────────────────────────────────────────

const account = ref(null);
const loading = ref(false);
const error = ref(null);
const professionals = ref([]);
const selectedProfessionalId = ref(null);

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref(null);
let toastTimer = null;

function showToast(message, type = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { message, type };
    toastTimer = setTimeout(() => {
        toast.value = null;
    }, 5000);
}

// ─── Carregamento ─────────────────────────────────────────────────────────────

async function loadProfessionals() {
    try {
        const { data } = await api.get('/users', { params: { only_professionals: 1 } });
        professionals.value = data.data ?? [];
        if (professionals.value.length > 0 && !selectedProfessionalId.value) {
            selectedProfessionalId.value = professionals.value[0].id;
        }
    } catch {
        // Silencioso — profissionais não críticos para exibir o status
    }
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await api.get('/agenda/calendar-sync');
        account.value = data.data;
        if (account.value?.professional_id) {
            selectedProfessionalId.value = account.value.professional_id;
        }
    } catch (e) {
        const status = e?.response?.status;
        if (status === 404) {
            account.value = null; // Nenhuma conta conectada ainda
        } else {
            error.value = 'Não foi possível verificar o status da sincronização. Tente novamente.';
        }
    } finally {
        loading.value = false;
    }
}

// ─── Conectar ─────────────────────────────────────────────────────────────────

const connecting = ref(false);

async function connect() {
    if (!selectedProfessionalId.value) {
        showToast('Selecione um profissional antes de conectar.', 'error');
        return;
    }
    connecting.value = true;
    try {
        const { data } = await api.post('/agenda/calendar-sync/google/connect', {
            professional_id: Number(selectedProfessionalId.value),
            provider: 'google',
        });
        // Em produção: redireciona para URL de autorização OAuth
        if (data.authorize_url) {
            window.location.href = data.authorize_url;
        } else {
            showToast('Redirecionando para o Google...');
        }
    } catch (e) {
        showToast(
            e?.response?.data?.message ??
                'Não foi possível iniciar a conexão com o Google. Tente novamente.',
            'error',
        );
    } finally {
        connecting.value = false;
    }
}

// ─── Modal de confirmação de desconexão ───────────────────────────────────────

const showDisconnectModal = ref(false);
const disconnecting = ref(false);
const disconnectDialogEl = ref(null);

function openDisconnect() {
    showDisconnectModal.value = true;
    // Foca o botão Cancelar (ação segura) ao abrir
    import('vue').then(({ nextTick }) => {
        nextTick(() => {
            disconnectDialogEl.value?.querySelector('button')?.focus();
        });
    });
}

function closeDisconnectModal() {
    showDisconnectModal.value = false;
}

function trapFocus(el, e) {
    if (!el) return;
    const focusable = Array.from(
        el.querySelectorAll('button:not([disabled]), [tabindex]:not([tabindex="-1"])'),
    );
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.key === 'Tab') {
        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }
}

async function confirmDisconnect() {
    if (!account.value || disconnecting.value) return;
    disconnecting.value = true;
    try {
        await api.post(`/agenda/calendar-sync/${account.value.id}/disconnect`);
        closeDisconnectModal();
        showToast('Google Calendar desconectado. Novas mudanças não serão espelhadas.');
        account.value = null;
        await load();
    } catch {
        showToast('Não foi possível desconectar. Tente novamente.', 'error');
    } finally {
        disconnecting.value = false;
    }
}

// ─── Formatação de datas ──────────────────────────────────────────────────────

function formatRelative(isoString) {
    if (!isoString) return null;
    const dt = DateTime.fromISO(isoString, { setZone: true }).setZone('America/Sao_Paulo');
    return dt.toRelative({ locale: 'pt-BR' });
}

function formatWatchExpiry(isoString) {
    if (!isoString) return null;
    const dt = DateTime.fromISO(isoString, { setZone: true }).setZone('America/Sao_Paulo');
    const daysLeft = Math.ceil(dt.diffNow('days').days);
    if (daysLeft <= 0) return 'expirado';
    if (daysLeft === 1) return 'expira amanhã';
    return `expira em ${daysLeft} dias`;
}

// ─── Status de erro legível ───────────────────────────────────────────────────

function humanizeError(account) {
    if (!account?.status_message) return 'Erro de sincronização';
    const msg = account.status_message.toLowerCase();
    if (msg.includes('401') || msg.includes('unauthorized')) {
        return 'Autorização revogada pelo Google — reconecte para retomar a sincronização.';
    }
    if (msg.includes('403') || msg.includes('forbidden')) {
        return 'Permissão negada pelo Google. Reconecte e autorize o acesso ao Google Calendar.';
    }
    if (msg.includes('quota') || msg.includes('429')) {
        return 'Limite de requisições atingido. A sincronização será retomada automaticamente em instantes.';
    }
    return account.status_message;
}

// ─── Watch channel status ─────────────────────────────────────────────────────

const watchStatus = computed(() => {
    if (!account.value?.watch_expires_at) return null;
    return formatWatchExpiry(account.value.watch_expires_at);
});

// ─── Init ─────────────────────────────────────────────────────────────────────

onMounted(async () => {
    await Promise.all([load(), loadProfessionals()]);
});
</script>

<template>
    <div class="p-4 sm:p-6 space-y-6 max-w-2xl">
        <h1 class="text-xl font-semibold text-foreground">Sincronização de Calendário</h1>

        <!-- Toast -->
        <div
            v-if="toast"
            role="alert"
            aria-live="assertive"
            class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg transition"
            :class="
                toast.type === 'success' ? 'bg-success-500 text-white' : 'bg-danger-500 text-white'
            "
        >
            <svg
                v-if="toast.type === 'success'"
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                />
            </svg>
            <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            {{ toast.message }}
        </div>

        <!-- ─── Google Calendar ──────────────────────────────────────────────────── -->
        <section
            class="rounded-lg border border-border bg-surface-elevated shadow-card overflow-hidden"
        >
            <!-- Header da seção -->
            <div class="flex items-center gap-3 border-b border-border px-5 py-4">
                <!-- Ícone Google Calendar (SVG simplificado) -->
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6 shrink-0"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <rect x="3" y="3" width="18" height="18" rx="2" fill="#4285F4" />
                    <rect x="3" y="9" width="18" height="2" fill="#fff" opacity="0.3" />
                    <text
                        x="12"
                        y="17"
                        text-anchor="middle"
                        font-size="7"
                        fill="#fff"
                        font-family="sans-serif"
                        font-weight="bold"
                    >
                        Cal
                    </text>
                </svg>
                <div>
                    <h2 class="text-base font-semibold text-foreground">Google Calendar</h2>
                    <p class="text-xs text-foreground-muted">Espelhe consultas automaticamente</p>
                </div>
            </div>

            <!-- Loading skeleton -->
            <div
                v-if="loading"
                aria-busy="true"
                aria-label="Verificando status da sincronização"
                class="px-5 py-6 space-y-3"
            >
                <div class="h-4 w-2/3 animate-pulse rounded bg-surface-muted" />
                <div class="h-4 w-1/2 animate-pulse rounded bg-surface-muted" />
                <div class="h-8 w-32 animate-pulse rounded-lg bg-surface-muted" />
            </div>

            <!-- Error state -->
            <div
                v-else-if="error"
                class="px-5 py-6 flex flex-col sm:flex-row sm:items-center gap-3"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 shrink-0 text-danger-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-sm text-danger-600 flex-1">{{ error }}</p>
                <button
                    type="button"
                    class="shrink-0 rounded-lg border border-danger-500 px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
                    @click="load"
                >
                    Tentar novamente
                </button>
            </div>

            <!-- Estado: Conectado -->
            <template v-else-if="account && account.status === 'connected'">
                <div class="px-5 py-5 space-y-4">
                    <div class="flex items-start gap-3">
                        <span
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-success-50"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3.5 w-3.5 text-success-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="3"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-foreground">Conectado</p>
                            <p class="text-sm text-foreground-muted truncate">
                                {{ account.provider_email }}
                            </p>
                        </div>
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-foreground-muted shrink-0">Sub-calendário</dt>
                            <dd class="font-mono text-xs text-foreground truncate max-w-[200px]">
                                {{ account.google_calendar_id || '(padrão)' }}
                            </dd>
                        </div>
                        <div
                            v-if="account.last_synced_at"
                            class="flex items-center justify-between gap-2"
                        >
                            <dt class="text-foreground-muted shrink-0">Última sincronização</dt>
                            <dd
                                class="text-foreground"
                                aria-live="polite"
                                :title="account.last_synced_at"
                            >
                                {{ formatRelative(account.last_synced_at) }}
                            </dd>
                        </div>
                        <div v-if="watchStatus" class="flex items-center justify-between gap-2">
                            <dt class="text-foreground-muted shrink-0">Canal de eventos</dt>
                            <dd
                                aria-live="polite"
                                :class="
                                    watchStatus.includes('expir') && !watchStatus.includes('dias')
                                        ? 'text-warning-500'
                                        : 'text-foreground'
                                "
                            >
                                {{ watchStatus }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div class="flex justify-end border-t border-border px-5 py-3">
                    <button
                        type="button"
                        class="text-sm text-danger-600 hover:text-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 rounded transition"
                        @click="openDisconnect"
                    >
                        Desconectar Google Calendar
                    </button>
                </div>
            </template>

            <!-- Estado: Erro de sincronização (disconnected com status_message) -->
            <template v-else-if="account && account.status === 'disconnected'">
                <div class="px-5 py-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 shrink-0 text-warning-500 mt-0.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                            />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-foreground">
                                Sincronização interrompida
                            </p>
                            <p class="text-sm text-foreground-muted mt-0.5">
                                {{ humanizeError(account) }}
                            </p>
                        </div>
                    </div>

                    <!-- Seletor de profissional -->
                    <div v-if="professionals.length > 0">
                        <label
                            for="csp-prof-reconnect"
                            class="block text-sm font-medium text-foreground mb-1"
                            >Profissional</label
                        >
                        <select
                            id="csp-prof-reconnect"
                            v-model="selectedProfessionalId"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                        >
                            <option v-for="p in professionals" :key="p.id" :value="p.id">
                                {{ p.name }}
                            </option>
                        </select>
                    </div>

                    <button
                        type="button"
                        :disabled="connecting"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        @click="connect"
                    >
                        <span v-if="connecting">Reconectando...</span>
                        <span v-else>Reconectar Google Calendar</span>
                    </button>
                </div>
            </template>

            <!-- Estado: Não conectado -->
            <template v-else>
                <div class="px-5 py-6 space-y-4">
                    <div>
                        <p class="text-sm font-semibold text-foreground mb-2">Por que conectar?</p>
                        <ul class="space-y-2 text-sm text-foreground-muted">
                            <li class="flex items-start gap-2">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 shrink-0 text-primary-700 mt-0.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                Consultas criadas no Paciente 360 aparecem automaticamente no Google
                                Calendar do profissional
                            </li>
                            <li class="flex items-start gap-2">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 shrink-0 text-primary-700 mt-0.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                Cancelamentos e reagendamentos são espelhados em tempo real
                            </li>
                            <li class="flex items-start gap-2">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 shrink-0 text-primary-700 mt-0.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                O profissional recebe notificações nativas do Google (app mobile,
                                email)
                            </li>
                        </ul>
                    </div>

                    <!-- Seletor de profissional -->
                    <div v-if="professionals.length > 0">
                        <label
                            for="csp-prof-connect"
                            class="block text-sm font-medium text-foreground mb-1"
                        >
                            Profissional <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="csp-prof-connect"
                            v-model="selectedProfessionalId"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                        >
                            <option value="" disabled>Selecione um profissional</option>
                            <option v-for="p in professionals" :key="p.id" :value="p.id">
                                {{ p.name }}
                            </option>
                        </select>
                    </div>
                    <p v-else class="text-xs text-foreground-muted italic">
                        Nenhum profissional cadastrado. Adicione profissionais antes de conectar o
                        calendário.
                    </p>

                    <button
                        type="button"
                        :disabled="connecting || !selectedProfessionalId"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        @click="connect"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 shrink-0"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                            />
                        </svg>
                        <span v-if="connecting">Conectando...</span>
                        <span v-else>Conectar Google Calendar</span>
                    </button>
                </div>
            </template>
        </section>

        <!-- ─── Microsoft Outlook (placeholder Fase 6) ──────────────────────────── -->
        <section
            class="rounded-lg border border-border bg-surface overflow-hidden"
            aria-label="Microsoft Outlook — indisponível"
        >
            <div class="flex items-center gap-3 border-b border-border px-5 py-4">
                <!-- Ícone Outlook simplificado -->
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6 shrink-0"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <rect x="3" y="3" width="18" height="18" rx="2" fill="#0078d4" />
                    <text
                        x="12"
                        y="16"
                        text-anchor="middle"
                        font-size="9"
                        fill="#fff"
                        font-family="sans-serif"
                        font-weight="bold"
                    >
                        Ol
                    </text>
                </svg>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground-muted">
                            Microsoft Outlook
                        </h2>
                        <span
                            class="inline-flex items-center rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-foreground-muted border border-border"
                        >
                            Em breve
                        </span>
                    </div>
                    <p class="text-xs text-foreground-subtle">Disponível na Fase 6 do roadmap</p>
                </div>
            </div>
            <div class="px-5 py-5">
                <p class="text-sm text-foreground-muted">
                    A integração com Microsoft Outlook / Microsoft 365 Calendar estará disponível em
                    uma versão futura. A sincronização com Google Calendar já está disponível acima.
                </p>
                <button
                    type="button"
                    disabled
                    aria-disabled="true"
                    class="mt-3 inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground-subtle cursor-not-allowed"
                    title="Integração com Outlook será disponibilizada na Fase 6"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                        />
                    </svg>
                    Conectar Outlook (indisponível)
                </button>
            </div>
        </section>

        <!-- ─── Modal de confirmação de desconexão ──────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showDisconnectModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                @click.self="closeDisconnectModal"
                @keydown.esc.prevent="closeDisconnectModal"
                @keydown="trapFocus(disconnectDialogEl, $event)"
            >
                <div
                    ref="disconnectDialogEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="disconnect-modal-title"
                    class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
                >
                    <div class="px-6 py-4 border-b border-border">
                        <h2
                            id="disconnect-modal-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Desconectar Google Calendar
                        </h2>
                    </div>
                    <div class="px-6 py-4 space-y-2">
                        <p class="text-sm text-foreground">
                            Deseja desconectar a conta
                            <strong class="font-semibold">{{ account?.provider_email }}</strong
                            >?
                        </p>
                        <p class="text-sm text-foreground-muted">
                            Suas consultas existentes no Google Calendar
                            <strong>permanecem</strong> — elas não serão apagadas. Novas criações,
                            cancelamentos e reagendamentos no Paciente 360
                            <strong>não serão espelhados</strong> até reconectar.
                        </p>
                    </div>
                    <div
                        class="flex items-center justify-end gap-3 border-t border-border px-6 py-4"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            @click="closeDisconnectModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="disconnecting"
                            class="rounded-lg bg-danger-500 px-4 py-2 text-sm font-semibold text-white hover:bg-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            @click="confirmDisconnect"
                        >
                            <span v-if="disconnecting">Desconectando...</span>
                            <span v-else>Desconectar</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
