<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/lib/api.js';

/**
 * T248 — TagPicker: autocomplete de tags com criação on-the-fly.
 *
 * Props:
 *  - modelValue: Array<number> (IDs das tags selecionadas)
 *  - pacienteId: ?number (quando presente, faz attach/detach via API)
 *
 * Emits:
 *  - update:modelValue
 *  - tag-attached(tag)
 *  - tag-detached(tag)
 */

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    pacienteId: {
        type: Number,
        default: null,
    },
    // Tags já carregadas (objetos completos) para exibição inicial
    tagsCarregadas: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'tag-attached', 'tag-detached']);

// ─── Estado ──────────────────────────────────────────────────────────────────

const query = ref('');
const sugestoes = ref([]);
const loadingSugestoes = ref(false);
const loadingAction = ref(false);
const softLimitAviso = ref(false);
const erro = ref(null);

// Tags aplicadas (objetos completos) — mantém sincronia com modelValue
const tagsAplicadas = ref([...props.tagsCarregadas]);

let debounceTimer = null;

// ─── Computed ─────────────────────────────────────────────────────────────────

const podeAdicionar = computed(() => tagsAplicadas.value.length < 20); // hard limit UI

// ─── Busca de sugestões ───────────────────────────────────────────────────────

watch(query, (novoValor) => {
    clearTimeout(debounceTimer);
    if (novoValor.trim().length < 1) {
        sugestoes.value = [];
        return;
    }
    debounceTimer = setTimeout(() => buscarTags(novoValor.trim()), 300);
});

async function buscarTags(q) {
    loadingSugestoes.value = true;
    try {
        const { data } = await api.get('/tags', { params: { q } });
        const listagem = data.data ?? data ?? [];
        // Filtra tags já aplicadas
        const idsAplicados = tagsAplicadas.value.map((t) => t.id);
        sugestoes.value = listagem.filter((t) => !idsAplicados.includes(t.id));
    } catch {
        sugestoes.value = [];
    } finally {
        loadingSugestoes.value = false;
    }
}

// ─── Aplicar tag existente ────────────────────────────────────────────────────

async function aplicarTag(tag) {
    if (tagsAplicadas.value.some((t) => t.id === tag.id)) { return; }

    softLimitAviso.value = false;
    erro.value = null;
    loadingAction.value = true;

    try {
        if (props.pacienteId) {
            const resp = await api.post(`/pacientes/${props.pacienteId}/tags`, { tag_id: tag.id });
            if (resp.headers['x-soft-warning']) {
                softLimitAviso.value = true;
            }
        }

        tagsAplicadas.value.push(tag);
        emit('update:modelValue', tagsAplicadas.value.map((t) => t.id));
        emit('tag-attached', tag);
        query.value = '';
        sugestoes.value = [];
    } catch (err) {
        erro.value = err.response?.data?.message ?? 'Erro ao aplicar tag.';
    } finally {
        loadingAction.value = false;
    }
}

// ─── Criar nova tag livre on-the-fly ─────────────────────────────────────────

async function criarEAplicar() {
    const nome = query.value.trim();
    if (nome.length < 2) { return; }

    softLimitAviso.value = false;
    erro.value = null;
    loadingAction.value = true;

    try {
        // POST /tags — find-or-create
        const resp = await api.post('/tags', { nome });
        const tag = resp.data?.data ?? resp.data;
        await aplicarTagObjeto(tag);
        query.value = '';
        sugestoes.value = [];
    } catch (err) {
        const status = err.response?.status;
        if (status === 409) {
            // Tag já existe — usa a retornada
            const tag = err.response.data?.data;
            if (tag) { await aplicarTagObjeto(tag); }
        } else {
            erro.value = err.response?.data?.message ?? 'Erro ao criar tag.';
        }
    } finally {
        loadingAction.value = false;
    }
}

async function aplicarTagObjeto(tag) {
    if (tagsAplicadas.value.some((t) => t.id === tag.id)) { return; }

    if (props.pacienteId) {
        const resp = await api.post(`/pacientes/${props.pacienteId}/tags`, { tag_id: tag.id });
        if (resp.headers['x-soft-warning']) {
            softLimitAviso.value = true;
        }
    }

    tagsAplicadas.value.push(tag);
    emit('update:modelValue', tagsAplicadas.value.map((t) => t.id));
    emit('tag-attached', tag);
}

// ─── Remover tag ─────────────────────────────────────────────────────────────

async function removerTag(tag) {
    if (tag.tipo === 'sistemica') { return; } // sistêmicas são read-only no picker

    erro.value = null;
    loadingAction.value = true;

    try {
        if (props.pacienteId) {
            await api.delete(`/pacientes/${props.pacienteId}/tags/${tag.id}`);
        }

        tagsAplicadas.value = tagsAplicadas.value.filter((t) => t.id !== tag.id);
        emit('update:modelValue', tagsAplicadas.value.map((t) => t.id));
        emit('tag-detached', tag);
        softLimitAviso.value = false;
    } catch (err) {
        erro.value = err.response?.data?.message ?? 'Erro ao remover tag.';
    } finally {
        loadingAction.value = false;
    }
}
</script>

<template>
    <div class="space-y-2">
        <!-- Tags aplicadas -->
        <div
            v-if="tagsAplicadas.length > 0"
            class="flex flex-wrap gap-1.5"
        >
            <span
                v-for="tag in tagsAplicadas"
                :key="tag.id"
                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                :class="tag.tipo === 'sistemica'
                    ? 'border-primary-300 bg-primary-50 text-primary-700'
                    : 'border-border bg-surface text-foreground'"
            >
                <span v-if="tag.tipo === 'sistemica'" class="mr-0.5 text-[10px] uppercase tracking-wide opacity-60">Sistema</span>
                {{ tag.nome }}
                <button
                    v-if="tag.tipo !== 'sistemica'"
                    type="button"
                    class="ml-0.5 text-foreground-muted transition hover:text-danger-600 focus-visible:outline-none"
                    :disabled="loadingAction"
                    @click="removerTag(tag)"
                >
                    ×
                </button>
            </span>
        </div>

        <!-- Aviso soft limit -->
        <p
            v-if="softLimitAviso"
            class="text-xs text-warning-600"
        >
            Recomendamos no máximo 10 tags por paciente para manter segmentação clara.
        </p>

        <!-- Input de busca / criação -->
        <div class="relative">
            <input
                v-model="query"
                type="text"
                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                placeholder="Buscar ou criar tag…"
                :disabled="loadingAction"
                @keydown.enter.prevent="sugestoes.length > 0 ? aplicarTag(sugestoes[0]) : criarEAplicar()"
            />

            <!-- Dropdown de sugestões -->
            <ul
                v-if="sugestoes.length > 0"
                class="absolute z-10 mt-1 w-full rounded-lg border border-border bg-surface-elevated shadow-md"
                role="listbox"
            >
                <li
                    v-for="tag in sugestoes"
                    :key="tag.id"
                    class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm text-foreground hover:bg-surface"
                    role="option"
                    @click="aplicarTag(tag)"
                >
                    <span>{{ tag.nome }}</span>
                    <span
                        v-if="tag.tipo === 'sistemica'"
                        class="rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-medium text-primary-700"
                    >
                        Sistema
                    </span>
                </li>
            </ul>

            <!-- Opção criar on-the-fly -->
            <div
                v-else-if="query.trim().length >= 2 && !loadingSugestoes"
                class="absolute z-10 mt-1 w-full rounded-lg border border-border bg-surface-elevated shadow-md"
            >
                <button
                    type="button"
                    class="w-full px-3 py-2 text-left text-sm text-primary-700 hover:bg-surface"
                    @click="criarEAplicar"
                >
                    Criar tag "{{ query.trim() }}"
                </button>
            </div>
        </div>

        <!-- Erro -->
        <p
            v-if="erro"
            class="text-xs text-danger-600"
            role="alert"
        >
            {{ erro }}
        </p>
    </div>
</template>
