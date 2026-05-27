<script setup>
import { computed } from 'vue';
import draggable from 'vuedraggable';
import PacienteCard from './PacienteCard.vue';

const props = defineProps({
    coluna: {
        type: Object,
        required: true,
    },
    pacientes: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['card-moved']);

/**
 * Calcula a nova posição fracionária usando fractional indexing:
 * `posicao = (anterior + proximo) / 2`. Garante valores únicos sem rebalancear
 * a lista inteira; se for o primeiro/último, usa o dobro/metade do vizinho.
 */
function calcularPosicao(lista, novoIndex) {
    const prev = lista[novoIndex - 1];
    const next = lista[novoIndex + 1];

    if (!prev && !next) {
        return 1.0;
    }

    if (!prev) {
        return (next.funil_posicao ?? 1.0) / 2;
    }

    if (!next) {
        return (prev.funil_posicao ?? 1.0) + 1.0;
    }

    return ((prev.funil_posicao ?? 0) + (next.funil_posicao ?? 0)) / 2;
}

function onCardMoved(event) {
    const { added, moved } = event;

    if (added) {
        const { element: paciente, newIndex } = added;
        const lista = localCards.value;
        const novaPosicao = calcularPosicao(lista, newIndex);

        emit('card-moved', {
            paciente,
            colunaDestino: props.coluna,
            posicao: novaPosicao,
        });
    }

    if (moved) {
        const { element: paciente, newIndex } = moved;
        const lista = localCards.value;
        const novaPosicao = calcularPosicao(lista, newIndex);

        emit('card-moved', {
            paciente,
            colunaDestino: props.coluna,
            posicao: novaPosicao,
        });
    }
}

// v-model local para o draggable
const localCards = computed(() =>
    [...props.pacientes].sort((a, b) => (a.funil_posicao ?? 0) - (b.funil_posicao ?? 0)),
);

const colunaColor = computed(() => props.coluna.cor ?? '#4f46e5');

/**
 * Cor de texto legível sobre a cor da coluna (UX-018 / contraste AA).
 * Calcula a luminância relativa (WCAG) do fundo escolhido pelo usuário e
 * devolve texto escuro ou branco — funciona para qualquer cor configurada.
 */
const headerTextColor = computed(() => {
    const hex = colunaColor.value.replace('#', '');
    const full =
        hex.length === 3
            ? hex
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : hex;
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16) / 255);
    const lin = (c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    const luminance = 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
    // contraste com branco = 1.05 / (L + 0.05); usa texto escuro quando branco falha AA
    return 1.05 / (luminance + 0.05) >= 4.5 ? '#ffffff' : '#1f2937';
});
</script>

<template>
    <div class="flex flex-col w-72 flex-shrink-0">
        <!-- Header da coluna -->
        <div
            class="flex items-center justify-between px-3 py-2 rounded-t-lg text-sm font-semibold"
            :style="{ backgroundColor: colunaColor, color: headerTextColor }"
        >
            <span>{{ coluna.nome }}</span>
            <span
                class="bg-black/10 rounded-full px-2 py-0.5 text-xs font-bold"
                :aria-label="`${pacientes.length} pacientes nesta coluna`"
            >
                {{ pacientes.length }}
            </span>
        </div>

        <!-- Lista de cards draggable -->
        <draggable
            v-model="localCards"
            :group="{ name: 'funil', put: true, pull: true }"
            item-key="id"
            class="flex-1 min-h-24 p-2 space-y-2 bg-zinc-50 dark:bg-zinc-900 rounded-b-lg border border-t-0 border-zinc-200 dark:border-zinc-700 overflow-y-auto max-h-[calc(100vh-220px)]"
            ghost-class="opacity-40"
            drag-class="rotate-2 shadow-xl"
            @change="onCardMoved"
        >
            <template #item="{ element }">
                <PacienteCard :paciente="element" />
            </template>
        </draggable>
    </div>
</template>
