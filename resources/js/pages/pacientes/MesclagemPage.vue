<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();

// ─── Query params: ?alvo={id}&origem={id}&origem={id} ─────────────────────────

const alvoId = computed(() => Number(route.query.alvo));

const origemIds = computed(() => {
    const raw = route.query.origem;
    if (!raw) { return []; }
    if (Array.isArray(raw)) { return raw.map(Number); }
    return [Number(raw)];
});

// ─── Estado ───────────────────────────────────────────────────────────────────

const pacienteAlvo = ref(null);
const pacientesOrigem = ref([]);
const loading = ref(false);
const loadError = ref(null);

// ─── Resoluções: campo → id do paciente preferido ─────────────────────────────
// { [campo]: pacienteId }

const resolucoes = ref({});

// ─── Campos a comparar ────────────────────────────────────────────────────────

const CAMPOS = [
    { key: 'nome', label: 'Nome' },
    { key: 'cpf', label: 'CPF' },
    { key: 'data_nascimento', label: 'Data de nascimento' },
    { key: 'telefone_primario', label: 'Telefone principal' },
    { key: 'email', label: 'E-mail' },
    { key: 'origem', label: 'Origem' },
    { key: 'origem_detalhe', label: 'Detalhe da origem' },
];

// ─── Carregar pacientes ───────────────────────────────────────────────────────

async function carregarPacientes() {
    if (!alvoId.value) {
        loadError.value = 'Parâmetro "alvo" ausente na URL.';
        return;
    }

    loading.value = true;
    loadError.value = null;

    try {
        const ids = [alvoId.value, ...origemIds.value];
        const respostas = await Promise.all(ids.map((id) => api.get(`/pacientes/${id}`)));

        const todos = respostas.map((r) => r.data.data ?? r.data);

        pacienteAlvo.value = todos.find((p) => p.id === alvoId.value) ?? todos[0];
        pacientesOrigem.value = todos.filter((p) => p.id !== alvoId.value);

        // Pré-seleciona o valor "mais completo" por campo (prioriza não-nulo do alvo).
        CAMPOS.forEach(({ key }) => {
            const valorAlvo = pacienteAlvo.value?.[key];
            const valorOrigem = pacientesOrigem.value[0]?.[key];

            if (valorAlvo && !valorOrigem) {
                resolucoes.value[key] = pacienteAlvo.value.id;
            } else if (!valorAlvo && valorOrigem) {
                resolucoes.value[key] = pacientesOrigem.value[0].id;
            } else {
                // Ambos têm valor; padrão = alvo.
                resolucoes.value[key] = pacienteAlvo.value?.id ?? null;
            }
        });
    } catch (err) {
        loadError.value = err.response?.status === 404
            ? 'Um dos pacientes não foi encontrado.'
            : t('common.error_generic');
    } finally {
        loading.value = false;
    }
}

onMounted(() => carregarPacientes());

// ─── Verifica campos divergentes ──────────────────────────────────────────────

function valorDoPaciente(paciente, campo) {
    return paciente?.[campo] ?? null;
}

function campoDivergente(campo) {
    const valorAlvo = valorDoPaciente(pacienteAlvo.value, campo);
    return pacientesOrigem.value.some((origem) => {
        const valorOrigem = valorDoPaciente(origem, campo);
        return valorOrigem !== valorAlvo;
    });
}

const camposDivergentes = computed(() =>
    CAMPOS.filter(({ key }) => campoDivergente(key)),
);

// ─── Modal de confirmação ─────────────────────────────────────────────────────

const confirmModal = reactive({
    open: false,
    loading: false,
    error: null,
});

function openConfirmModal() {
    confirmModal.open = true;
    confirmModal.loading = false;
    confirmModal.error = null;
}

function closeConfirmModal() {
    confirmModal.open = false;
}

async function executarMesclagem() {
    confirmModal.loading = true;
    confirmModal.error = null;

    try {
        const payload = {
            paciente_alvo_id: alvoId.value,
            pacientes_origem_ids: origemIds.value.length > 0
                ? origemIds.value
                : pacientesOrigem.value.map((p) => p.id),
            resolucoes: resolucoes.value,
        };

        await api.post('/pacientes/mesclagens', payload);
        closeConfirmModal();
        router.push(`/panel/pacientes/${alvoId.value}`);
    } catch (err) {
        confirmModal.error = err.response?.data?.message ?? t('common.error_generic');
        confirmModal.loading = false;
    }
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-5xl">

            <!-- ── Cabeçalho ───────────────────────────────────────────────────── -->
            <div class="mb-6 flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-foreground-muted transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    :aria-label="t('common.back')"
                    @click="router.back()"
                >
                    ←
                </button>
                <h1 class="text-2xl font-bold text-foreground">{{ t('mesclagem.title') }}</h1>
            </div>

            <!-- ── Loading ─────────────────────────────────────────────────────── -->
            <div
                v-if="loading"
                class="flex items-center justify-center py-20"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
            </div>

            <!-- ── Erro ───────────────────────────────────────────────────────── -->
            <div
                v-else-if="loadError"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
            >
                {{ loadError }}
            </div>

            <!-- ── Layout diff ────────────────────────────────────────────────── -->
            <template v-else-if="pacienteAlvo">

                <!-- Identificação dos pacientes -->
                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border-2 border-primary-400 bg-primary-50 p-4">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-primary-700">Paciente alvo (será mantido)</p>
                        <p class="text-sm font-bold text-foreground">{{ pacienteAlvo.nome }}</p>
                        <p class="text-xs text-foreground-muted">CPF: {{ pacienteAlvo.cpf ?? '—' }}</p>
                    </div>
                    <div
                        v-for="origem in pacientesOrigem"
                        :key="origem.id"
                        class="rounded-xl border border-border bg-surface-elevated p-4"
                    >
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-foreground-muted">Origem (será mesclado)</p>
                        <p class="text-sm font-bold text-foreground">{{ origem.nome }}</p>
                        <p class="text-xs text-foreground-muted">CPF: {{ origem.cpf ?? '—' }}</p>
                    </div>
                </div>

                <!-- Campos sem divergência -->
                <div
                    v-if="camposDivergentes.length === 0"
                    class="mb-6 rounded-lg border border-border bg-surface-elevated px-4 py-6 text-center"
                >
                    <p class="text-sm text-foreground-muted">Nenhum campo divergente encontrado. Os dados são idênticos.</p>
                </div>

                <!-- Tabela de divergências -->
                <div
                    v-else
                    class="mb-6 space-y-4"
                >
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground-muted">
                        Campos divergentes — selecione o valor a manter:
                    </h2>

                    <div
                        v-for="{ key, label } in camposDivergentes"
                        :key="key"
                        class="rounded-xl border border-border bg-surface-elevated p-4"
                    >
                        <p class="mb-3 text-sm font-medium text-foreground">{{ label }}</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <!-- Alvo -->
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                                :class="resolucoes[key] === pacienteAlvo.id
                                    ? 'border-primary-500 bg-primary-50'
                                    : 'border-border hover:border-primary-300'"
                            >
                                <input
                                    v-model="resolucoes[key]"
                                    type="radio"
                                    :name="`resolucao-${key}`"
                                    :value="pacienteAlvo.id"
                                    class="mt-0.5 accent-primary-600 h-4 w-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-primary-700 mb-0.5">Alvo</p>
                                    <p class="text-sm text-foreground break-words">
                                        {{ valorDoPaciente(pacienteAlvo, key) ?? '(vazio)' }}
                                    </p>
                                </div>
                            </label>

                            <!-- Origem(s) -->
                            <label
                                v-for="origem in pacientesOrigem"
                                :key="origem.id"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                                :class="resolucoes[key] === origem.id
                                    ? 'border-primary-500 bg-primary-50'
                                    : 'border-border hover:border-primary-300'"
                            >
                                <input
                                    v-model="resolucoes[key]"
                                    type="radio"
                                    :name="`resolucao-${key}`"
                                    :value="origem.id"
                                    class="mt-0.5 accent-primary-600 h-4 w-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-foreground-muted mb-0.5">Origem</p>
                                    <p class="text-sm text-foreground break-words">
                                        {{ valorDoPaciente(origem, key) ?? '(vazio)' }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Ações -->
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="router.back()"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="openConfirmModal"
                    >
                        Confirmar mesclagem
                    </button>
                </div>
            </template>
        </div>

        <!-- ─── Modal de confirmação ──────────────────────────────────────────── -->
        <ConfirmModal
            :open="confirmModal.open"
            :title="'Confirmar mesclagem'"
            @close="closeConfirmModal"
            @confirm="executarMesclagem"
        >
            <p class="text-sm text-foreground-muted">
                Após mesclar, terá 30 dias para reverter. Os dados do paciente origem serão incorporados ao alvo e o registro origem será desativado. Continuar?
            </p>
            <div
                v-if="confirmModal.error"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
            >
                {{ confirmModal.error }}
            </div>
            <template #actions>
                <button
                    type="button"
                    :disabled="confirmModal.loading"
                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="executarMesclagem"
                >
                    {{ confirmModal.loading ? t('common.loading') : t('common.confirm') }}
                </button>
            </template>
        </ConfirmModal>
    </main>
</template>
