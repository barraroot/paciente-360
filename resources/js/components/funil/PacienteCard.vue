<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    paciente: {
        type: Object,
        required: true,
    },
});

const router = useRouter();
const { t } = useI18n();

const iniciais = computed(() => {
    const partes = (props.paciente.nome ?? '').split(' ').filter(Boolean);
    if (partes.length === 0) {
        return '?';
    }

    return partes.length === 1
        ? partes[0][0].toUpperCase()
        : (partes[0][0] + partes[partes.length - 1][0]).toUpperCase();
});

function abrirFicha() {
    router.push({ name: 'pacientes.show', params: { id: props.paciente.id } });
}
</script>

<template>
    <div
        class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer select-none"
        role="article"
        :aria-label="paciente.nome"
        @click="abrirFicha"
    >
        <!-- Cabeçalho: avatar + nome -->
        <div class="flex items-center gap-2 mb-2">
            <div
                class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 flex items-center justify-center text-xs font-semibold flex-shrink-0"
                aria-hidden="true"
            >
                {{ iniciais }}
            </div>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                {{ paciente.nome }}
            </span>
        </div>

        <!-- Badges de tags -->
        <div
            v-if="paciente.tags && paciente.tags.length > 0"
            class="flex flex-wrap gap-1 mb-2"
        >
            <span
                v-for="tag in paciente.tags.slice(0, 3)"
                :key="tag.id"
                class="inline-block px-1.5 py-0.5 rounded text-xs font-medium"
                :style="tag.cor ? { backgroundColor: tag.cor + '22', color: tag.cor } : {}"
                :class="!tag.cor ? 'bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300' : ''"
            >
                {{ tag.nome }}
            </span>
            <span
                v-if="paciente.tags.length > 3"
                class="inline-block px-1.5 py-0.5 rounded text-xs bg-zinc-100 dark:bg-zinc-700 text-zinc-500"
            >
                +{{ paciente.tags.length - 3 }}
            </span>
        </div>

        <!-- Info: canal + telefone -->
        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
            <span v-if="paciente.origem" class="capitalize">{{ paciente.origem }}</span>
            <span v-if="paciente.telefone_primario">{{ paciente.telefone_primario }}</span>
        </div>
    </div>
</template>
