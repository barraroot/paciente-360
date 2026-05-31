<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useIaStore } from '@/stores/ia.js';
import MarkdownEditor from '@/components/Ia/MarkdownEditor.vue';

const route = useRoute();
const router = useRouter();
const store = useIaStore();

const isEdit = computed(() => route.name === 'ia.bases.edit');
const baseId = computed(() => route.params.id ?? null);

const form = reactive({
    name: '',
    description: '',
    markdown_content: '',
    tags: [],
});

const tagsInput = ref('');
const errors = ref({});
const loading = ref(false);

onMounted(async () => {
    if (!isEdit.value) return;
    loading.value = true;
    try {
        const base = await store.fetchKnowledgeBase(baseId.value);
        Object.assign(form, {
            name: base.name,
            description: base.description ?? '',
            markdown_content: base.markdown_content ?? '',
            tags: base.tags ?? [],
        });
        tagsInput.value = (base.tags ?? []).join(', ');
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
            markdown_content: form.markdown_content,
            tags: tagsInput.value
                .split(',')
                .map((t) => t.trim())
                .filter(Boolean),
        };

        if (isEdit.value) {
            await store.updateKnowledgeBase(baseId.value, payload);
        } else {
            await store.createKnowledgeBase(payload);
        }
        router.push({ name: 'ia.bases.index' });
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
            {{ isEdit ? 'Editar base de conhecimento' : 'Nova base de conhecimento' }}
        </h1>
        <p class="text-sm text-foreground-muted mb-6">
            Ao salvar, o conteúdo é reindexado automaticamente para uso pela IA (RAG).
        </p>

        <div v-if="loading" class="py-12 text-center text-foreground-muted">Carregando…</div>

        <form v-else class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="base-name" class="block text-sm font-medium text-foreground mb-1">Nome <span class="text-danger-600">*</span></label>
                <input
                    id="base-name"
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
                <label for="base-description" class="block text-sm font-medium text-foreground mb-1">Descrição</label>
                <input
                    id="base-description"
                    v-model="form.description"
                    type="text"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                />
            </div>

            <MarkdownEditor
                v-model="form.markdown_content"
                label="Conteúdo (Markdown)"
                required
                template-key="knowledge_base"
                :rows="14"
                :error="fieldError('markdown_content')"
            />

            <div>
                <label for="base-tags" class="block text-sm font-medium text-foreground mb-1">Tags (separadas por vírgula)</label>
                <input
                    id="base-tags"
                    v-model="tagsInput"
                    type="text"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    placeholder="faq, horarios"
                />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <router-link
                    :to="{ name: 'ia.bases.index' }"
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
