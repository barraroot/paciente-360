<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useIaStore } from '@/stores/ia.js';
import { useAuthStore } from '@/stores/auth.js';
import MarkdownEditor from '@/components/Ia/MarkdownEditor.vue';
import PersonaTestChatModal from '@/components/Ia/PersonaTestChatModal.vue';

const route = useRoute();
const router = useRouter();
const store = useIaStore();
const auth = useAuthStore();

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
    voice_id: null,
});

// Fase 18 (US5, T162) — voz TTS escolhida na Persona. Catálogo carregado em mount.
const selectedVoice = computed(
    () => store.voices.find((v) => v.id === Number(form.voice_id)) ?? null,
);

const errors = ref({});
const loading = ref(false);
const selectedBaseIds = ref([]);
const selectedGuardrailIds = ref([]);

// Fase 18 (US6, T188 + FR-039) — Testar persona em edição (rascunho).
// Disponível somente quando estamos editando uma persona existente, porque
// o backend exige `persona` na rota (`/ai/personas/{persona}/test-sessions`).
// O `persona_draft` carrega o formulário ATUAL — pode divergir do persistido.
const canTestPersona = computed(() => auth.hasPermission('ai.persona.test'));
const canTestDraft = computed(
    () =>
        canTestPersona.value &&
        isEdit.value &&
        personaId.value !== null &&
        (form.name?.trim().length ?? 0) >= 2 &&
        (form.markdown_content?.trim().length ?? 0) > 0,
);
const testDraftOpen = ref(false);
const draftPersona = ref(null);
const draftPayload = ref(null);

function openDraftTest() {
    if (!canTestDraft.value) return;
    draftPayload.value = {
        name: form.name,
        markdown_content: form.markdown_content,
        tone: form.tone ?? '',
        objective: form.objective ?? '',
        limitations: form.limitations ?? '',
        initial_message: form.initial_message ?? '',
        fallback_message: form.fallback_message ?? '',
        handoff_rules: form.handoff_rules ?? '',
        model_settings: form.model_settings ?? {},
    };
    draftPersona.value = {
        id: Number(personaId.value),
        name: form.name || 'Persona em edição',
    };
    testDraftOpen.value = true;
}

function closeDraftTest() {
    testDraftOpen.value = false;
    draftPersona.value = null;
    draftPayload.value = null;
}

onMounted(async () => {
    loading.value = true;
    try {
        await Promise.all([
            store.fetchModels(),
            store.fetchKnowledgeBases(),
            store.fetchGuardrails(),
            store.fetchVoices('pt-BR'),
        ]);
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
                voice_id: persona.voice_id ?? null,
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
        <h1 class="text-xl font-semibold text-foreground mb-6">
            {{ isEdit ? 'Editar persona' : 'Nova persona' }}
        </h1>

        <div v-if="loading" class="py-12 text-center text-foreground-muted">Carregando…</div>

        <form v-else class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="persona-ai-model" class="block text-sm font-medium text-foreground mb-1"
                    >Modelo de IA</label
                >
                <select
                    id="persona-ai-model"
                    v-model="form.ai_model_id"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="{ 'border-danger-500': fieldError('ai_model_id') }"
                >
                    <option v-for="m in store.activeModels" :key="m.id" :value="m.id">
                        {{ m.name }} ({{ m.provider }})
                    </option>
                </select>
                <p v-if="fieldError('ai_model_id')" class="mt-1 text-xs text-danger-600">
                    {{ fieldError('ai_model_id') }}
                </p>
            </div>

            <div>
                <label for="persona-name" class="block text-sm font-medium text-foreground mb-1"
                    >Nome <span class="text-danger-600">*</span></label
                >
                <input
                    id="persona-name"
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
                <label for="persona-tone" class="block text-sm font-medium text-foreground mb-1"
                    >Tom de voz</label
                >
                <input
                    id="persona-tone"
                    v-model="form.tone"
                    type="text"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    placeholder="cordial e objetivo"
                />
            </div>

            <MarkdownEditor
                v-model="form.markdown_content"
                label="Conteúdo da persona (Markdown)"
                required
                template-key="persona"
                :rows="10"
                :error="fieldError('markdown_content')"
            />

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label
                        for="persona-initial-message"
                        class="block text-sm font-medium text-foreground mb-1"
                        >Mensagem inicial</label
                    >
                    <textarea
                        id="persona-initial-message"
                        v-model="form.initial_message"
                        rows="2"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    ></textarea>
                </div>
                <div>
                    <label
                        for="persona-fallback-message"
                        class="block text-sm font-medium text-foreground mb-1"
                        >Mensagem de fallback</label
                    >
                    <textarea
                        id="persona-fallback-message"
                        v-model="form.fallback_message"
                        rows="2"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    ></textarea>
                </div>
            </div>

            <div>
                <label
                    for="persona-handoff-rules"
                    class="block text-sm font-medium text-foreground mb-1"
                    >Regras de encaminhamento humano</label
                >
                <textarea
                    id="persona-handoff-rules"
                    v-model="form.handoff_rules"
                    rows="3"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                ></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground"
                    >Bases de conhecimento (RAG)</label
                >
                <p class="text-xs text-foreground-muted mb-2">
                    A persona só consulta as bases ativas selecionadas aqui.
                </p>
                <p v-if="store.knowledgeBases.length === 0" class="text-sm text-foreground-subtle">
                    Nenhuma base cadastrada ainda.
                </p>
                <div
                    v-else
                    class="space-y-1 rounded-lg border border-border p-3 max-h-48 overflow-y-auto"
                >
                    <label
                        v-for="base in store.knowledgeBases"
                        :key="base.id"
                        class="flex items-center gap-2 text-sm text-foreground"
                    >
                        <input
                            v-model="selectedBaseIds"
                            type="checkbox"
                            :value="base.id"
                            class="rounded border-border text-primary-600"
                        />
                        <span>{{ base.name }}</span>
                        <span v-if="!base.is_active" class="text-xs text-foreground-subtle"
                            >(inativa)</span
                        >
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground"
                    >Guardrails da clínica</label
                >
                <p class="text-xs text-foreground-muted mb-2">
                    Restrições somadas ao piso de segurança obrigatório. Guardrails inativos não são
                    aplicados.
                </p>
                <p v-if="store.guardrails.length === 0" class="text-sm text-foreground-subtle">
                    Nenhum guardrail cadastrado ainda.
                </p>
                <div
                    v-else
                    class="space-y-1 rounded-lg border border-border p-3 max-h-48 overflow-y-auto"
                >
                    <label
                        v-for="guardrail in store.guardrails"
                        :key="guardrail.id"
                        class="flex items-center gap-2 text-sm text-foreground"
                    >
                        <input
                            v-model="selectedGuardrailIds"
                            type="checkbox"
                            :value="guardrail.id"
                            class="rounded border-border text-primary-600"
                        />
                        <span>{{ guardrail.name }}</span>
                        <span v-if="!guardrail.is_active" class="text-xs text-foreground-subtle"
                            >(inativo)</span
                        >
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label
                        for="persona-temperature"
                        class="block text-sm font-medium text-foreground mb-1"
                        >Temperatura</label
                    >
                    <input
                        id="persona-temperature"
                        v-model.number="form.model_settings.temperature"
                        type="number"
                        step="0.1"
                        min="0"
                        max="1"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="{ 'border-danger-500': fieldError('model_settings.temperature') }"
                    />
                    <p
                        v-if="fieldError('model_settings.temperature')"
                        class="mt-1 text-xs text-danger-600"
                    >
                        {{ fieldError('model_settings.temperature') }}
                    </p>
                </div>
                <div>
                    <label
                        for="persona-max-tokens"
                        class="block text-sm font-medium text-foreground mb-1"
                        >Limite de tokens</label
                    >
                    <input
                        id="persona-max-tokens"
                        v-model.number="form.model_settings.max_tokens"
                        type="number"
                        min="256"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    />
                </div>
            </div>

            <!-- Fase 18 (US5, T162, Q-clarify-4=B) — voz TTS por Persona -->
            <div>
                <label for="persona-voice" class="block text-sm font-medium text-foreground mb-1">
                    Voz TTS
                </label>
                <p class="text-xs text-foreground-muted mb-2">
                    Voz usada quando o paciente pedir resposta em áudio. Se nada for escolhido, a
                    voz padrão do tenant é usada.
                </p>
                <select
                    id="persona-voice"
                    v-model.number="form.voice_id"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                    :class="{ 'border-danger-500': fieldError('voice_id') }"
                >
                    <option :value="null">— Usar voz padrão do tenant —</option>
                    <option v-for="v in store.voices" :key="v.id" :value="v.id">
                        {{ v.display_name }}
                        ({{
                            v.gender === 'f'
                                ? 'feminina'
                                : v.gender === 'm'
                                  ? 'masculina'
                                  : 'neutra'
                        }}, {{ v.tone }})
                        <template v-if="v.is_system_default">— padrão do sistema</template>
                    </option>
                </select>
                <p v-if="fieldError('voice_id')" class="mt-1 text-xs text-danger-600">
                    {{ fieldError('voice_id') }}
                </p>

                <div
                    v-if="selectedVoice"
                    class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface-subtle p-3"
                >
                    <span
                        class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700"
                    >
                        {{
                            selectedVoice.gender === 'f'
                                ? 'Feminina'
                                : selectedVoice.gender === 'm'
                                  ? 'Masculina'
                                  : 'Neutra'
                        }}
                    </span>
                    <span
                        class="inline-flex items-center rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-foreground"
                    >
                        Tom: {{ selectedVoice.tone }}
                    </span>
                    <span
                        v-if="selectedVoice.is_system_default"
                        class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                    >
                        Padrão do sistema
                    </span>
                    <audio
                        v-if="selectedVoice.preview_url"
                        :src="selectedVoice.preview_url"
                        controls
                        preload="none"
                        class="h-8 w-full sm:w-auto sm:flex-1"
                        aria-label="Prévia da voz selecionada"
                    ></audio>
                    <span v-else class="text-xs text-foreground-muted">
                        Sem prévia disponível.
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                <button
                    v-if="isEdit && canTestPersona"
                    type="button"
                    :disabled="!canTestDraft"
                    :title="
                        canTestDraft
                            ? 'Abre o chat sandbox usando o formulário atual (não salva)'
                            : 'Preencha nome e conteúdo para testar'
                    "
                    class="rounded-lg border border-primary-200 bg-white px-4 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50 disabled:opacity-50"
                    @click="openDraftTest"
                >
                    Testar persona em edição
                </button>
                <router-link
                    :to="{ name: 'ia.personas.index' }"
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

        <PersonaTestChatModal
            v-if="draftPersona"
            :open="testDraftOpen"
            :persona="draftPersona"
            :persona-draft="draftPayload"
            :use-draft="true"
            @close="closeDraftTest"
        />
    </div>
</template>
