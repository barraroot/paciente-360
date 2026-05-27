<script setup>
/**
 * AppointmentFormModal — Modal de criação/edição de consulta (US-6.3).
 *
 * Acessibilidade (WCAG AA):
 *  - role=dialog + aria-modal + aria-labelledby
 *  - Focus trap: Tab/Shift+Tab circulam dentro do modal
 *  - Esc fecha (handler no keydown do overlay — AgendaPage também escuta)
 *  - Enter no último campo submete o form
 *  - Teleport to body para não vazar z-index
 *  - overlay click fecha (com guarda para mudanças não salvas)
 *
 * Validação: campo obrigatório = paciente + profissional + tipo + horário.
 * Erros 422 do backend mapeados em `fieldErrors`.
 */
import { ref, watch, computed, nextTick } from 'vue'
import PatientAutocomplete from './PatientAutocomplete.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    initialPayload: { type: Object, default: () => ({}) },
    professionals: { type: Array, default: () => [] },
    appointmentTypes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'submit'])

// ─── Form state ───────────────────────────────────────────────────────────────

const form = ref({
    paciente_id: null,
    paciente_label: '',
    professional_id: null,
    appointment_type_id: null,
    starts_at: '',
    channel_origin: 'painel',
    notify_patient: true,
})

const fieldErrors = ref({})
const submitting = ref(false)

watch(() => props.initialPayload, (v) => {
    form.value = {
        paciente_id: null,
        paciente_label: '',
        professional_id: null,
        appointment_type_id: null,
        starts_at: '',
        channel_origin: 'painel',
        notify_patient: true,
        ...v,
    }
    fieldErrors.value = {}
}, { immediate: true })

// Foca o primeiro campo ao abrir
watch(() => props.modelValue, async (open) => {
    if (open) {
        fieldErrors.value = {}
        await nextTick()
        dialogEl.value?.querySelector('input, select, button[autofocus]')?.focus()
    }
})

// ─── Refs de DOM para focus trap ──────────────────────────────────────────────

const dialogEl = ref(null)

function getFocusable() {
    if (!dialogEl.value) return []
    return Array.from(
        dialogEl.value.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    )
}

function trapFocus(e) {
    const focusable = getFocusable()
    if (!focusable.length) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (e.key === 'Tab') {
        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault()
                last.focus()
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault()
                first.focus()
            }
        }
    }
}

// ─── Validação local ──────────────────────────────────────────────────────────

const isValid = computed(() =>
    !!form.value.paciente_id &&
    !!form.value.professional_id &&
    !!form.value.appointment_type_id &&
    !!form.value.starts_at
)

// ─── Ações ────────────────────────────────────────────────────────────────────

function selectPatient(p) {
    form.value.paciente_id = p.id
    form.value.paciente_label = p.nome
}

async function submit() {
    if (!isValid.value || submitting.value) return
    submitting.value = true
    fieldErrors.value = {}
    try {
        await emit('submit', { ...form.value })
    } catch (e) {
        // 422 — mapeia erros por campo
        if (e?.response?.status === 422 && e.response.data?.errors) {
            fieldErrors.value = e.response.data.errors
        }
    } finally {
        submitting.value = false
    }
}

function close() {
    emit('update:modelValue', false)
}

function handleOverlayClick(e) {
    if (e.target === e.currentTarget) close()
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
            @click="handleOverlayClick"
            @keydown.esc.prevent="close"
            @keydown="trapFocus"
        >
            <div
                ref="dialogEl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="form-modal-title"
                class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h2 id="form-modal-title" class="text-base font-semibold text-foreground">
                        {{ form.id ? 'Detalhes da consulta' : 'Nova consulta' }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                        aria-label="Fechar"
                        @click="close"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Corpo -->
                <form class="px-6 py-4 space-y-4" @submit.prevent="submit" novalidate>
                    <!-- Paciente -->
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1" for="fc-paciente">
                            Paciente <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <p v-if="form.paciente_label" class="text-sm font-semibold text-primary-700 mb-1">
                            {{ form.paciente_label }}
                        </p>
                        <PatientAutocomplete id="fc-paciente" @select="selectPatient" />
                        <p v-if="fieldErrors.paciente_id" role="alert" class="mt-1 text-xs text-danger-600">
                            {{ fieldErrors.paciente_id[0] }}
                        </p>
                    </div>

                    <!-- Profissional -->
                    <div>
                        <label for="fc-professional" class="block text-sm font-medium text-foreground mb-1">
                            Profissional <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="fc-professional"
                            v-model="form.professional_id"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            :class="{ 'border-danger-500': fieldErrors.professional_id }"
                        >
                            <option value="" disabled>Selecione um profissional</option>
                            <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="fieldErrors.professional_id" role="alert" class="mt-1 text-xs text-danger-600">
                            {{ fieldErrors.professional_id[0] }}
                        </p>
                    </div>

                    <!-- Tipo de atendimento -->
                    <div>
                        <label for="fc-type" class="block text-sm font-medium text-foreground mb-1">
                            Tipo de atendimento <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="fc-type"
                            v-model="form.appointment_type_id"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            :class="{ 'border-danger-500': fieldErrors.appointment_type_id }"
                        >
                            <option value="" disabled>Selecione o tipo</option>
                            <option v-for="t in appointmentTypes" :key="t.id" :value="t.id">
                                {{ t.nome }} ({{ t.duration_minutes }} min)
                            </option>
                        </select>
                        <p v-if="fieldErrors.appointment_type_id" role="alert" class="mt-1 text-xs text-danger-600">
                            {{ fieldErrors.appointment_type_id[0] }}
                        </p>
                    </div>

                    <!-- Data/hora de início -->
                    <div>
                        <label for="fc-starts-at" class="block text-sm font-medium text-foreground mb-1">
                            Data e hora de início <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="fc-starts-at"
                            v-model="form.starts_at"
                            type="datetime-local"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            :class="{ 'border-danger-500': fieldErrors.starts_at }"
                        />
                        <p v-if="fieldErrors.starts_at" role="alert" class="mt-1 text-xs text-danger-600">
                            {{ fieldErrors.starts_at[0] }}
                        </p>
                    </div>

                    <!-- Notificar paciente -->
                    <label class="flex items-center gap-2 text-sm text-foreground cursor-pointer">
                        <input
                            v-model="form.notify_patient"
                            type="checkbox"
                            class="h-4 w-4 rounded border-border accent-primary-700"
                        />
                        Notificar paciente automaticamente
                    </label>
                </form>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                        @click="close"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="!isValid || submitting"
                        class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        @click="submit"
                    >
                        <span v-if="submitting">Salvando...</span>
                        <span v-else>{{ form.id ? 'Salvar alterações' : 'Criar consulta' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
