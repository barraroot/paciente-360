<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';
import { useAuthStore } from '@/stores/auth.js';
import api from '@/lib/api.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import PacienteTimelineTab from '@/components/pacientes/PacienteTimelineTab.vue';
import AnotacaoForm from '@/components/pacientes/AnotacaoForm.vue';
import TagPicker from '@/components/pacientes/TagPicker.vue';

const { t } = useI18n();
const { formatDate, formatDateTime } = useI18nFormat();
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const pacienteId = computed(() => Number(route.params.id));

// ─── Estado ───────────────────────────────────────────────────────────────────

const paciente = ref(null);
const loading = ref(false);
const loadError = ref(null);

// ─── Tabs: state local ────────────────────────────────────────────────────────
// tabs.detalhes | tabs.timeline | tabs.anotacoes | tabs.mesclagens

const activeTab = ref('tabs.detalhes');

// ─── Carregar paciente ────────────────────────────────────────────────────────

async function carregarPaciente() {
    loading.value = true;
    loadError.value = null;
    try {
        const { data } = await api.get(`/pacientes/${pacienteId.value}`);
        paciente.value = data.data ?? data;
    } catch (err) {
        if (err.response?.status === 404) {
            loadError.value = '404';
        } else {
            loadError.value = 'generic';
        }
    } finally {
        loading.value = false;
    }
}

onMounted(() => carregarPaciente());

// ─── Status badge ─────────────────────────────────────────────────────────────

function statusLabel(status) {
    const map = {
        lead: t('paciente.status.lead'),
        ativo: t('paciente.status.ativo'),
        inativo: t('paciente.status.inativo'),
        bloqueado: t('paciente.status.bloqueado'),
    };
    return map[status] ?? status;
}

function statusClass(status) {
    const map = {
        lead: 'bg-warning-50 text-warning-700',
        ativo: 'bg-success-50 text-success-700',
        inativo: 'bg-surface text-foreground-muted',
        bloqueado: 'bg-danger-50 text-danger-700',
    };
    return map[status] ?? 'bg-surface text-foreground-muted';
}

// ─── Alterar status ───────────────────────────────────────────────────────────

const statusModal = reactive({
    open: false,
    novoStatus: '',
    motivo: '',
    loading: false,
    error: null,
});

const TRANSICOES_VALIDAS = {
    lead: ['ativo', 'inativo'],
    ativo: ['inativo', 'bloqueado'],
    inativo: ['ativo', 'bloqueado'],
    bloqueado: ['inativo'],
};

const transicoesDisponiveis = computed(() => {
    if (!paciente.value) { return []; }
    return TRANSICOES_VALIDAS[paciente.value.status] ?? [];
});

function openStatusModal(novoStatus) {
    statusModal.open = true;
    statusModal.novoStatus = novoStatus;
    statusModal.motivo = '';
    statusModal.loading = false;
    statusModal.error = null;
}

function closeStatusModal() {
    statusModal.open = false;
}

async function confirmarAlteracaoStatus() {
    statusModal.loading = true;
    statusModal.error = null;
    try {
        const payload = { status: statusModal.novoStatus };
        if (statusModal.motivo) { payload.motivo = statusModal.motivo; }
        await api.patch(`/pacientes/${pacienteId.value}/status`, payload);
        closeStatusModal();
        await carregarPaciente();
    } catch (err) {
        const status = err.response?.status;
        if (status === 403) {
            statusModal.error = 'Transição requer permissão de Admin Clínica.';
        } else if (status === 422) {
            statusModal.error = err.response?.data?.message ?? t('common.error_generic');
        } else {
            statusModal.error = t('common.error_generic');
        }
        statusModal.loading = false;
    }
}

// ─── Anonimizar ───────────────────────────────────────────────────────────────

const anonimizarModal = reactive({
    open: false,
    loading: false,
    error: null,
});

function openAnonimizarModal() {
    anonimizarModal.open = true;
    anonimizarModal.loading = false;
    anonimizarModal.error = null;
}

function closeAnonimizarModal() {
    anonimizarModal.open = false;
}

async function confirmarAnonimizar() {
    anonimizarModal.loading = true;
    anonimizarModal.error = null;
    try {
        await api.post(`/pacientes/${pacienteId.value}/anonimizar`);
        router.push('/panel/pacientes');
    } catch (err) {
        const status = err.response?.status;
        if (status === 403) {
            anonimizarModal.error = 'Sem permissão. Apenas Admin Clínica pode anonimizar.';
        } else {
            anonimizarModal.error = t('common.error_generic');
        }
        anonimizarModal.loading = false;
    }
}

// ─── Anotações ───────────────────────────────────────────────────────────────

const anotacoes = ref([]);
const anotacoesLoading = ref(false);
const anotacoesError = ref(null);
const retratacaoAberta = ref(null);
const retratacaoTexto = ref('');
const retratacaoLoading = ref(false);
const retratacaoError = ref(null);

async function carregarAnotacoes() {
    if (!paciente.value) { return; }
    anotacoesLoading.value = true;
    anotacoesError.value = null;
    try {
        const { data } = await api.get(`/pacientes/${pacienteId.value}/anotacoes`);
        anotacoes.value = data.data ?? data ?? [];
    } catch {
        anotacoesError.value = t('common.error_generic');
    } finally {
        anotacoesLoading.value = false;
    }
}

function onAnotacaoCriada() {
    carregarAnotacoes();
}

function abrirRetratacao(id) {
    retratacaoAberta.value = id;
    retratacaoTexto.value = '';
    retratacaoError.value = null;
}

function fecharRetratacao() {
    retratacaoAberta.value = null;
}

async function enviarRetratacao(anotacaoId) {
    retratacaoLoading.value = true;
    retratacaoError.value = null;
    try {
        await api.post(`/pacientes/${pacienteId.value}/anotacoes/${anotacaoId}/retratacao`, {
            texto: retratacaoTexto.value,
        });
        fecharRetratacao();
        await carregarAnotacoes();
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            retratacaoError.value = err.response?.data?.message ?? t('common.error_generic');
        } else {
            retratacaoError.value = t('common.error_generic');
        }
    } finally {
        retratacaoLoading.value = false;
    }
}

function tipoAnotacaoBadgeClass(tipo) {
    const map = {
        geral: 'bg-surface border-border text-foreground-muted',
        clinica: 'bg-primary-50 border-primary-200 text-primary-700',
        comportamental: 'bg-warning-50 border-warning-200 text-warning-700',
        financeira: 'bg-success-50 border-success-200 text-success-700',
    };
    return map[tipo] ?? 'bg-surface border-border text-foreground-muted';
}

// ─── Mesclagens ───────────────────────────────────────────────────────────────

const mesclagens = ref([]);
const mesclagensLoading = ref(false);
const mesclagensError = ref(null);

async function carregarMesclagens() {
    if (mesclagens.value.length > 0) { return; }
    mesclagensLoading.value = true;
    mesclagensError.value = null;
    try {
        const { data } = await api.get(`/pacientes/${pacienteId.value}/mesclagens`);
        mesclagens.value = data.data ?? data ?? [];
    } catch {
        // Endpoint pode não existir ainda; trata silenciosamente.
        mesclagens.value = [];
    } finally {
        mesclagensLoading.value = false;
    }
}

const reverterModal = reactive({
    open: false,
    mesclagemId: null,
    loading: false,
    error: null,
});

function openReverterModal(mesclagemId) {
    reverterModal.open = true;
    reverterModal.mesclagemId = mesclagemId;
    reverterModal.loading = false;
    reverterModal.error = null;
}

function closeReverterModal() {
    reverterModal.open = false;
}

async function confirmarReverter() {
    reverterModal.loading = true;
    reverterModal.error = null;
    try {
        await api.post(`/pacientes/mesclagens/${reverterModal.mesclagemId}/reverter`);
        closeReverterModal();
        await carregarMesclagens();
    } catch (err) {
        const status = err.response?.status;
        if (status === 410) {
            reverterModal.error = 'Janela de reversão de 30 dias expirou.';
        } else if (status === 422) {
            reverterModal.error = 'Esta mesclagem já foi revertida.';
        } else {
            reverterModal.error = t('common.error_generic');
        }
        reverterModal.loading = false;
    }
}

// ─── Convênio principal ───────────────────────────────────────────────────────

function convenioLabel(p) {
    const principal = (p.convenios ?? []).find((c) => c.papel === 'principal');
    return principal?.convenio?.nome ?? '—';
}

// ─── Tags interativas (TagPicker) ─────────────────────────────────────────────

const tagIds = computed({
    get: () => (paciente.value?.tags ?? []).map((t) => t.id),
    set: () => {}, // gerenciado pelo TagPicker via eventos
});

const tagsCarregadas = computed(() => paciente.value?.tags ?? []);

// ─── Convênios interativos ────────────────────────────────────────────────────

const catalogoConvenios = ref([]);
const convenioModal = reactive({
    open: false,
    convenioSelecionadoId: '',
    papel: 'principal',
    carteirinha: '',
    loading: false,
    erro: null,
});

async function carregarCatalogoConvenios() {
    try {
        const { data } = await api.get('/convenios');
        catalogoConvenios.value = data.data ?? data ?? [];
    } catch {
        catalogoConvenios.value = [];
    }
}

function openConvenioModal() {
    convenioModal.open = true;
    convenioModal.convenioSelecionadoId = '';
    convenioModal.papel = 'principal';
    convenioModal.carteirinha = '';
    convenioModal.loading = false;
    convenioModal.erro = null;
    if (catalogoConvenios.value.length === 0) { carregarCatalogoConvenios(); }
}

function closeConvenioModal() {
    convenioModal.open = false;
}

async function vincularConvenio() {
    if (!convenioModal.convenioSelecionadoId) {
        convenioModal.erro = 'Selecione um convênio.';
        return;
    }

    const totalConvenios = (paciente.value?.convenios ?? []).length;
    if (totalConvenios >= 2) {
        convenioModal.erro = 'Máximo de 2 convênios por paciente.';
        return;
    }

    convenioModal.loading = true;
    convenioModal.erro = null;

    try {
        await api.post(`/pacientes/${pacienteId.value}/convenios`, {
            convenio_id: Number(convenioModal.convenioSelecionadoId),
            papel: convenioModal.papel,
            numero_carteirinha: convenioModal.carteirinha || null,
        });
        closeConvenioModal();
        await carregarPaciente();
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            convenioModal.erro = err.response?.data?.message ?? 'Dados inválidos.';
        } else {
            convenioModal.erro = 'Erro ao vincular convênio.';
        }
        convenioModal.loading = false;
    }
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-5xl">

            <!-- ── Botão voltar ─────────────────────────────────────────────────── -->
            <button
                type="button"
                class="mb-4 inline-flex items-center gap-1 text-sm text-foreground-muted transition hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="router.push('/panel/pacientes')"
            >
                ← {{ t('common.back') }}
            </button>

            <!-- ── Loading ─────────────────────────────────────────────────────── -->
            <div
                v-if="loading"
                class="flex items-center justify-center py-20"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
            </div>

            <!-- ── 404 / erro ──────────────────────────────────────────────────── -->
            <div
                v-else-if="loadError"
                class="flex flex-col items-center justify-center gap-4 py-20 text-center"
            >
                <p class="text-foreground-muted text-sm">
                    <template v-if="loadError === '404'">
                        Paciente não encontrado ou anonimizado.
                    </template>
                    <template v-else>
                        {{ t('common.error_generic') }}
                    </template>
                </p>
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="router.push('/panel/pacientes')"
                >
                    {{ t('common.back') }} à lista
                </button>
            </div>

            <!-- ── Ficha do paciente ───────────────────────────────────────────── -->
            <template v-else-if="paciente">

                <!-- Header -->
                <div class="mb-6 rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-center gap-4">
                            <!-- Avatar placeholder -->
                            <div
                                class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 text-xl font-bold"
                                aria-hidden="true"
                            >
                                {{ paciente.nome?.charAt(0)?.toUpperCase() ?? '?' }}
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-foreground">{{ paciente.nome }}</h1>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="statusClass(paciente.status)"
                                    >
                                        {{ statusLabel(paciente.status) }}
                                    </span>
                                    <span v-if="paciente.idade" class="text-sm text-foreground-muted">
                                        {{ paciente.idade }} anos
                                    </span>
                                    <span v-if="paciente.profissional_responsavel" class="text-sm text-foreground-muted">
                                        · {{ paciente.profissional_responsavel.nome }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Ações -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="auth.hasPermission('paciente.update')"
                                type="button"
                                class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="router.push(`/panel/pacientes/${pacienteId}/editar`)"
                            >
                                {{ t('users.list.action_edit') }}
                            </button>
                            <button
                                v-if="auth.hasRole('admin-clinica')"
                                type="button"
                                class="rounded-lg border border-danger-300 px-4 py-2 text-sm font-medium text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openAnonimizarModal"
                            >
                                {{ t('paciente.show.anonimizar_cta') }}
                            </button>
                        </div>
                    </div>

                    <!-- Alterar status -->
                    <div
                        v-if="transicoesDisponiveis.length > 0 && auth.hasPermission('paciente.update')"
                        class="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4"
                    >
                        <span class="text-xs font-medium text-foreground-muted">{{ t('paciente.show.alterar_status_cta') }}:</span>
                        <button
                            v-for="novoStatus in transicoesDisponiveis"
                            :key="novoStatus"
                            type="button"
                            class="rounded-full border border-border px-3 py-0.5 text-xs font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="openStatusModal(novoStatus)"
                        >
                            → {{ statusLabel(novoStatus) }}
                        </button>
                    </div>
                </div>

                <!-- ── Tabs ──────────────────────────────────────────────────── -->
                <div class="mb-6 flex gap-0 border-b border-border" role="tablist">
                    <button
                        v-for="tab in ['tabs.detalhes', 'tabs.timeline', 'tabs.anotacoes', 'tabs.mesclagens']"
                        :key="tab"
                        role="tab"
                        :aria-selected="activeTab === tab"
                        :aria-controls="`panel-${tab}`"
                        class="px-4 py-2.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="activeTab === tab
                            ? 'border-b-2 border-primary-700 text-primary-700'
                            : 'text-foreground-muted hover:text-foreground'"
                        @click="activeTab = tab; if (tab === 'tabs.mesclagens') { carregarMesclagens(); } if (tab === 'tabs.anotacoes') { carregarAnotacoes(); }"
                    >
                        {{ t(`paciente.show.${tab}`) }}
                    </button>
                </div>

                <!-- ── Tab: Detalhes ───────────────────────────────────────── -->
                <div
                    v-if="activeTab === 'tabs.detalhes'"
                    :id="`panel-tabs.detalhes`"
                    role="tabpanel"
                    class="space-y-6"
                >
                    <div class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">
                        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">Dados pessoais</h2>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.nome') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground font-medium">{{ paciente.nome }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.cpf') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.cpf ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.documento_estrangeiro') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.documento_estrangeiro ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.data_nascimento') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">
                                    {{ paciente.data_nascimento ? formatDate(paciente.data_nascimento + 'T00:00:00') : '—' }}
                                    <span v-if="paciente.idade" class="ml-1 text-xs text-foreground-muted">({{ paciente.idade }} anos)</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">
                        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">Contato</h2>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.telefone_primario') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.telefone_primario ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.email') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.email ?? '—' }}</dd>
                            </div>
                            <div
                                v-if="(paciente.telefones_secundarios ?? []).length > 0"
                                class="sm:col-span-2"
                            >
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.telefones_secundarios') }}</dt>
                                <dd class="mt-0.5 space-y-0.5">
                                    <p
                                        v-for="(tel, idx) in paciente.telefones_secundarios"
                                        :key="idx"
                                        class="text-sm text-foreground"
                                    >
                                        {{ tel.numero }}
                                        <span v-if="tel.label" class="text-xs text-foreground-muted">({{ tel.label }})</span>
                                    </p>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="paciente.endereco" class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">
                        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">{{ t('paciente.form.endereco') }}</h2>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-foreground-muted">CEP</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.endereco.cep ?? '—' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-foreground-muted">Logradouro</dt>
                                <dd class="mt-0.5 text-sm text-foreground">
                                    {{ [paciente.endereco.logradouro, paciente.endereco.numero, paciente.endereco.complemento].filter(Boolean).join(', ') || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">Bairro</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.endereco.bairro ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">Cidade / UF</dt>
                                <dd class="mt-0.5 text-sm text-foreground">
                                    {{ [paciente.endereco.cidade, paciente.endereco.uf].filter(Boolean).join(' / ') || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">
                        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">CRM</h2>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.origem') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.origem ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.origem_detalhe') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.origem_detalhe ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.form.profissional_responsavel') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ paciente.profissional_responsavel?.nome ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-foreground-muted">{{ t('paciente.list.filters.convenio') }}</dt>
                                <dd class="mt-0.5 text-sm text-foreground">{{ convenioLabel(paciente) }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="mb-1 text-xs text-foreground-muted">{{ t('paciente.form.tags') }}</dt>
                                <dd>
                                    <TagPicker
                                        v-model="tagIds"
                                        :paciente-id="pacienteId"
                                        :tags-carregadas="tagsCarregadas"
                                        @tag-attached="carregarPaciente"
                                        @tag-detached="carregarPaciente"
                                    />
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Convênios interativos -->
                    <div class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground-muted">Convênios</h2>
                            <button
                                v-if="auth.hasPermission('paciente.update') && (paciente.convenios ?? []).length < 2"
                                type="button"
                                class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openConvenioModal"
                            >
                                + Adicionar convênio
                            </button>
                        </div>

                        <div v-if="(paciente.convenios ?? []).length > 0" class="space-y-2">
                            <div
                                v-for="pconv in paciente.convenios"
                                :key="pconv.id ?? pconv.convenio_id"
                                class="flex items-center justify-between rounded-lg border border-border px-3 py-2"
                            >
                                <div>
                                    <p class="text-sm font-medium text-foreground">
                                        {{ pconv.convenio?.nome ?? pconv.nome ?? '—' }}
                                        <span class="ml-1 rounded bg-surface px-1.5 py-0.5 text-[10px] text-foreground-muted">
                                            {{ pconv.papel }}
                                        </span>
                                    </p>
                                    <p v-if="pconv.numero_carteirinha" class="text-xs text-foreground-muted">
                                        Carteirinha: {{ pconv.numero_carteirinha }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p v-else class="text-sm text-foreground-muted">
                            Nenhum convênio vinculado.
                        </p>
                    </div>

                    <p class="text-xs text-foreground-muted">
                        Criado em {{ paciente.created_at ? formatDateTime(paciente.created_at) : '—' }} ·
                        Atualizado em {{ paciente.updated_at ? formatDateTime(paciente.updated_at) : '—' }}
                    </p>
                </div>

                <!-- ── Tab: Timeline ───────────────────────────────────────── -->
                <div
                    v-if="activeTab === 'tabs.timeline'"
                    :id="`panel-tabs.timeline`"
                    role="tabpanel"
                >
                    <PacienteTimelineTab :paciente-id="pacienteId" />
                </div>

                <!-- ── Tab: Anotações ─────────────────────────────────────── -->
                <div
                    v-if="activeTab === 'tabs.anotacoes'"
                    :id="`panel-tabs.anotacoes`"
                    role="tabpanel"
                    class="space-y-4"
                >
                    <!-- Formulário de nova anotação -->
                    <AnotacaoForm
                        :paciente-id="pacienteId"
                        @criada="onAnotacaoCriada"
                    />

                    <!-- Loading -->
                    <div
                        v-if="anotacoesLoading"
                        class="flex items-center justify-center py-8"
                        aria-live="polite"
                    >
                        <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
                    </div>

                    <!-- Erro -->
                    <div
                        v-else-if="anotacoesError"
                        role="alert"
                        class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-600"
                    >
                        {{ anotacoesError }}
                    </div>

                    <!-- Lista de anotações -->
                    <div v-else-if="anotacoes.length > 0" class="space-y-3">
                        <template v-for="anotacao in anotacoes" :key="anotacao.id">
                            <!-- Anotação original (não é retratação) -->
                            <div
                                v-if="!anotacao.eh_retratacao"
                                class="rounded-xl border border-border bg-surface-elevated p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                                :class="tipoAnotacaoBadgeClass(anotacao.tipo)"
                                            >
                                                {{ t(`anotacao.tipo.${anotacao.tipo}`) }}
                                            </span>
                                            <span
                                                v-if="anotacao.retratacoes?.length > 0"
                                                class="text-xs text-warning-600"
                                            >
                                                {{ t('anotacao.retratada_em', { date: anotacao.retratacoes[0] }) }}
                                            </span>
                                        </div>
                                        <p class="mt-2 whitespace-pre-wrap text-sm text-foreground">
                                            {{ anotacao.texto }}
                                        </p>
                                        <p class="mt-1 text-xs text-foreground-muted">
                                            {{ anotacao.autor?.nome ?? 'Sistema' }}
                                            <span v-if="anotacao.created_at" class="mx-1">·</span>
                                            {{ anotacao.created_at }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="anotacao.retratacoes?.length === 0"
                                        type="button"
                                        class="shrink-0 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                        @click="abrirRetratacao(anotacao.id)"
                                    >
                                        {{ t('anotacao.retratacao') }}
                                    </button>
                                </div>

                                <!-- Form inline de retratação -->
                                <div
                                    v-if="retratacaoAberta === anotacao.id"
                                    class="mt-3 border-t border-border pt-3"
                                >
                                    <textarea
                                        v-model="retratacaoTexto"
                                        rows="3"
                                        class="w-full resize-y rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                        placeholder="Descreva a retratação (mínimo 10 caracteres)"
                                    />
                                    <div
                                        v-if="retratacaoError"
                                        role="alert"
                                        class="mt-2 text-xs text-danger-600"
                                    >
                                        {{ retratacaoError }}
                                    </div>
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground"
                                            @click="fecharRetratacao"
                                        >
                                            {{ t('common.cancel') }}
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="retratacaoLoading || retratacaoTexto.length < 10"
                                            class="rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60"
                                            @click="enviarRetratacao(anotacao.id)"
                                        >
                                            {{ retratacaoLoading ? t('common.loading') : t('common.save') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Retratações vinculadas (indentadas) -->
                                <div
                                    v-for="retId in (anotacao.retratacoes ?? [])"
                                    :key="retId"
                                    class="ml-6 mt-2 border-l-2 border-border pl-3"
                                >
                                    <p class="text-xs text-foreground-muted">
                                        Retratação #{{ retId }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-else
                        class="flex items-center justify-center py-12"
                    >
                        <p class="text-sm text-foreground-muted">
                            Nenhuma anotação registrada.
                        </p>
                    </div>
                </div>

                <!-- ── Tab: Mesclagens ────────────────────────────────────── -->
                <div
                    v-if="activeTab === 'tabs.mesclagens'"
                    :id="`panel-tabs.mesclagens`"
                    role="tabpanel"
                >
                    <div
                        v-if="mesclagensLoading"
                        class="flex items-center justify-center py-10"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
                    </div>

                    <div
                        v-else-if="mesclagens.length === 0"
                        class="flex items-center justify-center py-16"
                    >
                        <p class="text-sm text-foreground-muted">Nenhuma mesclagem registrada.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="m in mesclagens"
                            :key="m.id"
                            class="rounded-xl border border-border bg-surface-elevated p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-foreground">
                                        Mesclagem #{{ m.id }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-foreground-muted">
                                        {{ m.created_at ? formatDateTime(m.created_at) : '—' }}
                                        · <span v-if="m.reversivel_ate">{{ t('mesclagem.reversivel_ate', { date: formatDate(m.reversivel_ate) }) }}</span>
                                    </p>
                                </div>
                                <button
                                    v-if="!m.revertida_em && m.reversivel"
                                    type="button"
                                    class="shrink-0 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="openReverterModal(m.id)"
                                >
                                    {{ t('mesclagem.reverter_cta') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ─── Modal: Alterar status ─────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="statusModal.open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="status-modal-title"
                @click.self="closeStatusModal"
                @keydown.escape="closeStatusModal"
            >
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface-elevated p-6 shadow-xl">
                    <h2 id="status-modal-title" class="mb-4 text-lg font-semibold text-foreground">
                        {{ t('paciente.show.alterar_status_modal') }}
                    </h2>
                    <p class="mb-4 text-sm text-foreground-muted">
                        Novo status: <strong>{{ statusLabel(statusModal.novoStatus) }}</strong>
                    </p>
                    <div class="mb-4">
                        <label for="motivo-status" class="mb-1.5 block text-sm font-medium text-foreground">Motivo (opcional)</label>
                        <input
                            id="motivo-status"
                            v-model="statusModal.motivo"
                            type="text"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        />
                    </div>
                    <div
                        v-if="statusModal.error"
                        role="alert"
                        aria-live="assertive"
                        class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
                    >
                        {{ statusModal.error }}
                    </div>
                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="closeStatusModal"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="statusModal.loading"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="confirmarAlteracaoStatus"
                        >
                            {{ statusModal.loading ? t('common.loading') : t('common.confirm') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal: Anonimizar ─────────────────────────────────────────────── -->
        <ConfirmModal
            :open="anonimizarModal.open"
            :title="t('paciente.show.anonimizar_cta')"
            @close="closeAnonimizarModal"
            @confirm="confirmarAnonimizar"
        >
            <p class="text-sm text-foreground-muted">
                {{ t('paciente.show.anonimizar_confirm') }}
            </p>
            <div
                v-if="anonimizarModal.error"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
            >
                {{ anonimizarModal.error }}
            </div>
            <template #actions>
                <button
                    type="button"
                    :disabled="anonimizarModal.loading"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmarAnonimizar"
                >
                    {{ anonimizarModal.loading ? t('common.loading') : t('common.confirm') }}
                </button>
            </template>
        </ConfirmModal>

        <!-- ─── Modal: Reverter mesclagem ─────────────────────────────────────── -->
        <ConfirmModal
            :open="reverterModal.open"
            :title="t('mesclagem.reverter_cta')"
            @close="closeReverterModal"
            @confirm="confirmarReverter"
        >
            <p class="text-sm text-foreground-muted">
                Esta ação irá desfazer a mesclagem. Os registros originais serão restaurados.
            </p>
            <div
                v-if="reverterModal.error"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
            >
                {{ reverterModal.error }}
            </div>
            <template #actions>
                <button
                    type="button"
                    :disabled="reverterModal.loading"
                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmarReverter"
                >
                    {{ reverterModal.loading ? t('common.loading') : t('common.confirm') }}
                </button>
            </template>
        </ConfirmModal>

        <!-- ─── Modal: Adicionar convênio ─────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="convenioModal.open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="convenio-modal-title"
                @click.self="closeConvenioModal"
                @keydown.escape="closeConvenioModal"
            >
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface-elevated p-6 shadow-xl">
                    <h2 id="convenio-modal-title" class="mb-4 text-lg font-semibold text-foreground">
                        Adicionar convênio
                    </h2>

                    <div class="space-y-4">
                        <!-- Select convênio -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Convênio</label>
                            <select
                                v-model="convenioModal.convenioSelecionadoId"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            >
                                <option value="">Selecione…</option>
                                <option
                                    v-for="c in catalogoConvenios"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.nome }}
                                </option>
                            </select>
                        </div>

                        <!-- Papel -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Papel</label>
                            <select
                                v-model="convenioModal.papel"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            >
                                <option value="principal">Principal</option>
                                <option value="secundario">Secundário</option>
                            </select>
                        </div>

                        <!-- Carteirinha -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Nº da carteirinha (opcional)</label>
                            <input
                                v-model="convenioModal.carteirinha"
                                type="text"
                                maxlength="30"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            />
                        </div>

                        <!-- Erro -->
                        <div
                            v-if="convenioModal.erro"
                            role="alert"
                            class="rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
                        >
                            {{ convenioModal.erro }}
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="closeConvenioModal"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="convenioModal.loading"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="vincularConvenio"
                        >
                            {{ convenioModal.loading ? t('common.loading') : 'Adicionar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </main>
</template>
