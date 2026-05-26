<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useIaStore } from '@/stores/ia.js';

const route = useRoute();
const router = useRouter();
const store = useIaStore();

const isEdit = computed(() => route.name === 'ia.guardrails.edit');
const guardrailId = computed(() => route.params.id ?? null);

const CATEGORIES = [
    'seguranca',
    'lgpd',
    'atendimento_medico',
    'encaminhamento',
    'tom_de_voz',
    'restricoes_comerciais',
    'emergencia',
    'privacidade',
];

const form = reactive({
    name: '',
    description: '',
    category: '',
    markdown_content: '',
});

const errors = ref({});
const loading = ref(false);

onMounted(async () => {
    if (!isEdit.value) return;
    loading.value = true;
    try {
        const guardrail = await store.fetchGuardrail(guardrailId.value);
        Object.assign(form, {
            name: guardrail.name,
            description: guardrail.description ?? '',
            category: guardrail.category ?? '',
            markdown_content: guardrail.markdown_content ?? '',
        });
    } finally {
        loading.value = false;
    }
});

async function submit() {
    errors.value = {};
    try {
        const payload = {
            name: form.name,
            description: form.description,
            category: form.category || null,
            markdown_content: form.markdown_content,
        };

        if (isEdit.value) {
            await store.updateGuardrail(guardrailId.value, payload);
        } else {
            await store.createGuardrail(payload);
        }
        router.push({ name: 'ia.guardrails.index' });
    } catch (e) {
        errors.value = e?.response?.data?.errors ?? {};
    }
}

function fieldError(key) {
    return errors.value?.[key]?.[0] ?? null;
}
</script>

<template>
    <div class="p-6 max-w-3xl">
        <h1 class="text-xl font-semibold text-gray-900 mb-1">
            {{ isEdit ? 'Editar guardrail' : 'Novo guardrail' }}
        </h1>
        <p class="text-sm text-gray-500 mb-6">
            Restrições adicionais somadas ao piso de segurança obrigatório da IA.
        </p>

        <div v-if="loading" class="py-12 text-center text-gray-500">Carregando…</div>

        <form v-else class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nome</label>
                <input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Categoria</label>
                <select v-model="form.category" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <option value="">— Nenhuma —</option>
                    <option v-for="c in CATEGORIES" :key="c" :value="c">{{ c }}</option>
                </select>
                <p v-if="fieldError('category')" class="mt-1 text-xs text-red-600">{{ fieldError('category') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Descrição</label>
                <input v-model="form.description" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Conteúdo (Markdown)</label>
                <textarea
                    v-model="form.markdown_content"
                    rows="12"
                    class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm"
                    placeholder="# Restrições&#10;&#10;- Não prometer prazos de resultado.&#10;- Encaminhar reclamações graves a um atendente."
                ></textarea>
                <p v-if="fieldError('markdown_content')" class="mt-1 text-xs text-red-600">{{ fieldError('markdown_content') }}</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <router-link :to="{ name: 'ia.guardrails.index' }" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                    Cancelar
                </router-link>
                <button
                    type="submit"
                    :disabled="store.saving"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ store.saving ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </form>
    </div>
</template>
