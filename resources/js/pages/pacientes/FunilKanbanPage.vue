<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import KanbanBoard from '@/components/funil/KanbanBoard.vue';
import PerdidoMotivoModal from '@/components/pacientes/PerdidoMotivoModal.vue';

const { t } = useI18n();

// ─── Estado ──────────────────────────────────────────────────────────────────

const colunas = ref([]);
const pacientes = ref([]);
const loading = ref(true);
const erroCarregamento = ref(null);

// Filtros do toolbar
const filtros = ref({
    origem: '',
    profissional_responsavel_id: '',
});

// Modal de motivo para coluna perdido
const modalPerdidoAberto = ref(false);

/**
 * Payload pendente quando o drop acontece em coluna com `motivo_obrigatorio`.
 * @type {{ paciente: Object, colunaDestino: Object, posicao: number } | null}
 */
const movimentoPendente = ref(null);

// ─── Carregamento inicial ─────────────────────────────────────────────────────

async function carregarColunas() {
    const { data } = await api.get('/funil/colunas');

    colunas.value = data.data ?? [];
}

async function carregarPacientes() {
    const params = {
        per_page: 500,
    };

    if (filtros.value.origem) {
        params.origem = filtros.value.origem;
    }

    if (filtros.value.profissional_responsavel_id) {
        params.profissional_responsavel_id = filtros.value.profissional_responsavel_id;
    }

    const { data } = await api.get('/pacientes', { params });

    pacientes.value = data.data ?? [];
}

async function carregar() {
    loading.value = true;
    erroCarregamento.value = null;

    try {
        await Promise.all([carregarColunas(), carregarPacientes()]);
    } catch (e) {
        erroCarregamento.value = e?.response?.data?.message ?? 'Erro ao carregar o Kanban.';
    } finally {
        loading.value = false;
    }
}

onMounted(carregar);

// ─── Drag-and-drop ────────────────────────────────────────────────────────────

/**
 * Trata o evento de movimentação de card emitido pelo KanbanBoard.
 * Se a coluna destino tem `motivo_obrigatorio`, abre o modal primeiro.
 */
function onCardMoved({ paciente, colunaDestino, posicao }) {
    movimentoPendente.value = { paciente, colunaDestino, posicao };

    if (colunaDestino.motivo_obrigatorio) {
        modalPerdidoAberto.value = true;
    } else {
        executarMovimento(null, null);
    }
}

async function executarMovimento(motivo, motivoOutro) {
    if (!movimentoPendente.value) {
        return;
    }

    const { paciente, colunaDestino, posicao } = movimentoPendente.value;

    try {
        await api.patch(`/pacientes/${paciente.id}/funil`, {
            coluna_id: colunaDestino.id,
            posicao,
            motivo: motivo ?? undefined,
            motivo_outro: motivoOutro ?? undefined,
        });

        // Atualiza o estado local imediatamente
        const idx = pacientes.value.findIndex((p) => p.id === paciente.id);

        if (idx !== -1) {
            pacientes.value[idx] = {
                ...pacientes.value[idx],
                funil_coluna_atual_id: colunaDestino.id,
                funil_posicao: posicao,
            };
        }
    } catch (e) {
        // Se 422, o usuário já viu o modal; recarregar para desfazer o drop visual
        await carregarPacientes();
    } finally {
        movimentoPendente.value = null;
        modalPerdidoAberto.value = false;
    }
}

function onMotivoConfirmado({ motivo, motivo_outro }) {
    executarMovimento(motivo, motivo_outro);
}

function onModalCancelado() {
    // Desfaz o drop visual recarregando
    movimentoPendente.value = null;
    modalPerdidoAberto.value = false;
    carregarPacientes();
}

// ─── Filtros ──────────────────────────────────────────────────────────────────

async function aplicarFiltros() {
    await carregarPacientes();
}

const ORIGEM_OPTIONS = [
    { value: '', label: 'Todas as origens' },
    { value: 'site', label: 'Site' },
    { value: 'indicacao', label: 'Indicação' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'telefone', label: 'Telefone' },
    { value: 'presencial', label: 'Presencial' },
    { value: 'outro', label: 'Outro' },
];
</script>

<template>
    <div class="p-6 min-h-screen bg-surface text-foreground">
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">{{ t('funil.title') }}</h1>
            <a
                href="/panel/pacientes/funil/config"
                class="text-sm text-primary-600 hover:underline"
            >
                Configurar funil
            </a>
        </div>

        <!-- Toolbar de filtros -->
        <div class="flex gap-3 mb-6 flex-wrap">
            <select
                v-model="filtros.origem"
                aria-label="Filtrar por origem"
                class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary-500"
                @change="aplicarFiltros"
            >
                <option v-for="opt in ORIGEM_OPTIONS" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center items-center py-20">
            <span class="text-foreground-muted">Carregando Kanban…</span>
        </div>

        <!-- Erro -->
        <div
            v-else-if="erroCarregamento"
            class="rounded-lg bg-red-50 border border-red-200 p-4 text-red-700 text-sm"
            role="alert"
        >
            {{ erroCarregamento }}
        </div>

        <!-- Board -->
        <KanbanBoard v-else :colunas="colunas" :pacientes="pacientes" @card-moved="onCardMoved" />

        <!-- Modal de motivo perdido -->
        <PerdidoMotivoModal
            :open="modalPerdidoAberto"
            @confirm="onMotivoConfirmado"
            @cancel="onModalCancelado"
        />
    </div>
</template>
