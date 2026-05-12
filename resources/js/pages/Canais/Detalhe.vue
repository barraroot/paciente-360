<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useCanaisStore } from '@/stores/canais.js';
import ChannelStatusBadge from '@/components/Canais/ChannelStatusBadge.vue';

const props = defineProps({
    id: {
        type: [String, Number],
        required: true,
    },
});

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const canaisStore = useCanaisStore();

const loadingTemplates = ref(false);
const syncingTemplates = ref(false);
const toastMessage = ref(null);

// Exibe toast quando vindo de redirect com ?toast=connected
if (route.query.toast === 'connected') {
    toastMessage.value = t('canais.success.connected');
    // Limpa o query param sem recarregar a página
    router.replace({ name: 'canais.show', params: { id: props.id } });
    setTimeout(() => { toastMessage.value = null; }, 5000);
}

const channel = computed(() => canaisStore.selectedChannel);
const templates = computed(() => canaisStore.templatesByChannel[String(props.id)] ?? []);

onMounted(async () => {
    await canaisStore.loadChannel(props.id);
    loadingTemplates.value = true;
    try {
        await canaisStore.loadTemplates(props.id);
    } catch {
        // Silencioso
    } finally {
        loadingTemplates.value = false;
    }
});

async function syncTemplates() {
    syncingTemplates.value = true;
    try {
        await canaisStore.syncTemplates(props.id);
        toastMessage.value = t('canais.success.templates_synced');
        setTimeout(() => { toastMessage.value = null; }, 5000);
    } catch {
        // Silencioso
    } finally {
        syncingTemplates.value = false;
    }
}

function channelBgClass(type) {
    const map = {
        whatsapp: 'bg-[#25D366]',
        instagram: 'bg-gradient-to-br from-[#833ab4] via-[#fd1d1d] to-[#fcb045]',
        web: 'bg-surface-elevated border border-border',
    };
    return map[type] ?? 'bg-surface-elevated';
}

function channelIconText(type) {
    return { whatsapp: 'W', instagram: 'I', web: 'W' }[type] ?? '?';
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-3xl">

            <!-- Toast de sucesso -->
            <div
                v-if="toastMessage"
                role="alert"
                aria-live="polite"
                class="mb-4 rounded-lg border border-success-400 bg-success-50 px-4 py-3 text-sm font-medium text-success-700"
            >
                {{ toastMessage }}
            </div>

            <!-- Breadcrumb -->
            <nav
                aria-label="Breadcrumb"
                class="mb-4 flex items-center gap-1.5 text-xs text-foreground-muted"
            >
                <a
                    href="/panel"
                    class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                >
                    Painel
                </a>
                <span aria-hidden="true">›</span>
                <button
                    type="button"
                    class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                    @click="router.push({ name: 'canais.index' })"
                >
                    {{ t('canais.titulo') }}
                </button>
                <span aria-hidden="true">›</span>
                <span class="text-foreground">{{ channel?.name ?? t('canais.detalhe.titulo') }}</span>
            </nav>

            <!-- Loading -->
            <div
                v-if="canaisStore.loading && !channel"
                class="animate-pulse space-y-4"
                aria-busy="true"
            >
                <div class="h-8 w-48 rounded bg-surface-elevated"></div>
                <div class="rounded-xl border border-border bg-surface-elevated p-6">
                    <div class="space-y-3">
                        <div class="h-4 w-2/3 rounded bg-surface"></div>
                        <div class="h-4 w-1/2 rounded bg-surface"></div>
                        <div class="h-4 w-1/3 rounded bg-surface"></div>
                    </div>
                </div>
            </div>

            <template v-else-if="channel">
                <!-- Card de detalhes -->
                <div class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-base font-bold text-white"
                            :class="channelBgClass(channel.type)"
                            aria-hidden="true"
                        >
                            {{ channelIconText(channel.type) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl font-bold text-foreground">{{ channel.name }}</h1>
                            <p class="text-sm text-foreground-muted">{{ t(`canais.tipo.${channel.type}`) }}</p>
                        </div>
                        <ChannelStatusBadge :status="channel.status" />
                    </div>

                    <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                        <div v-if="channel.provider_metadata?.whatsapp_sender">
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('canais.detalhe.sender_label') }}
                            </dt>
                            <dd class="mt-0.5 font-mono text-foreground">
                                {{ channel.provider_metadata.whatsapp_sender }}
                            </dd>
                        </div>
                        <div v-if="channel.quality_rating">
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('canais.quality_rating.label') }}
                            </dt>
                            <dd class="mt-0.5 text-foreground">
                                {{ t(`canais.quality_rating.${channel.quality_rating}`) }}
                            </dd>
                        </div>
                        <div v-if="channel.created_at">
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('canais.detalhe.criado_em') }}
                            </dt>
                            <dd class="mt-0.5 text-foreground">
                                {{ new Date(channel.created_at).toLocaleDateString('pt-BR') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Templates -->
                <div class="mt-6">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-foreground">
                            {{ t('canais.detalhe.templates_titulo') }}
                        </h2>
                        <button
                            v-if="channel.type === 'whatsapp'"
                            type="button"
                            :disabled="syncingTemplates"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="syncTemplates"
                        >
                            <svg
                                v-if="syncingTemplates"
                                class="h-3.5 w-3.5 animate-spin"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ syncingTemplates ? t('common.loading') : t('canais.detalhe.sincronizar_cta') }}
                        </button>
                    </div>

                    <div
                        v-if="loadingTemplates"
                        class="animate-pulse space-y-2"
                        aria-busy="true"
                    >
                        <div v-for="n in 3" :key="n" class="h-10 rounded-lg border border-border bg-surface-elevated"></div>
                    </div>

                    <div
                        v-else-if="templates.length === 0"
                        class="rounded-lg border border-border bg-surface-elevated px-4 py-6 text-center text-sm text-foreground-muted"
                    >
                        {{ t('canais.detalhe.templates_vazio') }}
                    </div>

                    <div
                        v-else
                        class="divide-y divide-border rounded-xl border border-border bg-surface-elevated overflow-hidden"
                    >
                        <div
                            v-for="tpl in templates"
                            :key="tpl.id"
                            class="flex items-center justify-between px-4 py-3 text-sm"
                        >
                            <div>
                                <p class="font-medium text-foreground">{{ tpl.name }}</p>
                                <p class="text-xs text-foreground-muted">{{ tpl.category }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="tpl.status === 'approved'
                                    ? 'bg-success-50 text-success-700'
                                    : 'bg-surface text-foreground-muted border border-border'"
                            >
                                {{ tpl.status }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Botão voltar -->
                <div class="mt-6">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="router.push({ name: 'canais.index' })"
                    >
                        ← {{ t('canais.detalhe.voltar') }}
                    </button>
                </div>
            </template>
        </div>
    </main>
</template>
