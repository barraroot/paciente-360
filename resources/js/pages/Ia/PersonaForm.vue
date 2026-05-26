<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useIaStore } from '@/stores/ia.js';

const route = useRoute();
const router = useRouter();
const store = useIaStore();

const isEdit = computed(() => route.name === 'ia.personas.edit');
const personaId = computed(() => route.params.id ?? null);

const form = reactive({
    ai_model_id: null,
    name: '',
    description: '',
    markdown_content: '',
    tone: '',
    objective: '',
    limitations: '',
    initial_message: '',
    fallback_message: '',
    handoff_rules: '',
    model_settings: { temperature: 0.5, max_tokens: 1024 },
});

const errors = ref({});
const loading = ref(false);
const selectedBaseIds = ref([]);
const selectedGuardrailIds = ref([]);

onMounted(async () => {
    loading.value = true;
    try {
        await Promise.all([store.fetchModels(), store.fetchKnowledgeBases(), store.fetchGuardrails()]);
        if (isEdit.value) {
            const persona = await store.fetchPersona(personaId.value);
            Object.assign(form, {
                ai_model_id: persona.ai_model_id,
                name: persona.name,
                description: persona.description ?? '',
                markdown_content: persona.markdown_content ?? '',
                tone: persona.tone ?? '',
                objective: persona.objective ?? '',
                limitations: persona.limitations ?? '',
                initial_message: persona.initial_message ?? '',
                fallback_message: persona.fallback_message ?? '',
                handoff_rules: persona.handoff_rules ?? '',
                model_settings: persona.model_settings ?? { temperature: 0.5, max_tokens: 1024 },
            });
            selectedBaseIds.value = persona.knowledge_base_ids ?? [];
            selectedGuardrailIds.value = persona.guardrail_ids ?? [];
        } else if (store.activeModels.length > 0) {
            form.ai_model_id = store.activeModels[0].id;
        }
    } finally {
        loading.value = false;
    }
});

async function submit() {
    errors.value = {};
    try {
        const payload = { ...form };
        const persona = isEdit.value
            ? await store.updatePersona(personaId.value, payload)
            : await store.createPersona(payload);

        await Promise.all([
            store.syncPersonaKnowledgeBases(persona.id, selectedBaseIds.value),
            store.syncPersonaGuardrails(persona.id, selectedGuardrailIds.value),
        ]);

        router.push({ name: 'ia.personas.index' });
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
        <h1 class="text-xl font-semibold text-gray-900 mb-6">
            {{ isEdit ? 'Editar persona' : 'Nova persona' }}
        </h1>

        <div v-if="loading" class="py-12 text-center text-gray-500">Carregando…</div>

        <form v-else class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-gray-700">Modelo de IA</label>
                <select v-model="form.ai_model_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <option v-for="m in store.activeModels" :key="m.id" :value="m.id">
                        {{ m.name }} ({{ m.provider }})
                    </option>
                </select>
                <p v-if="fieldError('ai_model_id')" class="mt-1 text-xs text-red-600">{{ fieldError('ai_model_id') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nome</label>
                <input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tom de voz</label>
                <input v-model="form.tone" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="cordial e objetivo" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Conteúdo da persona (Markdown)</label>
                <textarea v-model="form.markdown_content" rows="8" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm"></textarea>
                <p v-if="fieldError('markdown_content')" class="mt-1 text-xs text-red-600">{{ fieldError('markdown_content') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mensagem inicial</label>
                    <textarea v-model="form.initial_message" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mensagem de fallback</label>
                    <textarea v-model="form.fallback_message" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Regras de encaminhamento humano</label>
                <textarea v-model="form.handoff_rules" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Bases de conhecimento (RAG)</label>
                <p class="text-xs text-gray-500 mb-2">A persona só consulta as bases ativas selecionadas aqui.</p>
                <p v-if="store.knowledgeBases.length === 0" class="text-sm text-gray-400">
                    Nenhuma base cadastrada ainda.
                </p>
                <div v-else class="space-y-1 rounded-lg border border-gray-200 p-3 max-h-48 overflow-y-auto">
                    <label
                        v-for="base in store.knowledgeBases"
                        :key="base.id"
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input
                            v-model="selectedBaseIds"
                            type="checkbox"
                            :value="base.id"
                            class="rounded border-gray-300 text-indigo-600"
                        />
                        <span>{{ base.name }}</span>
                        <span v-if="!base.is_active" class="text-xs text-gray-400">(inativa)</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Guardrails da clínica</label>
                <p class="text-xs text-gray-500 mb-2">
                    Restrições somadas ao piso de segurança obrigatório. Guardrails inativos não são aplicados.
                </p>
                <p v-if="store.guardrails.length === 0" class="text-sm text-gray-400">
                    Nenhum guardrail cadastrado ainda.
                </p>
                <div v-else class="space-y-1 rounded-lg border border-gray-200 p-3 max-h-48 overflow-y-auto">
                    <label
                        v-for="guardrail in store.guardrails"
                        :key="guardrail.id"
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input
                            v-model="selectedGuardrailIds"
                            type="checkbox"
                            :value="guardrail.id"
                            class="rounded border-gray-300 text-indigo-600"
                        />
                        <span>{{ guardrail.name }}</span>
                        <span v-if="!guardrail.is_active" class="text-xs text-gray-400">(inativo)</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Temperatura</label>
                    <input v-model.number="form.model_settings.temperature" type="number" step="0.1" min="0" max="1" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                    <p v-if="fieldError('model_settings.temperature')" class="mt-1 text-xs text-red-600">{{ fieldError('model_settings.temperature') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Limite de tokens</label>
                    <input v-model.number="form.model_settings.max_tokens" type="number" min="256" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <router-link :to="{ name: 'ia.personas.index' }" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
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
