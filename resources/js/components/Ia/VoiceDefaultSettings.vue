<script setup>
import { computed, onMounted, ref } from 'vue';
import { useIaStore } from '@/stores/ia.js';

/**
 * **T163 (Fase 18 — US5, FR-037)** — tela de configurações de voz/TTS do
 * tenant. Define:
 *   - voz padrão (`default_voice_id`) usada quando Persona não tem voz própria;
 *   - master switch `tts_enabled` (FR-037: tenant pode desativar TTS todo).
 *
 * Cadeia de resolução implementada no backend (PersonaVoiceResolverService):
 *   Persona.voice_id → tenant.default_voice_id → entrada is_system_default=true.
 */

const store = useIaStore();

const defaultVoiceId = ref(null);
const ttsEnabled = ref(true);
const initialLoaded = ref(false);
const savedAt = ref(null);

const selectedVoice = computed(
    () => store.voices.find((v) => v.id === Number(defaultVoiceId.value)) ?? null,
);

const systemDefaultVoice = computed(() => store.voices.find((v) => v.is_system_default) ?? null);

const isDirty = computed(() => {
    const current = store.voiceSettings ?? {};
    const sameVoice = (current.default_voice_id ?? null) === (defaultVoiceId.value ?? null);
    const sameToggle = (current.tts_enabled ?? true) === ttsEnabled.value;
    return !(sameVoice && sameToggle);
});

onMounted(async () => {
    await Promise.all([store.fetchVoices('pt-BR'), store.fetchVoiceSettings()]);
    defaultVoiceId.value = store.voiceSettings?.default_voice_id ?? null;
    ttsEnabled.value = store.voiceSettings?.tts_enabled ?? true;
    initialLoaded.value = true;
});

async function save() {
    await store.saveVoiceSettings({
        default_voice_id: defaultVoiceId.value,
        tts_enabled: ttsEnabled.value,
    });
    savedAt.value = new Date();
    // Re-sincroniza estado local com a verdade do backend (caso default_voice_id
    // tenha vindo enriquecido com default_voice).
    defaultVoiceId.value = store.voiceSettings?.default_voice_id ?? null;
    ttsEnabled.value = store.voiceSettings?.tts_enabled ?? true;
}

function genderLabel(g) {
    return g === 'f' ? 'Feminina' : g === 'm' ? 'Masculina' : 'Neutra';
}
</script>

<template>
    <div class="max-w-3xl p-6">
        <h1 class="mb-1 text-xl font-semibold text-foreground">Voz padrão do tenant</h1>
        <p class="mb-6 text-sm text-foreground-muted">
            Voz usada quando uma Persona não tem voz própria configurada. Se nada for escolhido, o
            padrão do sistema é aplicado.
        </p>

        <div v-if="store.loading && !initialLoaded" class="py-12 text-center text-foreground-muted">
            Carregando…
        </div>

        <div v-else-if="store.error" class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700">
            {{ store.error }}
        </div>

        <form v-else class="space-y-6" @submit.prevent="save">
            <!-- TTS master switch -->
            <div class="rounded-lg border border-border bg-white p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <label for="tts-enabled" class="text-sm font-medium text-foreground">
                            Permitir respostas em áudio (TTS)
                        </label>
                        <p class="mt-1 text-xs text-foreground-muted">
                            Quando desligado, todo turno da IA responde em texto, mesmo se o
                            paciente pedir áudio. Útil em clínicas com fluxo 100% por texto.
                        </p>
                    </div>
                    <label
                        class="relative inline-flex cursor-pointer items-center"
                        :title="ttsEnabled ? 'Clique para desativar' : 'Clique para ativar'"
                    >
                        <input
                            id="tts-enabled"
                            v-model="ttsEnabled"
                            type="checkbox"
                            class="peer sr-only"
                        />
                        <span
                            class="h-6 w-11 rounded-full bg-surface-muted transition peer-checked:bg-primary-600"
                        ></span>
                        <span
                            class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5"
                        ></span>
                    </label>
                </div>
            </div>

            <!-- Default voice select -->
            <div>
                <label
                    for="tenant-default-voice"
                    class="block text-sm font-medium text-foreground mb-1"
                >
                    Voz padrão
                </label>
                <p class="mb-2 text-xs text-foreground-muted">
                    Personas sem voz própria usam esta. Se nada for escolhido, cai no padrão do
                    sistema.
                </p>
                <select
                    id="tenant-default-voice"
                    v-model.number="defaultVoiceId"
                    :disabled="!ttsEnabled"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50"
                >
                    <option :value="null">
                        — Usar padrão do sistema
                        <template v-if="systemDefaultVoice">
                            ({{ systemDefaultVoice.display_name }})
                        </template>
                        —
                    </option>
                    <option v-for="v in store.voices" :key="v.id" :value="v.id">
                        {{ v.display_name }} ({{ genderLabel(v.gender) }}, {{ v.tone }})
                        <template v-if="v.is_system_default">— padrão do sistema</template>
                    </option>
                </select>

                <div
                    v-if="selectedVoice"
                    class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface-subtle p-3"
                >
                    <span
                        class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700"
                    >
                        {{ genderLabel(selectedVoice.gender) }}
                    </span>
                    <span
                        class="inline-flex items-center rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-foreground"
                    >
                        Tom: {{ selectedVoice.tone }}
                    </span>
                    <audio
                        v-if="selectedVoice.preview_url"
                        :src="selectedVoice.preview_url"
                        controls
                        preload="none"
                        class="h-8 w-full sm:w-auto sm:flex-1"
                        aria-label="Prévia da voz padrão"
                    ></audio>
                    <span v-else class="text-xs text-foreground-muted">Sem prévia disponível.</span>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 border-t border-border pt-4">
                <p v-if="savedAt" class="text-xs text-foreground-muted" aria-live="polite">
                    Salvo às {{ savedAt.toLocaleTimeString('pt-BR') }}.
                </p>
                <span v-else></span>
                <button
                    type="submit"
                    :disabled="store.saving || !isDirty"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                >
                    {{ store.saving ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </form>
    </div>
</template>
