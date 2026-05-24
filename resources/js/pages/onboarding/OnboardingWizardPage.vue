<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import api from '@/lib/api.js';
import { useAuthStore } from '@/stores/auth.js';
import ProfessionalFormModal from '@/components/Professionals/ProfessionalFormModal.vue';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

// ─── Spec 012 — Step 2 (first_professional) integration ───────────────────────
const professionalModalOpen = ref(false);

// ─── State ────────────────────────────────────────────────────────────────────

const state = ref({
    completed: false,
    progress_percent: 0,
    steps: [],
});

const loading = ref(true);
const activeStep = ref(null);
const toast = ref(null);
const toastMessage = ref('');
const globalError = ref(null);
const saving = ref(false);

// ─── Clinic data form ─────────────────────────────────────────────────────────

const clinicForm = ref({
    address: '',
    phone: '',
    opening_hours: '',
});

const clinicFormErrors = ref({});

// ─── Computed ─────────────────────────────────────────────────────────────────

const allCompleted = computed(() => state.value.completed === true);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Aplica máscara visual de telefone BR: (00) 00000-0000.
 *
 * @param {string} value
 * @returns {string}
 */
function formatPhone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 2) return digits.length ? `(${digits}` : '';
    if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function onPhoneInput(event) {
    clinicForm.value.phone = formatPhone(event.target.value);
}

function showToast(message) {
    toastMessage.value = message;
    toast.value = true;
    setTimeout(() => {
        toast.value = false;
    }, 3000);
}

// ─── API ──────────────────────────────────────────────────────────────────────

async function fetchState() {
    loading.value = true;
    try {
        const { data } = await api.get('/onboarding/state');
        state.value = data.data;

        if (state.value.completed) {
            try {
                await auth.fetchMe();
            } catch {
                // best-effort refresh
            }
        }
    } catch {
        globalError.value = t('common.error_generic');
    } finally {
        loading.value = false;
    }
}

async function completeStep(key, payload) {
    saving.value = true;
    globalError.value = null;
    clinicFormErrors.value = {};
    try {
        const { data } = await api.post(`/onboarding/steps/${key}/complete`, payload);
        state.value = data.data;
        activeStep.value = null;
        showToast(t('tenant.onboarding.step_completed'));

        if (state.value.completed) {
            try {
                await auth.fetchMe();
            } catch {
                // ignore — refresh apenas best-effort; usuário ainda pode clicar
                // "Ir para o painel" e o guard recarrega via fetchMe se necessário.
            }
        }
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;
        if (status === 409) {
            globalError.value = body?.message ?? t('common.error_generic');
        } else if (status === 422 && body?.errors) {
            clinicFormErrors.value = body.errors;
        } else {
            globalError.value = t('common.error_generic');
        }
    } finally {
        saving.value = false;
    }
}

function onSubmitClinicData() {
    completeStep('clinic_data', {
        address: clinicForm.value.address,
        phone: clinicForm.value.phone.replace(/\D/g, ''),
        opening_hours: clinicForm.value.opening_hours,
    });
}

function toggleStep(step) {
    if (step.status === 'locked') return;
    // Spec 012 — step 2 abre modal em vez de painel inline.
    if (step.key === 'first_professional' && step.status === 'pending') {
        professionalModalOpen.value = true;
        return;
    }
    activeStep.value = activeStep.value === step.key ? null : step.key;
    clinicFormErrors.value = {};
}

/**
 * Handler do @saved do ProfessionalFormModal no contexto de onboarding.
 * Marca step 2 como completo, gravando { professional_id, via } no payload.
 */
async function onProfessionalSaved(saved) {
    const via = saved?.user_id ? 'linked_user' : 'invited_user';
    await completeStep('first_professional', {
        professional_id: saved.id,
        via,
    });
}

async function skipStep(stepKey) {
    saving.value = true;
    globalError.value = null;
    try {
        const { data } = await api.post(`/onboarding/steps/${stepKey}/skip`);
        state.value = data.data;
        activeStep.value = null;
        showToast(t('tenant.onboarding.step_skipped'));
        if (state.value.completed) {
            try { await auth.fetchMe(); } catch { /* best-effort */ }
        }
    } catch {
        globalError.value = t('common.error_generic');
    } finally {
        saving.value = false;
    }
}

function fieldError(field) {
    return clinicFormErrors.value[field]?.[0] ?? null;
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(() => {
    fetchState();
});
</script>

<template>
    <main class="min-h-screen bg-surface px-4 py-12">
        <div class="mx-auto w-full max-w-2xl">

            <!-- Toast de sucesso -->
            <div
                v-if="toast"
                role="status"
                aria-live="polite"
                class="fixed right-4 top-4 z-50 rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm font-medium text-success-600 shadow-popover"
            >
                {{ toastMessage }}
            </div>

            <!-- Banner de onboarding completo -->
            <div
                v-if="allCompleted"
                role="status"
                aria-live="polite"
                class="mb-6 flex items-center justify-between rounded-xl border border-success-500 bg-success-50 px-5 py-4"
            >
                <p class="font-medium text-success-600">
                    {{ t('tenant.onboarding.all_steps_completed') }}
                </p>
                <button
                    type="button"
                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="router.push('/panel')"
                >
                    {{ t('tenant.onboarding.go_to_panel') }}
                </button>
            </div>

            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-semibold text-foreground">
                    {{ t('tenant.onboarding.title') }}
                </h1>
                <p class="mt-1 text-sm text-foreground-muted">
                    {{ t('tenant.onboarding.subtitle') }}
                </p>
            </div>

            <!-- Progress bar -->
            <div class="mb-8">
                <div class="mb-1.5 flex items-center justify-between text-sm text-foreground-muted">
                    <span>{{ t('tenant.onboarding.continue') }}</span>
                    <span class="font-medium text-foreground">{{ state.progress_percent }}% concluído</span>
                </div>
                <div class="h-2 rounded-full bg-surface-muted">
                    <div
                        class="h-full rounded-full bg-primary-700 transition-all duration-500"
                        :style="{ width: state.progress_percent + '%' }"
                        role="progressbar"
                        :aria-valuenow="state.progress_percent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="`${state.progress_percent}% concluído`"
                    ></div>
                </div>
            </div>

            <!-- Alerta de erro global -->
            <div
                v-if="globalError"
                role="alert"
                aria-live="assertive"
                class="mb-6 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-500"
            >
                {{ globalError }}
            </div>

            <!-- Loading skeleton -->
            <div v-if="loading" class="space-y-3">
                <div
                    v-for="i in 5"
                    :key="i"
                    class="h-16 animate-pulse rounded-xl bg-surface-muted"
                ></div>
            </div>

            <!-- Stepper vertical -->
            <ol v-else class="space-y-3" aria-label="Etapas de configuração">
                <li
                    v-for="step in state.steps"
                    :key="step.key"
                    class="overflow-hidden rounded-xl border bg-surface-elevated transition-shadow"
                    :class="{
                        'border-primary-200 shadow-card': step.status === 'pending' || step.status === 'completed',
                        'border-border opacity-60': step.status === 'locked',
                        'border-border': step.status === 'skipped',
                    }"
                >
                    <!-- Card header -->
                    <div class="flex items-center gap-4 px-5 py-4">
                        <!-- Ícone de status -->
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg font-bold"
                            :class="{
                                'bg-success-50 text-success-600': step.status === 'completed',
                                'bg-primary-50 text-primary-700': step.status === 'pending',
                                'bg-surface-muted text-foreground-subtle': step.status === 'locked',
                                'bg-surface-muted text-foreground-muted': step.status === 'skipped',
                            }"
                            aria-hidden="true"
                        >
                            <span v-if="step.status === 'completed'">✓</span>
                            <span v-else-if="step.status === 'pending'">→</span>
                            <span v-else-if="step.status === 'locked'">⊘</span>
                            <span v-else-if="step.status === 'skipped'">⊝</span>
                        </div>

                        <!-- Título e status -->
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-foreground truncate">{{ step.label }}</p>
                            <p class="text-xs text-foreground-muted mt-0.5">
                                <span v-if="step.status === 'completed'">Concluído</span>
                                <span v-else-if="step.status === 'pending'">Pendente</span>
                                <span v-else-if="step.status === 'skipped'">Pulado</span>
                                <span v-else-if="step.status === 'locked'">{{ t('tenant.onboarding.locked_tooltip') }}</span>
                            </p>
                        </div>

                        <!-- Botões de ação -->
                        <div class="shrink-0 flex gap-2">
                            <!-- Locked: botão disabled com tooltip -->
                            <template v-if="step.status === 'locked'">
                                <button
                                    type="button"
                                    disabled
                                    :title="t('tenant.onboarding.locked_tooltip')"
                                    aria-disabled="true"
                                    class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground-subtle cursor-not-allowed"
                                >
                                    {{ t('tenant.onboarding.cta_start') }}
                                </button>
                            </template>

                            <!-- Pending: botão Iniciar (+ Pular se opcional) -->
                            <template v-else-if="step.status === 'pending'">
                                <button
                                    v-if="!step.required && step.key === 'first_professional'"
                                    type="button"
                                    :disabled="saving"
                                    class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground-muted transition hover:border-foreground-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="skipStep(step.key)"
                                >
                                    {{ t('tenant.onboarding.cta_skip') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-primary-700 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="toggleStep(step)"
                                >
                                    {{ activeStep === step.key ? t('common.cancel') : t('tenant.onboarding.cta_start') }}
                                </button>
                            </template>

                            <!-- Completed: botão Editar (apenas clinic_data nesta fase) -->
                            <template v-else-if="step.status === 'completed'">
                                <button
                                    v-if="step.key === 'clinic_data'"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:border-primary-300 hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="toggleStep(step)"
                                >
                                    {{ activeStep === step.key ? t('common.cancel') : t('tenant.onboarding.cta_edit') }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Painel expandido inline para clinic_data -->
                    <div
                        v-if="activeStep === step.key && step.key === 'clinic_data'"
                        class="border-t border-border bg-surface px-5 py-5"
                    >
                        <form
                            novalidate
                            @submit.prevent="onSubmitClinicData"
                        >
                            <!-- Endereço -->
                            <div class="mb-4">
                                <label
                                    for="clinic_address"
                                    class="mb-1.5 block text-sm font-medium text-foreground"
                                >
                                    {{ t('tenant.onboarding.fields.address') }}
                                </label>
                                <textarea
                                    id="clinic_address"
                                    v-model="clinicForm.address"
                                    name="address"
                                    rows="2"
                                    autocomplete="street-address"
                                    :aria-invalid="!!fieldError('address')"
                                    :aria-describedby="fieldError('address') ? 'address-error' : undefined"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200 resize-none"
                                    :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('address') }"
                                    placeholder="Rua das Flores, 123 — Jardim Saúde, São Paulo/SP"
                                ></textarea>
                                <p
                                    v-if="fieldError('address')"
                                    id="address-error"
                                    role="alert"
                                    class="mt-1 text-xs text-danger-500"
                                >
                                    {{ fieldError('address') }}
                                </p>
                            </div>

                            <!-- Telefone -->
                            <div class="mb-4">
                                <label
                                    for="clinic_phone"
                                    class="mb-1.5 block text-sm font-medium text-foreground"
                                >
                                    {{ t('tenant.onboarding.fields.phone') }}
                                </label>
                                <input
                                    id="clinic_phone"
                                    :value="clinicForm.phone"
                                    type="tel"
                                    name="phone"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="(11) 99999-0000"
                                    :aria-invalid="!!fieldError('phone')"
                                    :aria-describedby="fieldError('phone') ? 'phone-error' : undefined"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('phone') }"
                                    @input="onPhoneInput"
                                />
                                <p
                                    v-if="fieldError('phone')"
                                    id="phone-error"
                                    role="alert"
                                    class="mt-1 text-xs text-danger-500"
                                >
                                    {{ fieldError('phone') }}
                                </p>
                            </div>

                            <!-- Horário de funcionamento -->
                            <div class="mb-6">
                                <label
                                    for="clinic_opening_hours"
                                    class="mb-1.5 block text-sm font-medium text-foreground"
                                >
                                    {{ t('tenant.onboarding.fields.opening_hours') }}
                                </label>
                                <textarea
                                    id="clinic_opening_hours"
                                    v-model="clinicForm.opening_hours"
                                    name="opening_hours"
                                    rows="2"
                                    :aria-invalid="!!fieldError('opening_hours')"
                                    :aria-describedby="fieldError('opening_hours') ? 'opening_hours-error' : undefined"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200 resize-none"
                                    :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('opening_hours') }"
                                    placeholder="Seg-Sex: 8h-18h | Sáb: 8h-12h"
                                ></textarea>
                                <p
                                    v-if="fieldError('opening_hours')"
                                    id="opening_hours-error"
                                    role="alert"
                                    class="mt-1 text-xs text-danger-500"
                                >
                                    {{ fieldError('opening_hours') }}
                                </p>
                            </div>

                            <!-- Botões do form -->
                            <div class="flex items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="saving"
                                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {{ saving ? t('common.loading') : t('tenant.onboarding.cta_save') }}
                                </button>
                                <button
                                    type="button"
                                    disabled
                                    :title="t('tenant.onboarding.skip')"
                                    aria-disabled="true"
                                    class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground-subtle cursor-not-allowed"
                                >
                                    {{ t('tenant.onboarding.cta_skip') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </li>
            </ol>
        </div>

        <!-- Spec 012 — modal de novo profissional para step 2 do wizard -->
        <ProfessionalFormModal
            v-model:open="professionalModalOpen"
            :professional="null"
            @saved="onProfessionalSaved"
        />
    </main>
</template>
