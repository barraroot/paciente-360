<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['confirm', 'cancel']);

const { t } = useI18n();

const MOTIVOS = [
    { value: 'sem_interesse', label: t('funil.motivo.sem_interesse') },
    { value: 'sem_retorno', label: t('funil.motivo.sem_retorno') },
    { value: 'preco', label: t('funil.motivo.preco') },
    { value: 'outro', label: t('funil.motivo.outro') },
];

const motivoSelecionado = ref('');
const motivoOutro = ref('');
const erros = ref({});

const isOutro = computed(() => motivoSelecionado.value === 'outro');

// Validação client-side mínima
const motivoOutroValido = computed(() => !isOutro.value || motivoOutro.value.length >= 10);

const podeConfirmar = computed(() => motivoSelecionado.value !== '' && motivoOutroValido.value);

// Resetar ao abrir
watch(
    () => props.open,
    (val) => {
        if (val) {
            motivoSelecionado.value = '';
            motivoOutro.value = '';
            erros.value = {};
        }
    },
);

function confirmar() {
    erros.value = {};

    if (!motivoSelecionado.value) {
        erros.value.motivo = t('funil.motivo_obrigatorio_title');

        return;
    }

    if (isOutro.value && motivoOutro.value.length < 10) {
        erros.value.motivo_outro = t('funil.motivo_outro_label') + ' (mín. 10 caracteres)';

        return;
    }

    emit('confirm', {
        motivo: motivoSelecionado.value,
        motivo_outro: isOutro.value ? motivoOutro.value : undefined,
    });
}

function cancelar() {
    emit('cancel');
}
</script>

<template>
    <ConfirmModal :open="open" :title="t('funil.motivo_obrigatorio_title')" @close="cancelar">
        <!-- Seleção de motivo -->
        <div class="space-y-3">
            <div v-for="motivo in MOTIVOS" :key="motivo.value" class="flex items-center gap-3">
                <input
                    :id="`motivo-${motivo.value}`"
                    v-model="motivoSelecionado"
                    type="radio"
                    :value="motivo.value"
                    class="text-primary-600 focus:ring-primary-500 border-zinc-300"
                />
                <label
                    :for="`motivo-${motivo.value}`"
                    class="text-sm text-foreground cursor-pointer"
                >
                    {{ motivo.label }}
                </label>
            </div>

            <p v-if="erros.motivo" class="text-danger-600 text-xs" role="alert">
                {{ erros.motivo }}
            </p>
        </div>

        <!-- Textarea quando "outro" -->
        <div v-if="isOutro" class="mt-4">
            <label for="motivo-outro-texto" class="block text-sm font-medium text-foreground mb-1">
                {{ t('funil.motivo_outro_label') }}
                <span class="text-danger-500">*</span>
            </label>
            <textarea
                id="motivo-outro-texto"
                v-model="motivoOutro"
                rows="3"
                minlength="10"
                maxlength="255"
                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                :placeholder="`Mínimo 10 caracteres (${motivoOutro.length}/255)`"
            />
            <p v-if="erros.motivo_outro" class="text-danger-600 text-xs mt-1" role="alert">
                {{ erros.motivo_outro }}
            </p>
        </div>

        <!-- Ações personalizadas -->
        <template #actions>
            <button
                type="button"
                class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="cancelar"
            >
                Cancelar
            </button>
            <button
                type="button"
                class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!podeConfirmar"
                @click="confirmar"
            >
                Confirmar
            </button>
        </template>
    </ConfirmModal>
</template>
