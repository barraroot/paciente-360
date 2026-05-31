<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useIaStore } from '@/stores/ia.js';
import MarkdownEditor from '@/components/Ia/MarkdownEditor.vue';

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
        <h1 class="text-xl font-semibold text-foreground mb-1">
            {{ isEdit ? 'Editar guardrail' : 'Novo guardrail' }}
        </h1>
        <p class="text-sm text-foreground-muted mb-6">
            Restrições adicionais somadas ao piso de segurança obrigatório da IA.
        </p>

        <div v-if="loading" class="py-12 text-center text-foreground-muted">Carregando…</div>

        <form v-else class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="guardrail-name" class="block text-sm font-medium text-foreground mb-1">Nome <span class="text-danger-600">*</span></label>
                <input
                    id="guardrail-name"
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="{ 'border-danger-500': fieldError('name') }"
                />
                <p v-if="fieldError('name')" class="mt-1 text-xs text-danger-600">
                    {{ fieldError('name') }}
                </p>
            </div>

            <div>
                <label for="guardrail-category" class="block text-sm font-medium text-foreground mb-1">Categoria</label>
                <select
                    id="guardrail-category"
                    v-model="form.category"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="{ 'border-danger-500': fieldError('category') }"
                >
                    <option value="">— Nenhuma —</option>
                    <option v-for="c in CATEGORIES" :key="c" :value="c">{{ c }}</option>
                </select>
                <p v-if="fieldError('category')" class="mt-1 text-xs text-danger-600">
                    {{ fieldError('category') }}
                </p>
            </div>

            <div>
                <label for="guardrail-description" class="block text-sm font-medium text-foreground mb-1">Descrição</label>
                <input
                    id="guardrail-description"
                    v-model="form.description"
                    type="text"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                />
            </div>

            <MarkdownEditor
                v-model="form.markdown_content"
                label="Conteúdo (Markdown)"
                required
                template-key="guardrail"
                :rows="12"
                :error="fieldError('markdown_content')"
            />

            <div class="flex justify-end gap-3 pt-2">
                <router-link
                    :to="{ name: 'ia.guardrails.index' }"
                    class="rounded-lg px-4 py-2 text-sm text-foreground-muted hover:bg-surface-muted"
                >
                    Cancelar
                </router-link>
                <button
                    type="submit"
                    :disabled="store.saving"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                >
                    {{ store.saving ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </form>
    </div>
</template>
