<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useCanaisStore } from '@/stores/canais.js';
import { useAuthStore } from '@/stores/auth.js';
import ChannelStatusBadge from '@/components/Canais/ChannelStatusBadge.vue';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

const { t } = useI18n();
const router = useRouter();
const canaisStore = useCanaisStore();
const auth = useAuthStore();

// ─── Ability checks ──────────────────────────────────────────────────────────

const canConnect = computed(() => auth.hasPermission('channel.connect'));
const canDisconnect = computed(() => auth.hasPermission('channel.disconnect'));

// ─── Dropdown "Conectar canal" ────────────────────────────────────────────────

const conectarDropdownOpen = ref(false);

function toggleConectarDropdown() {
    conectarDropdownOpen.value = !conectarDropdownOpen.value;
}

function closeConectarDropdown() {
    conectarDropdownOpen.value = false;
}

// ─── Dropdown de ações por card ───────────────────────────────────────────────

const openActionDropdown = ref(null);

function toggleActionDropdown(id) {
    openActionDropdown.value = openActionDropdown.value === id ? null : id;
}

function closeAllDropdowns() {
    openActionDropdown.value = null;
    conectarDropdownOpen.value = false;
}

// ─── Carregamento de dados ────────────────────────────────────────────────────

async function load() {
    try {
        await canaisStore.loadChannels();
    } catch {
        // erro já fica em canaisStore.error
    }
}

onMounted(() => {
    load();
    // Auto-refresh a cada 30s
    refreshInterval = setInterval(load, 30_000);
});

let refreshInterval = null;
onBeforeUnmount(() => {
    if (refreshInterval) { clearInterval(refreshInterval); }
});

// ─── Modal desconectar ────────────────────────────────────────────────────────

const disconnectModal = ref({
    open: false,
    channelId: null,
    channelName: '',
    loading: false,
    error: null,
    force: false,
});

function openDisconnectModal(channel) {
    disconnectModal.value = {
        open: true,
        channelId: channel.id,
        channelName: channel.name,
        loading: false,
        error: null,
        force: false,
    };
}

function closeDisconnectModal() {
    disconnectModal.value.open = false;
}

async function confirmDisconnect() {
    disconnectModal.value.loading = true;
    disconnectModal.value.error = null;
    try {
        await canaisStore.disconnect(disconnectModal.value.channelId, disconnectModal.value.force);
        closeDisconnectModal();
    } catch (err) {
        const status = err.response?.status;
        const code = err.response?.data?.error;
        if (status === 409 || code === 'channel.has_active_conversations') {
            // Exibe opção de forçar desconexão
            disconnectModal.value.error = t('canais.errors.disconnect_with_active');
            disconnectModal.value.force = true;
        } else if (status === 403) {
            disconnectModal.value.error = t('canais.errors.no_permission');
        } else {
            disconnectModal.value.error = t('common.error_generic');
        }
        disconnectModal.value.loading = false;
    }
}

// ─── Ações do dropdown de canal ───────────────────────────────────────────────

async function handleReconnect(channel) {
    closeAllDropdowns();
    try {
        await canaisStore.reconnect(channel.id);
    } catch {
        // Silencioso — Detalhe page exibirá o erro
    }
}

async function handleSyncTemplates(channel) {
    closeAllDropdowns();
    try {
        await canaisStore.syncTemplates(channel.id);
    } catch {
        // Silencioso
    }
}

// ─── Helpers visuais ──────────────────────────────────────────────────────────

function channelIcon(type) {
    const icons = {
        whatsapp: 'whatsapp',
        instagram: 'instagram',
        web: 'web',
    };
    return icons[type] ?? 'web';
}

function channelIconBgClass(type) {
    const map = {
        whatsapp: 'bg-[#25D366]',
        instagram: 'bg-gradient-to-br from-[#833ab4] via-[#fd1d1d] to-[#fcb045]',
        web: 'bg-surface-elevated border border-border',
    };
    return map[type] ?? 'bg-surface-elevated';
}

function channelIconTextClass(type) {
    return type === 'web' ? 'text-foreground-muted' : 'text-white';
}

function qualityRatingClass(rating) {
    const map = {
        high: 'bg-success-50 text-success-700 border-success-200',
        medium: 'bg-warning-50 text-warning-700 border-warning-200',
        low: 'bg-danger-50 text-danger-700 border-danger-200',
        flagged: 'bg-danger-100 text-danger-800 border-danger-300',
    };
    return map[rating] ?? 'bg-surface text-foreground-muted border-border';
}

function qualityRatingLabel(rating) {
    if (!rating) { return null; }
    return t(`canais.quality_rating.${rating}`);
}

function channelSubtitle(channel) {
    if (channel.type === 'whatsapp') {
        const sender = channel.provider_metadata?.whatsapp_sender ?? '';
        return `${t('canais.tipo.whatsapp')} · ${sender}`;
    }
    if (channel.type === 'instagram') {
        return t('canais.tipo.instagram');
    }
    return t('canais.tipo.web');
}

function canReconnect(channel) {
    return ['expirado', 'invalido'].includes(channel.status);
}
</script>

<template>
    <main
        class="min-h-screen bg-surface py-8 px-4"
        @click="closeAllDropdowns"
    >
        <div class="mx-auto max-w-7xl">

            <!-- ── Cabeçalho ──────────────────────────────────────────────────── -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <nav
                        aria-label="Breadcrumb"
                        class="mb-1 flex items-center gap-1.5 text-xs text-foreground-muted"
                    >
                        <a
                            href="/panel"
                            class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            Painel
                        </a>
                        <span aria-hidden="true">›</span>
                        <span class="text-foreground">{{ t('canais.titulo') }}</span>
                    </nav>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ t('canais.titulo') }}
                    </h1>
                </div>

                <!-- Botão + dropdown "Conectar canal" -->
                <div
                    v-if="canConnect"
                    class="relative"
                    @click.stop
                >
                    <button
                        type="button"
                        :aria-expanded="conectarDropdownOpen"
                        aria-haspopup="true"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="toggleConectarDropdown"
                    >
                        + {{ t('canais.conectar') }}
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div
                        v-if="conectarDropdownOpen"
                        class="absolute right-0 z-20 mt-1 w-52 rounded-lg border border-border bg-surface-elevated shadow-xl"
                        role="menu"
                    >
                        <!-- WhatsApp — habilitado -->
                        <button
                            type="button"
                            role="menuitem"
                            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-sm text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded-t-lg"
                            @click="() => { closeConectarDropdown(); router.push({ name: 'canais.conectar_whatsapp' }); }"
                        >
                            <!-- Ícone WhatsApp -->
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#25D366] text-white text-xs font-bold shrink-0">W</span>
                            {{ t('canais.conectar_whatsapp') }}
                        </button>

                        <!-- Instagram — em breve -->
                        <div
                            role="menuitem"
                            aria-disabled="true"
                            class="group relative flex w-full items-center gap-2.5 px-3 py-2.5 text-sm text-foreground-muted cursor-not-allowed"
                            :title="t('canais.em_breve')"
                        >
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-[#833ab4] via-[#fd1d1d] to-[#fcb045] text-white text-xs font-bold shrink-0">I</span>
                            {{ t('canais.conectar_instagram') }}
                            <span class="ml-auto rounded-full bg-surface px-1.5 py-0.5 text-[10px] font-medium text-foreground-subtle border border-border">
                                {{ t('canais.em_breve') }}
                            </span>
                        </div>

                        <!-- Widget Web — em breve -->
                        <div
                            role="menuitem"
                            aria-disabled="true"
                            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-sm text-foreground-muted cursor-not-allowed rounded-b-lg"
                            :title="t('canais.em_breve')"
                        >
                            <span class="flex h-6 w-6 items-center justify-center rounded-full border border-border bg-surface text-foreground-muted text-xs font-bold shrink-0">W</span>
                            {{ t('canais.conectar_widget') }}
                            <span class="ml-auto rounded-full bg-surface px-1.5 py-0.5 text-[10px] font-medium text-foreground-subtle border border-border">
                                {{ t('canais.em_breve') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Loading — Skeleton de 3 cards ─────────────────────────────── -->
            <div
                v-if="canaisStore.loading && canaisStore.channels.length === 0"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                aria-busy="true"
                aria-live="polite"
            >
                <div
                    v-for="n in 3"
                    :key="n"
                    class="animate-pulse rounded-xl border border-border bg-surface-elevated p-5 shadow-[var(--shadow-card)]"
                >
                    <div class="flex items-start gap-4">
                        <div class="h-10 w-10 rounded-full bg-surface shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 w-2/3 rounded bg-surface"></div>
                            <div class="h-3 w-1/2 rounded bg-surface"></div>
                        </div>
                        <div class="h-5 w-16 rounded-full bg-surface"></div>
                    </div>
                </div>
            </div>

            <!-- ── Erro ───────────────────────────────────────────────────────── -->
            <div
                v-else-if="canaisStore.error && canaisStore.channels.length === 0"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-4 text-sm text-danger-700"
            >
                <p class="font-medium">{{ t('canais.errors.load_failed') }}</p>
                <p class="mt-1 text-danger-600">{{ canaisStore.error }}</p>
                <button
                    type="button"
                    class="mt-3 rounded-lg border border-danger-400 px-3 py-1.5 text-xs font-medium text-danger-700 transition hover:bg-danger-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="load"
                >
                    Tentar novamente
                </button>
            </div>

            <!-- ── Empty state ────────────────────────────────────────────────── -->
            <div
                v-else-if="!canaisStore.loading && canaisStore.channels.length === 0"
                class="flex flex-col items-center justify-center gap-5 py-24 text-center"
            >
                <!-- Ilustração placeholder -->
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-surface-elevated border border-border">
                    <svg class="h-10 w-10 text-foreground-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-base font-semibold text-foreground">{{ t('canais.empty_state.titulo') }}</p>
                    <p class="mt-1 text-sm text-foreground-muted">{{ t('canais.empty_state.descricao') }}</p>
                </div>
                <button
                    v-if="canConnect"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="router.push({ name: 'canais.conectar_whatsapp' })"
                >
                    {{ t('canais.empty_state.cta') }}
                </button>
            </div>

            <!-- ── Lista de cards ──────────────────────────────────────────────── -->
            <div
                v-else
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                aria-label="t('canais.titulo')"
            >
                <article
                    v-for="channel in canaisStore.channels"
                    :key="channel.id"
                    class="relative rounded-xl border border-border bg-surface-elevated p-5 shadow-[var(--shadow-card)] transition hover:border-primary-300"
                >
                    <div class="flex items-start gap-4">
                        <!-- Avatar / Ícone do tipo -->
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                            :class="[channelIconBgClass(channel.type), channelIconTextClass(channel.type)]"
                            aria-hidden="true"
                        >
                            <template v-if="channel.type === 'whatsapp'">W</template>
                            <template v-else-if="channel.type === 'instagram'">I</template>
                            <template v-else>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
                                </svg>
                            </template>
                        </div>

                        <!-- Informações principais -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-foreground">
                                {{ channel.name }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-foreground-muted">
                                {{ channelSubtitle(channel) }}
                            </p>
                        </div>

                        <!-- Badge de status -->
                        <ChannelStatusBadge :status="channel.status" class="shrink-0" />
                    </div>

                    <!-- Quality Rating (apenas WhatsApp) -->
                    <div
                        v-if="channel.type === 'whatsapp' && channel.quality_rating"
                        class="mt-3"
                    >
                        <span
                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                            :class="qualityRatingClass(channel.quality_rating)"
                        >
                            <span class="font-semibold">{{ t('canais.quality_rating.label') }}:</span>
                            {{ qualityRatingLabel(channel.quality_rating) }}
                        </span>
                    </div>

                    <!-- Dropdown de ações (3 pontos) -->
                    <div
                        class="absolute right-3 top-3"
                        @click.stop
                    >
                        <button
                            type="button"
                            :aria-label="`Ações para ${channel.name}`"
                            :aria-expanded="openActionDropdown === channel.id"
                            aria-haspopup="true"
                            class="rounded-lg p-1.5 text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="toggleActionDropdown(channel.id)"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM10 8.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM11.5 15.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z" />
                            </svg>
                        </button>

                        <div
                            v-if="openActionDropdown === channel.id"
                            class="absolute right-0 z-20 mt-1 w-48 rounded-lg border border-border bg-surface-elevated shadow-xl"
                            role="menu"
                        >
                            <!-- Ver detalhes -->
                            <button
                                type="button"
                                role="menuitem"
                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded-t-lg"
                                @click="() => { closeAllDropdowns(); router.push({ name: 'canais.show', params: { id: channel.id } }); }"
                            >
                                {{ t('canais.actions.ver_detalhes') }}
                            </button>

                            <!-- Reconectar (apenas status expirado/invalido) -->
                            <button
                                v-if="canReconnect(channel)"
                                type="button"
                                role="menuitem"
                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-warning-700 transition hover:bg-warning-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="handleReconnect(channel)"
                            >
                                {{ t('canais.actions.reconectar') }}
                            </button>

                            <!-- Sincronizar templates (apenas WhatsApp) -->
                            <button
                                v-if="channel.type === 'whatsapp'"
                                type="button"
                                role="menuitem"
                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="handleSyncTemplates(channel)"
                            >
                                {{ t('canais.actions.sincronizar_templates') }}
                            </button>

                            <!-- Desconectar (apenas admin com ability) -->
                            <button
                                v-if="canDisconnect"
                                type="button"
                                role="menuitem"
                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded-b-lg"
                                @click="() => { closeAllDropdowns(); openDisconnectModal(channel); }"
                            >
                                {{ t('canais.actions.desconectar') }}
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <!-- ── Modal desconectar ──────────────────────────────────────────────── -->
        <ConfirmModal
            :open="disconnectModal.open"
            :title="t('canais.disconnect_confirm.titulo')"
            @close="closeDisconnectModal"
            @confirm="confirmDisconnect"
        >
            <p class="text-sm text-foreground-muted">
                {{ t('canais.disconnect_confirm.descricao') }}
            </p>
            <p
                v-if="disconnectModal.channelName"
                class="mt-1 text-sm font-medium text-foreground"
            >
                {{ disconnectModal.channelName }}
            </p>
            <div
                v-if="disconnectModal.error"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-warning-400 bg-warning-50 px-3 py-2 text-sm text-warning-800"
            >
                {{ disconnectModal.error }}
            </div>
            <template #actions>
                <button
                    type="button"
                    :disabled="disconnectModal.loading"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmDisconnect"
                >
                    {{ disconnectModal.loading
                        ? t('common.loading')
                        : (disconnectModal.force ? 'Forçar desconexão' : t('canais.actions.desconectar'))
                    }}
                </button>
            </template>
        </ConfirmModal>
    </main>
</template>
