<script setup>
/**
 * T163 (Fase 7 Lote E) — Filtros reutilizáveis do Relatório de Receitas (US-8.4).
 *
 * Props:
 *  - modelValue (Object) — filtros atuais
 *  - professionals (Array) — lista de profissionais para o select
 *
 * Emits:
 *  - update:modelValue (debounce 300ms) — quando qualquer filtro muda
 *  - clear — quando o botão "Limpar filtros" é clicado
 *
 * A11y:
 *  - labels com for
 *  - aria-describedby em campos com hint
 *  - autocomplete de paciente com role=listbox/option
 */
import { ref, computed, watch } from 'vue';
import api from '@/lib/api';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({}),
    },
    professionals: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'clear']);

// ─── Filtros locais (cópia reativa) ──────────────────────────────────────────

const local = ref({ ...props.modelValue });

watch(
    () => props.modelValue,
    (v) => {
        // Atualiza local apenas se vier diferença do pai (evita loop)
        if (JSON.stringify(v) !== JSON.stringify(local.value)) {
            local.value = { ...v };
        }
    },
    { deep: true },
);

// ─── Debounce emit ────────────────────────────────────────────────────────────

let debounceTimer = null;

function applyFilter(key, value) {
    local.value = { ...local.value, [key]: value || null };
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('update:modelValue', { ...local.value });
    }, 300);
}

// ─── Autocomplete de paciente ─────────────────────────────────────────────────

const patientQuery = ref('');
const patientResults = ref([]);
const patientSearchLoading = ref(false);
let patientTimer = null;

watch(patientQuery, (v) => {
    clearTimeout(patientTimer);
    if (!v || v.length < 2) {
        patientResults.value = [];
        return;
    }
    patientTimer = setTimeout(async () => {
        patientSearchLoading.value = true;
        try {
            const { data } = await api.get('/pacientes', { params: { search: v, per_page: 8 } });
            patientResults.value = data.data ?? [];
        } finally {
            patientSearchLoading.value = false;
        }
    }, 250);
});

function selectPatient(patient) {
    local.value = { ...local.value, patient_id: patient.id };
    patientQuery.value = patient.nome;
    patientResults.value = [];
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('update:modelValue', { ...local.value });
    }, 300);
}

function clearPatient() {
    local.value = { ...local.value, patient_id: null };
    patientQuery.value = '';
    patientResults.value = [];
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('update:modelValue', { ...local.value });
    }, 300);
}

// ─── Limpar todos os filtros ──────────────────────────────────────────────────

function clearAll() {
    local.value = {
        status: null,
        type: null,
        professional_id: null,
        patient_id: null,
        expires_after: null,
        expires_before: null,
    };
    patientQuery.value = '';
    patientResults.value = [];
    clearTimeout(debounceTimer);
    emit('update:modelValue', { ...local.value });
    emit('clear');
}

// ─── Contagem de filtros ativos ───────────────────────────────────────────────

const activeFilterCount = computed(() => {
    return Object.values(local.value).filter((v) => v !== null && v !== undefined && v !== '')
        .length;
});
</script>

<template>
    <div class="space-y-3">
        <!-- Grid de filtros: 2 colunas desktop, 1 mobile -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Status -->
            <div>
                <label
                    for="report-filter-status"
                    class="block text-xs font-medium text-foreground mb-1"
                >
                    Status
                </label>
                <select
                    id="report-filter-status"
                    :value="local.status"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="applyFilter('status', $event.target.value || null)"
                >
                    <option value="">Todos</option>
                    <option value="active">Ativa</option>
                    <option value="cancelled">Cancelada</option>
                    <option value="superseded">Substituída</option>
                </select>
            </div>

            <!-- Tipo -->
            <div>
                <label
                    for="report-filter-type"
                    class="block text-xs font-medium text-foreground mb-1"
                >
                    Tipo
                </label>
                <select
                    id="report-filter-type"
                    :value="local.type"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="applyFilter('type', $event.target.value || null)"
                >
                    <option value="">Todos</option>
                    <option value="common">Comum</option>
                    <option value="special">Especial</option>
                    <option value="controlled">Controlada</option>
                </select>
            </div>

            <!-- Profissional -->
            <div>
                <label
                    for="report-filter-professional"
                    class="block text-xs font-medium text-foreground mb-1"
                >
                    Profissional
                </label>
                <select
                    id="report-filter-professional"
                    :value="local.professional_id"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="applyFilter('professional_id', $event.target.value || null)"
                >
                    <option value="">Todos</option>
                    <option v-for="p in professionals" :key="p.id" :value="p.id">
                        {{ p.name ?? p.nome }}
                    </option>
                </select>
            </div>

            <!-- Paciente (autocomplete) -->
            <div class="relative">
                <label
                    for="report-filter-patient"
                    class="block text-xs font-medium text-foreground mb-1"
                >
                    Paciente
                </label>
                <div class="relative">
                    <input
                        id="report-filter-patient"
                        v-model="patientQuery"
                        type="text"
                        role="combobox"
                        placeholder="Buscar paciente..."
                        autocomplete="off"
                        aria-describedby="report-filter-patient-hint"
                        aria-autocomplete="list"
                        :aria-expanded="patientResults.length > 0"
                        aria-controls="report-patient-listbox"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    />
                    <button
                        v-if="local.patient_id"
                        type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        aria-label="Limpar filtro de paciente"
                        @click="clearPatient"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
                <p id="report-filter-patient-hint" class="sr-only">
                    Digite pelo menos 2 caracteres para buscar pacientes
                </p>
                <ul
                    v-if="patientResults.length"
                    id="report-patient-listbox"
                    class="absolute z-10 mt-1 w-full rounded-lg border border-border bg-surface shadow-lg max-h-48 overflow-auto"
                    role="listbox"
                    aria-label="Sugestões de pacientes"
                >
                    <li
                        v-for="p in patientResults"
                        :key="p.id"
                        role="option"
                        class="cursor-pointer px-3 py-2 text-sm text-foreground hover:bg-primary-50 focus:bg-primary-50 focus:outline-none"
                        tabindex="0"
                        @click="selectPatient(p)"
                        @keydown.enter.prevent="selectPatient(p)"
                    >
                        {{ p.nome }}
                        <span class="ml-1 text-xs text-foreground-muted">{{
                            p.cpf || p.telefone_primario
                        }}</span>
                    </li>
                </ul>
                <div
                    v-if="patientSearchLoading"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-foreground-muted"
                    aria-live="polite"
                    aria-label="Buscando..."
                >
                    <svg
                        class="h-3.5 w-3.5 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>
                </div>
            </div>

            <!-- Vence após -->
            <div>
                <label
                    for="report-filter-expires-after"
                    class="block text-xs font-medium text-foreground mb-1"
                >
                    Vence após
                </label>
                <input
                    id="report-filter-expires-after"
                    type="date"
                    :value="local.expires_after"
                    aria-describedby="report-filter-dates-hint"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="applyFilter('expires_after', $event.target.value || null)"
                />
            </div>

            <!-- Vence antes -->
            <div>
                <label
                    for="report-filter-expires-before"
                    class="block text-xs font-medium text-foreground mb-1"
                >
                    Vence antes
                </label>
                <input
                    id="report-filter-expires-before"
                    type="date"
                    :value="local.expires_before"
                    aria-describedby="report-filter-dates-hint"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="applyFilter('expires_before', $event.target.value || null)"
                />
                <p id="report-filter-dates-hint" class="sr-only">
                    Filtra receitas pelo intervalo de vencimento
                </p>
            </div>
        </div>

        <!-- Limpar filtros -->
        <div v-if="activeFilterCount > 0" class="flex justify-end">
            <button
                type="button"
                class="text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                @click="clearAll"
            >
                Limpar filtros ({{ activeFilterCount }})
            </button>
        </div>
    </div>
</template>
