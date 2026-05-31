<script setup>
import { ref, onMounted } from 'vue';
import { useIaStore } from '@/stores/ia.js';
import PersonaChannelMatrix from '@/components/Ia/PersonaChannelMatrix.vue';

const store = useIaStore();
const rows = ref([]);
const dirty = ref(false);
const saved = ref(false);

onMounted(async () => {
    await load();
});

async function load() {
    const data = await store.fetchMatrix();
    // Cópia local para edição.
    rows.value = data.map((r) => ({ ...r, channels: { ...r.channels } }));
    dirty.value = false;
}

function onToggle({ ai_persona_id, channel_type, value }) {
    const row = rows.value.find((r) => r.ai_persona_id === ai_persona_id);
    if (row) {
        row.channels[channel_type] = value;
        dirty.value = true;
        saved.value = false;
    }
}

async function save() {
    const cells = [];
    for (const row of rows.value) {
        for (const channelType of ['whatsapp', 'web', 'instagram']) {
            cells.push({
                ai_persona_id: row.ai_persona_id,
                channel_type: channelType,
                is_active: !!row.channels[channelType],
            });
        }
    }
    await store.saveMatrix(cells);
    dirty.value = false;
    saved.value = true;
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-foreground">Matriz Persona × Canal</h1>
                <p class="text-sm text-foreground-muted">
                    Defina quais personas atendem cada canal. A IA atende um canal quando há ao
                    menos uma persona ativa nele.
                </p>
            </div>
            <button
                type="button"
                :disabled="!dirty || store.saving"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                @click="save"
            >
                {{ store.saving ? 'Salvando…' : 'Salvar alterações' }}
            </button>
        </div>

        <p v-if="saved" class="mb-4 rounded-lg bg-success-50 px-4 py-2 text-sm text-success-700">
            Matriz atualizada.
        </p>

        <div v-if="store.loading" class="py-12 text-center text-foreground-muted">Carregando…</div>

        <div
            v-else-if="rows.length === 0"
            class="rounded-lg border border-dashed border-border-strong py-12 text-center text-foreground-muted"
        >
            Nenhuma persona criada. Crie personas para configurar a matriz.
        </div>

        <PersonaChannelMatrix v-else :rows="rows" @toggle="onToggle" />
    </div>
</template>
