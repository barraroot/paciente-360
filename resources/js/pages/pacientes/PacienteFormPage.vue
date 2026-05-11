<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import DedupSuggestionModal from '@/components/pacientes/DedupSuggestionModal.vue';

const props = defineProps({
    /** ID do paciente em modo edição. Undefined = criação. */
    id: {
        type: Number,
        default: undefined,
    },
});

const { t } = useI18n();
const router = useRouter();
const route = useRoute();

const isEdit = computed(() => !!props.id);

// ─── Estado do form ───────────────────────────────────────────────────────────

const form = reactive({
    // Dados pessoais
    nome: '',
    cpf: '',
    documento_estrangeiro: '',
    data_nascimento: '',

    // Contato
    telefone_primario: '',
    telefones_secundarios: [],
    email: '',

    // Endereço
    endereco: {
        cep: '',
        logradouro: '',
        numero: '',
        complemento: '',
        bairro: '',
        cidade: '',
        uf: '',
    },

    // CRM
    status: 'lead',
    origem: '',
    origem_detalhe: '',
    profissional_responsavel_id: '',
    tags: [],
    convenios: [],
});

const validationErrors = ref({});
const submitLoading = ref(false);
const submitError = ref(null);
const submitSuccess = ref(false);

// ─── Dedup modal ──────────────────────────────────────────────────────────────

const dedupModal = reactive({
    open: false,
    candidatos: [],
});

function closeDedupModal() {
    dedupModal.open = false;
}

// ─── Máscara de CPF ───────────────────────────────────────────────────────────

/**
 * Aplica máscara 000.000.000-00 ao CPF mantendo apenas dígitos.
 * Usado tanto para exibição quanto para envio (backend canonicaliza).
 */
function formatCpf(value) {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 3) { return digits; }
    if (digits.length <= 6) { return `${digits.slice(0, 3)}.${digits.slice(3)}`; }
    if (digits.length <= 9) { return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`; }
    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9, 11)}`;
}

function onCpfInput(event) {
    form.cpf = formatCpf(event.target.value);
}

/**
 * Aplica máscara (00) 00000-0000 ao telefone.
 */
function formatTelefone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 2) { return digits.length ? `(${digits}` : ''; }
    if (digits.length <= 7) { return `(${digits.slice(0, 2)}) ${digits.slice(2)}`; }
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function onTelefonePrimarioInput(event) {
    form.telefone_primario = formatTelefone(event.target.value);
}

function onTelefoneSecundarioInput(event, idx) {
    form.telefones_secundarios[idx].numero = formatTelefone(event.target.value);
}

// ─── Telefones secundários ────────────────────────────────────────────────────

const MAX_TELEFONES_SECUNDARIOS = 5;

function adicionarTelefone() {
    if (form.telefones_secundarios.length < MAX_TELEFONES_SECUNDARIOS) {
        form.telefones_secundarios.push({ numero: '', label: '' });
    }
}

function removerTelefone(idx) {
    form.telefones_secundarios.splice(idx, 1);
}

// ─── Convênios ────────────────────────────────────────────────────────────────

function adicionarConvenio() {
    if (form.convenios.length < 2) {
        form.convenios.push({
            convenio_id: '',
            papel: form.convenios.length === 0 ? 'principal' : 'secundario',
            numero_carteirinha: '',
        });
    }
}

function removerConvenio(idx) {
    form.convenios.splice(idx, 1);
    // Reajusta papel se necessário.
    if (form.convenios.length > 0) {
        form.convenios[0].papel = 'principal';
    }
}

// ─── Selects auxiliares ───────────────────────────────────────────────────────

const profissionais = ref([]);
const tagsDisponiveis = ref([]);
const conveniosDisponiveis = ref([]);

async function carregarDadosAuxiliares() {
    try {
        const [profResp, tagsResp, convResp] = await Promise.allSettled([
            api.get('/users', { params: { role: 'medico', per_page: 100 } }),
            api.get('/tags'),
            api.get('/convenios', { params: { per_page: 100 } }),
        ]);

        if (profResp.status === 'fulfilled') {
            profissionais.value = profResp.value.data.data ?? profResp.value.data ?? [];
        }
        if (tagsResp.status === 'fulfilled') {
            tagsDisponiveis.value = tagsResp.value.data.data ?? tagsResp.value.data ?? [];
        }
        if (convResp.status === 'fulfilled') {
            conveniosDisponiveis.value = convResp.value.data.data ?? convResp.value.data ?? [];
        }
    } catch {
        // Falha silenciosa — seletores ficam sem opções pré-carregadas.
    }
}

// ─── Modo edição: carregar paciente ──────────────────────────────────────────

const loadingPaciente = ref(false);
const loadError = ref(null);

async function carregarPaciente() {
    if (!props.id) { return; }
    loadingPaciente.value = true;
    loadError.value = null;
    try {
        const { data } = await api.get(`/pacientes/${props.id}`);
        const p = data.data ?? data;
        form.nome = p.nome ?? '';
        form.cpf = p.cpf ?? '';
        form.documento_estrangeiro = p.documento_estrangeiro ?? '';
        form.data_nascimento = p.data_nascimento ?? '';
        form.telefone_primario = p.telefone_primario ?? '';
        form.telefones_secundarios = Array.isArray(p.telefones_secundarios)
            ? p.telefones_secundarios.map((t) => ({ numero: t.numero ?? '', label: t.label ?? '' }))
            : [];
        form.email = p.email ?? '';
        form.endereco = {
            cep: p.endereco?.cep ?? '',
            logradouro: p.endereco?.logradouro ?? '',
            numero: p.endereco?.numero ?? '',
            complemento: p.endereco?.complemento ?? '',
            bairro: p.endereco?.bairro ?? '',
            cidade: p.endereco?.cidade ?? '',
            uf: p.endereco?.uf ?? '',
        };
        form.origem = p.origem ?? '';
        form.origem_detalhe = p.origem_detalhe ?? '';
        form.profissional_responsavel_id = p.profissional_responsavel?.id ?? '';
        form.tags = (p.tags ?? []).map((tag) => tag.id);
        form.convenios = (p.convenios ?? []).map((c, idx) => ({
            convenio_id: c.convenio_id ?? c.convenio?.id ?? '',
            papel: idx === 0 ? 'principal' : 'secundario',
            numero_carteirinha: c.numero_carteirinha ?? '',
        }));
    } catch (err) {
        loadError.value = err.response?.status === 404
            ? 'Paciente não encontrado.'
            : t('common.error_generic');
    } finally {
        loadingPaciente.value = false;
    }
}

onMounted(async () => {
    await Promise.all([carregarDadosAuxiliares(), carregarPaciente()]);
});

// ─── Validação client-side ────────────────────────────────────────────────────

function validar() {
    const errors = {};
    if (!form.nome || form.nome.trim().length < 2) {
        errors.nome = 'Nome deve ter pelo menos 2 caracteres.';
    }
    if (form.cpf) {
        const digits = form.cpf.replace(/\D/g, '');
        if (digits.length !== 11) {
            errors.cpf = 'CPF inválido. Informe os 11 dígitos.';
        }
    }
    return errors;
}

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? null;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

async function onSubmit() {
    validationErrors.value = {};
    submitError.value = null;

    const clientErrors = validar();
    if (Object.keys(clientErrors).length > 0) {
        // Converte para formato array para reutilizar fieldError().
        validationErrors.value = Object.fromEntries(
            Object.entries(clientErrors).map(([k, v]) => [k, [v]]),
        );
        return;
    }

    submitLoading.value = true;

    // Monta payload sem campos vazios opcionais.
    const payload = {
        nome: form.nome,
        ...(form.cpf ? { cpf: form.cpf } : {}),
        ...(form.documento_estrangeiro ? { documento_estrangeiro: form.documento_estrangeiro } : {}),
        ...(form.data_nascimento ? { data_nascimento: form.data_nascimento } : {}),
        ...(form.telefone_primario ? { telefone_primario: form.telefone_primario } : {}),
        ...(form.telefones_secundarios.length > 0 ? { telefones_secundarios: form.telefones_secundarios } : {}),
        ...(form.email ? { email: form.email } : {}),
        ...(Object.values(form.endereco).some(Boolean) ? { endereco: form.endereco } : {}),
        ...(form.origem ? { origem: form.origem } : {}),
        ...(form.origem_detalhe ? { origem_detalhe: form.origem_detalhe } : {}),
        ...(form.profissional_responsavel_id ? { profissional_responsavel_id: form.profissional_responsavel_id } : {}),
        ...(form.tags.length > 0 ? { tags: form.tags } : {}),
        ...(form.convenios.length > 0 ? { convenios: form.convenios } : {}),
    };

    // No modo criação, inclui status inicial.
    if (!isEdit.value) {
        payload.status = form.status;
    }

    try {
        if (isEdit.value) {
            await api.patch(`/pacientes/${props.id}`, payload);
            router.push(`/panel/pacientes/${props.id}`);
        } else {
            const { data } = await api.post('/pacientes', payload);
            const novoPacienteId = (data.data ?? data).id;
            router.push(`/panel/pacientes/${novoPacienteId}`);
        }
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 409) {
            // Dedup: abre modal com candidatos.
            dedupModal.candidatos = body?.candidatos ?? [];
            dedupModal.open = true;
        } else if (status === 422) {
            validationErrors.value = body?.errors ?? {};
        } else if (status === 403) {
            submitError.value = 'Sem permissão para realizar esta ação.';
        } else {
            submitError.value = t('common.error_generic');
        }
    } finally {
        submitLoading.value = false;
    }
}

// Re-submit com ignorar_duplicata=true (criar paralelo) a partir do modal.
async function criarParalelo() {
    closeDedupModal();
    submitLoading.value = true;
    submitError.value = null;
    try {
        const { data } = await api.post('/pacientes', {
            nome: form.nome,
            cpf: form.cpf,
            ignorar_duplicata: true,
        });
        const novoPacienteId = (data.data ?? data).id;
        router.push(`/panel/pacientes/${novoPacienteId}`);
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            submitError.value = 'Não é possível criar paralelo com mesmo CPF; modifique o CPF ou use Mesclar.';
        } else {
            submitError.value = t('common.error_generic');
        }
    } finally {
        submitLoading.value = false;
    }
}

// ─── Estados do Brasil ────────────────────────────────────────────────────────

const UF_OPTIONS = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
    'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
    'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

// ─── Origem options ───────────────────────────────────────────────────────────

const ORIGEM_OPTIONS = [
    { value: 'site', label: 'Site' },
    { value: 'indicacao', label: 'Indicação' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'telefone', label: 'Telefone' },
    { value: 'presencial', label: 'Presencial' },
    { value: 'outro', label: 'Outro' },
];

// ─── Tags toggle ──────────────────────────────────────────────────────────────

function toggleTag(tagId) {
    const idx = form.tags.indexOf(tagId);
    if (idx === -1) {
        form.tags.push(tagId);
    } else {
        form.tags.splice(idx, 1);
    }
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-3xl">

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
                <h1 class="text-2xl font-bold text-foreground">
                    {{ isEdit ? t('paciente.form.title_edit') : t('paciente.form.title_new') }}
                </h1>
            </div>

            <!-- ── Loading (modo edição) ───────────────────────────────────────── -->
            <div
                v-if="loadingPaciente"
                class="flex items-center justify-center py-16"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
            </div>

            <!-- ── Erro de carregamento ────────────────────────────────────────── -->
            <div
                v-else-if="loadError"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
            >
                {{ loadError }}
            </div>

            <!-- ── Formulário ──────────────────────────────────────────────────── -->
            <form
                v-else
                novalidate
                @submit.prevent="onSubmit"
            >

                <!-- Erro global de submit -->
                <div
                    v-if="submitError"
                    role="alert"
                    aria-live="assertive"
                    class="mb-6 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
                >
                    {{ submitError }}
                </div>

                <!-- ══ Seção: Dados pessoais ══════════════════════════════════════ -->
                <section class="mb-8">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">Dados pessoais</h2>
                    <div class="space-y-4 rounded-xl border border-border bg-surface-elevated p-5">

                        <!-- Nome -->
                        <div>
                            <label for="nome" class="mb-1.5 block text-sm font-medium text-foreground">
                                {{ t('paciente.form.nome') }}
                                <span class="text-danger-600" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="nome"
                                v-model="form.nome"
                                type="text"
                                required
                                autocomplete="name"
                                :aria-invalid="!!fieldError('nome')"
                                :aria-describedby="fieldError('nome') ? 'nome-error' : undefined"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                :class="{ 'border-danger-500': fieldError('nome') }"
                            />
                            <p
                                v-if="fieldError('nome')"
                                id="nome-error"
                                role="alert"
                                class="mt-1 text-xs text-danger-600"
                            >
                                {{ fieldError('nome') }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- CPF -->
                            <div>
                                <label for="cpf" class="mb-1.5 block text-sm font-medium text-foreground">
                                    {{ t('paciente.form.cpf') }}
                                </label>
                                <input
                                    id="cpf"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="14"
                                    :value="form.cpf"
                                    :aria-invalid="!!fieldError('cpf')"
                                    :aria-describedby="fieldError('cpf') ? 'cpf-error' : undefined"
                                    placeholder="000.000.000-00"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    :class="{ 'border-danger-500': fieldError('cpf') }"
                                    @input="onCpfInput"
                                />
                                <p
                                    v-if="fieldError('cpf')"
                                    id="cpf-error"
                                    role="alert"
                                    class="mt-1 text-xs text-danger-600"
                                >
                                    {{ fieldError('cpf') }}
                                </p>
                            </div>

                            <!-- Documento estrangeiro -->
                            <div>
                                <label for="doc-estrangeiro" class="mb-1.5 block text-sm font-medium text-foreground">
                                    {{ t('paciente.form.documento_estrangeiro') }}
                                </label>
                                <input
                                    id="doc-estrangeiro"
                                    v-model="form.documento_estrangeiro"
                                    type="text"
                                    maxlength="30"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                        </div>

                        <!-- Data de nascimento -->
                        <div class="sm:w-1/2">
                            <label for="data-nascimento" class="mb-1.5 block text-sm font-medium text-foreground">
                                {{ t('paciente.form.data_nascimento') }}
                            </label>
                            <input
                                id="data-nascimento"
                                v-model="form.data_nascimento"
                                type="date"
                                :max="new Date().toISOString().split('T')[0]"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            />
                        </div>
                    </div>
                </section>

                <!-- ══ Seção: Contato ══════════════════════════════════════════ -->
                <section class="mb-8">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">Contato</h2>
                    <div class="space-y-4 rounded-xl border border-border bg-surface-elevated p-5">

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Telefone primário -->
                            <div>
                                <label for="telefone-primario" class="mb-1.5 block text-sm font-medium text-foreground">
                                    {{ t('paciente.form.telefone_primario') }}
                                </label>
                                <input
                                    id="telefone-primario"
                                    type="tel"
                                    inputmode="tel"
                                    maxlength="15"
                                    :value="form.telefone_primario"
                                    placeholder="(00) 00000-0000"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    @input="onTelefonePrimarioInput"
                                />
                            </div>

                            <!-- E-mail -->
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-foreground">
                                    {{ t('paciente.form.email') }}
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    :aria-invalid="!!fieldError('email')"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    :class="{ 'border-danger-500': fieldError('email') }"
                                />
                                <p
                                    v-if="fieldError('email')"
                                    role="alert"
                                    class="mt-1 text-xs text-danger-600"
                                >
                                    {{ fieldError('email') }}
                                </p>
                            </div>
                        </div>

                        <!-- Telefones secundários -->
                        <div>
                            <p class="mb-2 text-sm font-medium text-foreground">
                                {{ t('paciente.form.telefones_secundarios') }}
                            </p>
                            <div class="space-y-2">
                                <div
                                    v-for="(tel, idx) in form.telefones_secundarios"
                                    :key="idx"
                                    class="flex gap-2"
                                >
                                    <input
                                        :id="`tel-sec-${idx}`"
                                        type="tel"
                                        inputmode="tel"
                                        maxlength="15"
                                        :value="tel.numero"
                                        :placeholder="`Telefone ${idx + 2} — (00) 00000-0000`"
                                        :aria-label="`Telefone adicional ${idx + 1}`"
                                        class="flex-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                        @input="onTelefoneSecundarioInput($event, idx)"
                                    />
                                    <input
                                        v-model="tel.label"
                                        type="text"
                                        maxlength="30"
                                        placeholder="Etiqueta (ex: Trabalho)"
                                        :aria-label="`Etiqueta do telefone ${idx + 1}`"
                                        class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    />
                                    <button
                                        type="button"
                                        :aria-label="`Remover telefone ${idx + 1}`"
                                        class="rounded-lg border border-danger-300 px-2 py-1 text-xs text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                        @click="removerTelefone(idx)"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                            <button
                                v-if="form.telefones_secundarios.length < MAX_TELEFONES_SECUNDARIOS"
                                type="button"
                                class="mt-2 text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="adicionarTelefone"
                            >
                                + Adicionar telefone
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ══ Seção: Endereço ════════════════════════════════════════════ -->
                <section class="mb-8">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">{{ t('paciente.form.endereco') }}</h2>
                    <div class="space-y-4 rounded-xl border border-border bg-surface-elevated p-5">

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="cep" class="mb-1.5 block text-sm font-medium text-foreground">CEP</label>
                                <input
                                    id="cep"
                                    v-model="form.endereco.cep"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="9"
                                    placeholder="00000-000"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="logradouro" class="mb-1.5 block text-sm font-medium text-foreground">Logradouro</label>
                                <input
                                    id="logradouro"
                                    v-model="form.endereco.logradouro"
                                    type="text"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="numero" class="mb-1.5 block text-sm font-medium text-foreground">Número</label>
                                <input
                                    id="numero"
                                    v-model="form.endereco.numero"
                                    type="text"
                                    maxlength="10"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="complemento" class="mb-1.5 block text-sm font-medium text-foreground">Complemento</label>
                                <input
                                    id="complemento"
                                    v-model="form.endereco.complemento"
                                    type="text"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="bairro" class="mb-1.5 block text-sm font-medium text-foreground">Bairro</label>
                                <input
                                    id="bairro"
                                    v-model="form.endereco.bairro"
                                    type="text"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                            <div>
                                <label for="cidade" class="mb-1.5 block text-sm font-medium text-foreground">Cidade</label>
                                <input
                                    id="cidade"
                                    v-model="form.endereco.cidade"
                                    type="text"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                            <div>
                                <label for="uf" class="mb-1.5 block text-sm font-medium text-foreground">UF</label>
                                <select
                                    id="uf"
                                    v-model="form.endereco.uf"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                >
                                    <option value="">—</option>
                                    <option v-for="uf in UF_OPTIONS" :key="uf" :value="uf">{{ uf }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ══ Seção: CRM ═════════════════════════════════════════════════ -->
                <section class="mb-8">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">CRM</h2>
                    <div class="space-y-4 rounded-xl border border-border bg-surface-elevated p-5">

                        <!-- Status inicial (apenas criação) -->
                        <div v-if="!isEdit">
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium text-foreground">
                                    {{ t('paciente.form.status_inicial') }}
                                </legend>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            v-model="form.status"
                                            type="radio"
                                            value="lead"
                                            class="accent-primary-600 h-4 w-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                        />
                                        <span class="text-sm text-foreground">{{ t('paciente.status.lead') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            v-model="form.status"
                                            type="radio"
                                            value="ativo"
                                            class="accent-primary-600 h-4 w-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                        />
                                        <span class="text-sm text-foreground">{{ t('paciente.status.ativo') }}</span>
                                    </label>
                                </div>
                            </fieldset>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Origem -->
                            <div>
                                <label for="origem" class="mb-1.5 block text-sm font-medium text-foreground">
                                    {{ t('paciente.form.origem') }}
                                </label>
                                <select
                                    id="origem"
                                    v-model="form.origem"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                >
                                    <option value="">Selecione…</option>
                                    <option
                                        v-for="opt in ORIGEM_OPTIONS"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Detalhe da origem -->
                            <div>
                                <label for="origem-detalhe" class="mb-1.5 block text-sm font-medium text-foreground">
                                    {{ t('paciente.form.origem_detalhe') }}
                                </label>
                                <input
                                    id="origem-detalhe"
                                    v-model="form.origem_detalhe"
                                    type="text"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                />
                            </div>
                        </div>

                        <!-- Profissional responsável -->
                        <div>
                            <label for="profissional" class="mb-1.5 block text-sm font-medium text-foreground">
                                {{ t('paciente.form.profissional_responsavel') }}
                            </label>
                            <select
                                id="profissional"
                                v-model="form.profissional_responsavel_id"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            >
                                <option value="">Nenhum</option>
                                <option
                                    v-for="prof in profissionais"
                                    :key="prof.id"
                                    :value="prof.id"
                                >
                                    {{ prof.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Tags -->
                        <div v-if="tagsDisponiveis.length > 0">
                            <p class="mb-2 text-sm font-medium text-foreground">{{ t('paciente.form.tags') }}</p>
                            <div class="flex flex-wrap gap-1.5" role="group" :aria-label="t('paciente.form.tags')">
                                <button
                                    v-for="tag in tagsDisponiveis"
                                    :key="tag.id"
                                    type="button"
                                    :aria-pressed="form.tags.includes(tag.id)"
                                    class="rounded-full border px-2.5 py-0.5 text-xs font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    :class="form.tags.includes(tag.id)
                                        ? 'border-primary-500 bg-primary-50 text-primary-700'
                                        : 'border-border bg-surface text-foreground-muted hover:border-primary-400'"
                                    @click="toggleTag(tag.id)"
                                >
                                    {{ tag.nome ?? tag.name }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ══ Seção: Convênios ════════════════════════════════════════════ -->
                <section class="mb-8">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground-muted">{{ t('paciente.form.convenios') }}</h2>
                    <div class="space-y-3 rounded-xl border border-border bg-surface-elevated p-5">

                        <div
                            v-for="(conv, idx) in form.convenios"
                            :key="idx"
                            class="rounded-lg border border-border p-3"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-medium text-foreground-muted uppercase">
                                    {{ idx === 0 ? 'Principal' : 'Secundário' }}
                                </span>
                                <button
                                    type="button"
                                    :aria-label="`Remover convênio ${idx + 1}`"
                                    class="text-xs text-danger-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="removerConvenio(idx)"
                                >
                                    Remover
                                </button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label :for="`convenio-id-${idx}`" class="mb-1 block text-xs font-medium text-foreground">
                                        Convênio
                                    </label>
                                    <select
                                        :id="`convenio-id-${idx}`"
                                        v-model="conv.convenio_id"
                                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    >
                                        <option value="">Selecione…</option>
                                        <option
                                            v-for="c in conveniosDisponiveis"
                                            :key="c.id"
                                            :value="c.id"
                                        >
                                            {{ c.nome }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label :for="`carteirinha-${idx}`" class="mb-1 block text-xs font-medium text-foreground">
                                        Nº carteirinha
                                    </label>
                                    <input
                                        :id="`carteirinha-${idx}`"
                                        v-model="conv.numero_carteirinha"
                                        type="text"
                                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    />
                                </div>
                            </div>
                        </div>

                        <button
                            v-if="form.convenios.length < 2"
                            type="button"
                            class="text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="adicionarConvenio"
                        >
                            + Adicionar convênio
                        </button>
                    </div>
                </section>

                <!-- ── Ações do form ────────────────────────────────────────────── -->
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="router.back()"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="submitLoading"
                        class="rounded-lg bg-primary-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ submitLoading ? t('common.loading') : t('paciente.form.submit') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- ─── Modal dedup ─────────────────────────────────────────────────── -->
        <DedupSuggestionModal
            :open="dedupModal.open"
            :candidatos="dedupModal.candidatos"
            :payload-original="form"
            @close="closeDedupModal"
            @criar-paralelo="criarParalelo"
        />
    </main>
</template>
