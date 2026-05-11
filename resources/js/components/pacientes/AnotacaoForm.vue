<script setup>
import { ref, reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';

const { t } = useI18n();

const props = defineProps({
    pacienteId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['criada']);

// ─── Formulário ───────────────────────────────────────────────────────────────

const form = reactive({
    tipo: 'geral',
    texto: '',
});

const loading = ref(false);
const errors = ref({});
const successMessage = ref('');

const TIPOS = [
    { value: 'geral', label: t('anotacao.tipo.geral') },
    { value: 'clinica', label: t('anotacao.tipo.clinica') },
    { value: 'comportamental', label: t('anotacao.tipo.comportamental') },
    { value: 'financeira', label: t('anotacao.tipo.financeira') },
];

const textoLength = computed(() => form.texto.length);

// ─── Submissão ───────────────────────────────────────────────────────────────

async function salvar() {
    loading.value = true;
    errors.value = {};
    successMessage.value = '';

    try {
        const { data } = await api.post(`/pacientes/${props.pacienteId}/anotacoes`, {
            tipo: form.tipo,
            texto: form.texto,
        });

        successMessage.value = t('anotacao.criada_sucesso');
        form.texto = '';
        form.tipo = 'geral';

        emit('criada', data.data ?? data);
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            errors.value = err.response.data?.errors ?? {};
        } else if (status === 403) {
            errors.value = { _geral: t('common.error_forbidden') };
        } else {
            errors.value = { _geral: t('common.error_generic') };
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="rounded-xl border border-border bg-surface-elevated p-5">
        <h3 class="mb-4 text-sm font-semibold text-foreground">
            {{ t('anotacao.create') }}
        </h3>

        <form class="space-y-4" @submit.prevent="salvar">
            <!-- Tipo -->
            <div>
                <label for="anotacao-tipo" class="mb-1.5 block text-sm font-medium text-foreground">
                    Tipo
                </label>
                <select
                    id="anotacao-tipo"
                    v-model="form.tipo"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                >
                    <option
                        v-for="tipo in TIPOS"
                        :key="tipo.value"
                        :value="tipo.value"
                    >
                        {{ tipo.label }}
                    </option>
                </select>
                <p v-if="errors.tipo" class="mt-1 text-xs text-danger-600">{{ errors.tipo[0] }}</p>
            </div>

            <!-- Texto -->
            <div>
                <label for="anotacao-texto" class="mb-1.5 block text-sm font-medium text-foreground">
                    Texto
                </label>
                <textarea
                    id="anotacao-texto"
                    v-model="form.texto"
                    rows="4"
                    maxlength="5000"
                    :placeholder="t('anotacao.texto_placeholder')"
                    class="w-full resize-y rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                />
                <div class="mt-1 flex justify-between">
                    <p v-if="errors.texto" class="text-xs text-danger-600">{{ errors.texto[0] }}</p>
                    <p class="ml-auto text-xs text-foreground-muted">{{ textoLength }}/5000</p>
                </div>
            </div>

            <!-- Erro geral -->
            <div
                v-if="errors._geral"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-300 bg-danger-50 px-3 py-2 text-sm text-danger-600"
            >
                {{ errors._geral }}
            </div>

            <!-- Sucesso -->
            <div
                v-if="successMessage"
                role="status"
                aria-live="polite"
                class="rounded-lg border border-success-300 bg-success-50 px-3 py-2 text-sm text-success-700"
            >
                {{ successMessage }}
            </div>

            <!-- Ações -->
            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="loading || form.texto.trim() === ''"
                    class="rounded-lg bg-primary-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ loading ? t('common.loading') : t('paciente.form.submit') }}
                </button>
            </div>
        </form>
    </div>
</template>
