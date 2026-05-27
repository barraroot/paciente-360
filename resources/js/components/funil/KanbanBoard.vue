<script setup>
import { computed } from 'vue';
import KanbanColumn from './KanbanColumn.vue';

const props = defineProps({
    colunas: {
        type: Array,
        required: true,
    },
    pacientes: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['card-moved']);

/**
 * Agrupa pacientes por `funil_coluna_atual_id`.
 * Pacientes sem coluna atribuída ficam de fora do Kanban.
 *
 * @returns {Object} mapa { colunaId: Paciente[] }
 */
const pacientesPorColuna = computed(() => {
    const mapa = {};

    for (const coluna of props.colunas) {
        mapa[coluna.id] = [];
    }

    for (const p of props.pacientes) {
        if (p.funil_coluna_atual_id && mapa[p.funil_coluna_atual_id] !== undefined) {
            mapa[p.funil_coluna_atual_id].push(p);
        }
    }

    return mapa;
});

function onCardMoved(payload) {
    emit('card-moved', payload);
}
</script>

<template>
    <div
        class="flex gap-4 overflow-x-auto pb-4 min-h-96"
        tabindex="0"
        role="region"
        aria-label="Quadro do funil de pacientes"
    >
        <KanbanColumn
            v-for="coluna in colunas"
            :key="coluna.id"
            :coluna="coluna"
            :pacientes="pacientesPorColuna[coluna.id] ?? []"
            @card-moved="onCardMoved"
        />
    </div>
</template>
