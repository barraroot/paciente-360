<script setup>
/**
 * **T169 (Fase 8 — Lote C US-9.1)** — Detalhes + ações de campanha (AC-9.1.2/4/6).
 */
import { onMounted, ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCampaignsStore } from '@/stores/campaignsStore';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

const route = useRoute();
const router = useRouter();
const store = useCampaignsStore();

const previewData = ref(null);
const previewing = ref(false);
const dispatchModalOpen = ref(false);
const showCancelModal = ref(false);
const cancelReason = ref('');

const id = computed(() => route.params.id);
const c = computed(() => store.current);

onMounted(async () => {
    await store.fetchCampaign(id.value);
});

async function doPreview() {
    previewing.value = true;
    try {
        previewData.value = await store.preview(id.value);
    } finally {
        previewing.value = false;
    }
}

async function doDispatch() {
    dispatchModalOpen.value = false;
    try {
        await store.dispatch(id.value);
        router.push(`/campaigns/${id.value}/report`);
    } catch (_) {}
}

async function confirmCancel() {
    try {
        await store.cancel(id.value, cancelReason.value.trim() || null);
        showCancelModal.value = false;
        await store.fetchCampaign(id.value);
    } catch (_) {}
}

const canDispatch = computed(() => c.value && ['draft', 'scheduled'].includes(c.value.status));
const canCancel = computed(() => c.value && !['completed', 'canceled'].includes(c.value.status));
</script>

<template>
    <div v-if="!c" class="p-6 text-sm text-foreground-muted">Carregando…</div>

    <div v-else class="space-y-6 p-6">
        <header class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ c.name }}</h1>
                <p class="mt-1 text-sm text-foreground-muted">
                    Canal: <strong>{{ c.channel }}</strong> • Status:
                    <strong>{{ c.status_label }}</strong>
                </p>
            </div>
            <RouterLink to="/campaigns" class="text-sm text-foreground-muted underline"
                >Voltar</RouterLink
            >
        </header>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded border bg-white p-4">
                <h3 class="text-xs font-medium text-foreground-muted">Elegíveis</h3>
                <p class="mt-1 text-2xl font-bold">{{ c.total_eligible ?? '—' }}</p>
            </div>
            <div class="rounded border bg-white p-4">
                <h3 class="text-xs font-medium text-foreground-muted">Enviados</h3>
                <p class="mt-1 text-2xl font-bold text-success-700">{{ c.total_dispatched }}</p>
            </div>
            <div class="rounded border bg-white p-4">
                <h3 class="text-xs font-medium text-foreground-muted">Bloqueados</h3>
                <p class="mt-1 text-2xl font-bold text-danger-700">{{ c.total_blocked }}</p>
            </div>
        </div>

        <section class="rounded border bg-white p-4">
            <h2 class="text-sm font-semibold">Segmentação aplicada</h2>
            <pre class="mt-2 text-xs overflow-auto bg-surface-muted p-3 rounded">{{
                JSON.stringify(c.audience_filters, null, 2)
            }}</pre>
        </section>

        <div class="flex gap-2">
            <button
                type="button"
                @click="doPreview"
                :disabled="previewing"
                class="rounded border px-4 py-2 text-sm hover:bg-surface-muted disabled:opacity-50"
            >
                {{ previewing ? 'Calculando…' : 'Pré-visualizar' }}
            </button>

            <button
                v-if="canDispatch"
                type="button"
                @click="dispatchModalOpen = true"
                :disabled="store.saving"
                class="rounded bg-success-600 px-4 py-2 text-sm font-medium text-white hover:bg-success-700 disabled:opacity-50"
            >
                Disparar agora
            </button>

            <button
                v-if="canCancel"
                type="button"
                @click="showCancelModal = true"
                class="rounded bg-danger-600 px-4 py-2 text-sm font-medium text-white hover:bg-danger-700"
            >
                Cancelar campanha
            </button>

            <RouterLink
                :to="`/campaigns/${c.id}/report`"
                class="rounded border bg-primary-50 px-4 py-2 text-sm hover:bg-primary-100"
            >
                Ver relatório
            </RouterLink>
        </div>

        <section
            v-if="previewData"
            class="rounded border border-primary-200 bg-primary-50 p-4 text-sm"
        >
            <h3 class="font-semibold">Pré-visualização</h3>
            <p class="mt-2">
                <strong>{{ previewData.eligible_count }}</strong> paciente(s) elegível(is)
            </p>
            <ul v-if="previewData.warnings?.length" class="mt-3 list-disc pl-5 space-y-1">
                <li v-for="(w, i) in previewData.warnings" :key="i" class="text-warning-800">
                    {{ w }}
                </li>
            </ul>
        </section>

        <ConfirmModal
            :open="dispatchModalOpen"
            title="Disparar campanha"
            @close="dispatchModalOpen = false"
            @confirm="doDispatch"
        >
            <p>Confirma o disparo da campanha? Esta ação irá processar o público elegível.</p>
        </ConfirmModal>

        <Teleport to="body">
            <div
                v-if="showCancelModal"
                role="dialog"
                aria-modal="true"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-4"
                @keydown.esc.prevent="showCancelModal = false"
                @click.self="showCancelModal = false"
            >
                <div class="w-full max-w-md rounded bg-white p-6 shadow-xl">
                    <h2 class="text-lg font-semibold">Cancelar campanha</h2>
                    <textarea
                        v-model="cancelReason"
                        rows="3"
                        class="mt-3 w-full rounded border-border-strong text-sm"
                        placeholder="Motivo (opcional)"
                    ></textarea>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-sm"
                            @click="showCancelModal = false"
                        >
                            Voltar
                        </button>
                        <button
                            type="button"
                            class="rounded bg-danger-600 px-3 py-1.5 text-sm text-white"
                            :disabled="store.saving"
                            @click="confirmCancel"
                        >
                            Confirmar cancelamento
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
