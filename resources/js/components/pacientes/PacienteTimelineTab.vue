<script setup>
import { ref, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import TimelineEvent from './TimelineEvent.vue';

const { t } = useI18n();

const props = defineProps({
    pacienteId: {
        type: Number,
        required: true,
    },
});

// ─── Estado ───────────────────────────────────────────────────────────────────

const eventos = ref([]);
const loading = ref(false);
const loadError = ref(null);
const nextCursor = ref(null);
const hasMore = ref(false);
const loadingMore = ref(false);

// Filtros por tipo
const TIPOS_DISPONIVEIS = [
    { value: 'paciente.criado', label: 'Criação' },
    { value: 'paciente.atualizado', label: 'Atualização' },
    { value: 'paciente.status_alterado', label: 'Status alterado' },
    { value: 'paciente.anotacao_criada', label: 'Anotação criada' },
    { value: 'paciente.anotacao_retratada', label: 'Retratação' },
    { value: 'paciente.mesclado', label: 'Mesclagem' },
    { value: 'paciente.mesclagem_revertida', label: 'Reversão de mesclagem' },
    { value: 'paciente.tag_aplicada', label: 'Tag aplicada' },
    { value: 'paciente.tag_removida', label: 'Tag removida' },
    { value: 'lead.movido_no_funil', label: 'Funil' },
];

const tiposSelecionados = ref([]);

// ─── Carregamento ─────────────────────────────────────────────────────────────

async function carregarTimeline(append = false) {
    if (append) {
        loadingMore.value = true;
    } else {
        loading.value = true;
        loadError.value = null;
        eventos.value = [];
        nextCursor.value = null;
    }

    try {
        const params = new URLSearchParams();
        params.set('limit', '50');

        if (tiposSelecionados.value.length > 0) {
            tiposSelecionados.value.forEach((tipo) => params.append('tipo[]', tipo));
        }

        if (append && nextCursor.value) {
            params.set('cursor', nextCursor.value);
        }

        const { data } = await api.get(
            `/pacientes/${props.pacienteId}/timeline?${params.toString()}`,
        );

        const novos = data.data ?? [];
        if (append) {
            eventos.value = [...eventos.value, ...novos];
        } else {
            eventos.value = novos;
        }

        nextCursor.value = data.meta?.next_cursor ?? null;
        hasMore.value = data.meta?.has_more ?? false;
    } catch {
        loadError.value = t('common.error_generic');
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

async function carregarMais() {
    if (!hasMore.value || loadingMore.value) { return; }
    await carregarTimeline(true);
}

function toggleTipo(tipo) {
    const idx = tiposSelecionados.value.indexOf(tipo);
    if (idx === -1) {
        tiposSelecionados.value = [...tiposSelecionados.value, tipo];
    } else {
        tiposSelecionados.value = tiposSelecionados.value.filter((t) => t !== tipo);
    }
}

// Recarrega ao mudar filtros
watch(tiposSelecionados, () => carregarTimeline(false), { deep: true });

onMounted(() => carregarTimeline(false));
</script>

<template>
    <div class="space-y-4">
        <!-- Filtros por tipo -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="tipo in TIPOS_DISPONIVEIS"
                :key="tipo.value"
                type="button"
                class="rounded-full border px-3 py-1 text-xs font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                :class="tiposSelecionados.includes(tipo.value)
                    ? 'border-primary-600 bg-primary-50 text-primary-700'
                    : 'border-border bg-surface text-foreground-muted hover:text-foreground'"
                @click="toggleTipo(tipo.value)"
            >
                {{ tipo.label }}
            </button>
            <button
                v-if="tiposSelecionados.length > 0"
                type="button"
                class="rounded-full border border-border px-3 py-1 text-xs font-medium text-foreground-muted transition hover:text-foreground"
                @click="tiposSelecionados = []"
            >
                {{ t('common.filter') }} ×
            </button>
        </div>

        <!-- Loading inicial -->
        <div
            v-if="loading"
            class="flex items-center justify-center py-12"
            aria-live="polite"
            aria-busy="true"
        >
            <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
        </div>

        <!-- Erro -->
        <div
            v-else-if="loadError"
            role="alert"
            class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-600"
        >
            {{ loadError }}
        </div>

        <!-- Empty state -->
        <div
            v-else-if="eventos.length === 0"
            class="flex flex-col items-center justify-center py-16 text-center"
        >
            <p class="text-sm text-foreground-muted">
                {{ t('timeline.empty') }}
            </p>
        </div>

        <!-- Lista de eventos -->
        <div v-else class="space-y-2">
            <TimelineEvent
                v-for="evento in eventos"
                :key="evento.id"
                :evento="evento"
            />

            <!-- Botão Carregar mais -->
            <div
                v-if="hasMore"
                class="flex justify-center pt-4"
            >
                <button
                    type="button"
                    :disabled="loadingMore"
                    class="rounded-lg border border-border px-5 py-2 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="carregarMais"
                >
                    {{ loadingMore ? t('common.loading') : t('timeline.load_more') }}
                </button>
            </div>
        </div>
    </div>
</template>
