<script setup>
/**
 * **T169 (Fase 8 — Lote C US-9.1)** — Formulário de criação (AC-9.1.1).
 */
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useCampaignsStore } from '@/stores/campaignsStore';

const router = useRouter();
const store = useCampaignsStore();

const form = ref({
    name: '',
    channel: 'whatsapp',
    template_id: null,
    scheduled_for: '',
    audience_filters: {
        inactivity_months: 6,
        tags: [],
        age_range: { min: null, max: null },
        gender: '',
    },
});

const tagsInput = ref('');

async function submit() {
    const payload = { ...form.value };
    // Parse tags string → array.
    if (tagsInput.value.trim()) {
        payload.audience_filters.tags = tagsInput.value
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean);
    }
    if (payload.scheduled_for === '') {
        delete payload.scheduled_for;
    }
    // Limpa age_range vazio.
    if (!payload.audience_filters.age_range.min && !payload.audience_filters.age_range.max) {
        delete payload.audience_filters.age_range;
    }
    if (!payload.audience_filters.gender) {
        delete payload.audience_filters.gender;
    }

    try {
        const created = await store.create(payload);
        router.push(`/campaigns/${created.id}`);
    } catch (_) {
        /* erro já tratado */
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Nova campanha</h1>
            <p class="mt-1 text-sm text-foreground-muted">
                Configure segmentação + template. Pré-visualize antes de disparar.
            </p>
        </header>

        <form @submit.prevent="submit" class="space-y-4 rounded border bg-white p-6">
            <div>
                <label class="block text-sm font-medium text-foreground">Nome da campanha</label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    maxlength="200"
                    aria-label="Nome da campanha"
                    class="mt-1 w-full rounded border-border-strong text-sm"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground">Canal</label>
                <select
                    v-model="form.channel"
                    required
                    aria-label="Canal"
                    class="mt-1 w-full rounded border-border-strong text-sm"
                >
                    <option value="whatsapp">WhatsApp</option>
                    <option value="instagram">Instagram</option>
                </select>
                <p class="mt-1 text-xs text-foreground-muted">Q3 — canal único por campanha.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground">Template HSM (ID)</label>
                <input
                    v-model.number="form.template_id"
                    type="number"
                    aria-label="Template HSM (ID)"
                    class="mt-1 w-full rounded border-border-strong text-sm"
                    placeholder="ID do template aprovado pela Meta"
                />
                <p class="mt-1 text-xs text-foreground-muted">
                    Templates devem ter unsubscribe (AC-9.3.3).
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground">Agendar para</label>
                <input
                    v-model="form.scheduled_for"
                    type="datetime-local"
                    aria-label="Agendar para"
                    class="mt-1 w-full rounded border-border-strong text-sm"
                />
                <p class="mt-1 text-xs text-foreground-muted">
                    Vazio = dispatch manual via página de detalhes.
                </p>
            </div>

            <fieldset class="border border-border rounded p-4">
                <legend class="text-sm font-medium text-foreground px-2">Segmentação</legend>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-foreground"
                            >Inatividade (meses sem consulta realizada)</label
                        >
                        <input
                            v-model.number="form.audience_filters.inactivity_months"
                            type="number"
                            min="1"
                            max="60"
                            aria-label="Inatividade em meses sem consulta realizada"
                            class="mt-1 w-full rounded border-border-strong text-sm"
                        />
                        <p class="mt-1 text-xs text-foreground-muted">
                            Q1 — última `ConsultaRealizada` (Fase 5).
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground"
                            >Tags (separadas por vírgula)</label
                        >
                        <input
                            v-model="tagsInput"
                            type="text"
                            aria-label="Tags separadas por vírgula"
                            class="mt-1 w-full rounded border-border-strong text-sm"
                            placeholder="ex.: vacinação, preventivo"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-foreground"
                                >Idade mín.</label
                            >
                            <input
                                v-model.number="form.audience_filters.age_range.min"
                                type="number"
                                min="0"
                                max="120"
                                aria-label="Idade mínima"
                                class="mt-1 w-full rounded border-border-strong text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground"
                                >Idade máx.</label
                            >
                            <input
                                v-model.number="form.audience_filters.age_range.max"
                                type="number"
                                min="0"
                                max="120"
                                aria-label="Idade máxima"
                                class="mt-1 w-full rounded border-border-strong text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground">Gênero</label>
                        <select
                            v-model="form.audience_filters.gender"
                            aria-label="Gênero"
                            class="mt-1 w-full rounded border-border-strong text-sm"
                        >
                            <option value="">Todos</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                            <option value="O">Outro</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <div
                v-if="store.error"
                role="alert"
                class="rounded border border-danger-200 bg-danger-50 p-3 text-sm text-danger-800"
            >
                {{ store.error }}
            </div>

            <div class="flex justify-end gap-2">
                <RouterLink
                    to="/campaigns"
                    class="rounded border px-4 py-2 text-sm hover:bg-surface-muted"
                    >Cancelar</RouterLink
                >
                <button
                    type="submit"
                    :disabled="store.saving"
                    class="rounded bg-success-700 px-4 py-2 text-sm font-medium text-white hover:bg-success-800 disabled:opacity-50"
                >
                    {{ store.saving ? 'Criando…' : 'Criar campanha' }}
                </button>
            </div>
        </form>
    </div>
</template>
