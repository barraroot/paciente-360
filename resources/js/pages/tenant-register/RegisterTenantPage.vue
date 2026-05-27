<script setup>
import { ref, reactive, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import AuthHeroPanel from '@/components/auth/AuthHeroPanel.vue';

const { t } = useI18n();

// ─── Constante de subdomínio ────────────────────────────────────────────────
const SUBDOMAIN_SUFFIX = import.meta.env.VITE_SUBDOMAIN_SUFFIX ?? 'lvh.me';

// ─── Versão vigente dos termos ───────────────────────────────────────────────
const TERMS_VERSION = '1.0';

// ─── Estado do formulário ────────────────────────────────────────────────────
const form = reactive({
    clinic_name: '',
    cnpj: '',
    slug: '',
    responsible_name: '',
    responsible_email: '',
    responsible_phone: '',
    password: '',
    terms_accepted: false,
});

const loading = ref(false);
const error = ref(null);
const validationErrors = ref({});
const success = ref(false);

// ─── Máscaras inline ─────────────────────────────────────────────────────────

/**
 * Aplica máscara visual CNPJ: 00.000.000/0000-00.
 * Remove tudo que não é dígito, depois reformata até 14 dígitos.
 *
 * @param {string} value
 * @returns {string}
 */
function formatCnpj(value) {
    const digits = value.replace(/\D/g, '').slice(0, 14);
    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
    if (digits.length <= 8) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
    if (digits.length <= 12)
        return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
}

/**
 * Aplica máscara visual de telefone BR: (00) 00000-0000.
 * Remove não-dígitos, reformata até 11 dígitos.
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

function onCnpjInput(event) {
    form.cnpj = formatCnpj(event.target.value);
}

function onPhoneInput(event) {
    form.responsible_phone = formatPhone(event.target.value);
}

// ─── Preview de slug ─────────────────────────────────────────────────────────

const previewSlug = computed(() => {
    const raw = form.slug.trim() || form.clinic_name.trim().toLowerCase().replace(/\s+/g, '-');
    if (!raw) return null;
    return `${raw}.${SUBDOMAIN_SUFFIX}`;
});

// ─── Validação de senha ───────────────────────────────────────────────────────

const passwordStrong = computed(() => {
    const p = form.password;
    return p.length >= 8 && /[A-Z]/.test(p) && /[0-9]/.test(p);
});

// ─── Validação de slug ────────────────────────────────────────────────────────

const slugValid = computed(() => {
    if (!form.slug) return true; // opcional
    return /^[a-z][a-z0-9-]{1,62}$/.test(form.slug);
});

// ─── Helpers de erro de campo ─────────────────────────────────────────────────

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? null;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

async function onSubmit() {
    if (!form.terms_accepted) return;

    loading.value = true;
    error.value = null;
    validationErrors.value = {};

    const payload = {
        clinic_name: form.clinic_name,
        cnpj: form.cnpj.replace(/\D/g, ''),
        responsible_name: form.responsible_name,
        responsible_email: form.responsible_email,
        responsible_phone: form.responsible_phone,
        password: form.password,
        terms_accepted: true,
        terms_version: TERMS_VERSION,
    };

    if (form.slug.trim()) {
        payload.slug = form.slug.trim();
    }

    try {
        const { data } = await api.post('/tenants/register', payload);

        success.value = true;

        setTimeout(() => {
            window.location.href = data.login_url;
        }, 1500);
    } catch (err) {
        loading.value = false;

        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 422 && body?.errors) {
            validationErrors.value = body.errors;

            // Focar o primeiro campo com erro
            await nextTick();
            const firstErrorField = Object.keys(body.errors)[0];
            const el = document.getElementById(firstErrorField);
            if (el) el.focus();
        } else if (status === 429) {
            const retryAfter =
                err.response.headers?.['retry-after'] ?? body?.retry_after ?? '?';
            error.value = t('common.error_rate_limit', { seconds: retryAfter });
        } else {
            error.value = t('common.error_generic');
        }
    }
}
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-screen bg-surface">
        <!-- ── Coluna esquerda — formulário ─────────────────────────────── -->
        <section
            class="flex min-h-screen items-center justify-center px-8 py-12"
            aria-label="Formulário de cadastro de clínica"
        >
            <div class="w-full max-w-md">
                <!-- Logotipo -->
                <div class="mb-8 flex items-center gap-2">
                    <svg
                        width="36"
                        height="36"
                        viewBox="0 0 36 36"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >
                        <rect width="36" height="36" rx="8" fill="oklch(0.32 0.06 180)" />
                        <text
                            x="50%"
                            y="50%"
                            dominant-baseline="central"
                            text-anchor="middle"
                            fill="white"
                            font-size="18"
                            font-weight="600"
                            font-family="ui-sans-serif, system-ui, sans-serif"
                        >P</text>
                    </svg>
                    <span class="text-xl font-semibold text-foreground tracking-tight">
                        Paciente<span class="text-primary-700">360</span>
                    </span>
                </div>

                <!-- Cabeçalho -->
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-foreground">
                        {{ t('tenant.register.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-foreground-muted">
                        {{ t('tenant.register.subtitle') }}
                    </p>
                </div>

                <!-- Banner de sucesso -->
                <div
                    v-if="success"
                    role="status"
                    aria-live="polite"
                    class="mb-4 rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-700"
                >
                    {{ t('tenant.register.success') }}
                </div>

                <!-- Alerta de erro global -->
                <div
                    v-if="error"
                    role="alert"
                    aria-live="assertive"
                    class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
                >
                    {{ error }}
                </div>

                <form @submit.prevent="onSubmit" novalidate>
                    <!-- Nome da clínica -->
                    <div class="mb-4">
                        <label
                            for="clinic_name"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('tenant.register.name') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="clinic_name"
                            v-model="form.clinic_name"
                            type="text"
                            name="clinic_name"
                            required
                            autocomplete="organization"
                            :aria-invalid="!!fieldError('clinic_name')"
                            :aria-describedby="fieldError('clinic_name') ? 'clinic_name-error' : undefined"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('clinic_name') }"
                            placeholder="Clínica Saúde Total"
                        />
                        <p
                            v-if="fieldError('clinic_name')"
                            id="clinic_name-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('clinic_name') }}
                        </p>
                    </div>

                    <!-- CNPJ -->
                    <div class="mb-4">
                        <label
                            for="cnpj"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('tenant.register.cnpj') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="cnpj"
                            :value="form.cnpj"
                            type="text"
                            name="cnpj"
                            required
                            autocomplete="off"
                            inputmode="numeric"
                            placeholder="00.000.000/0000-00"
                            :aria-invalid="!!fieldError('cnpj')"
                            :aria-describedby="fieldError('cnpj') ? 'cnpj-error' : 'cnpj-help'"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('cnpj') }"
                            @input="onCnpjInput"
                        />
                        <p
                            v-if="fieldError('cnpj')"
                            id="cnpj-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('cnpj') }}
                        </p>
                        <p v-else id="cnpj-help" class="mt-1 text-xs text-foreground-muted">
                            {{ t('tenant.register.cnpj_help') }}
                        </p>
                    </div>

                    <!-- Slug (endereço) — opcional -->
                    <div class="mb-4">
                        <label
                            for="slug"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('tenant.register.slug_label') }}
                            <span class="ml-1 text-xs font-normal text-foreground-muted">(opcional)</span>
                        </label>
                        <input
                            id="slug"
                            v-model="form.slug"
                            type="text"
                            name="slug"
                            autocomplete="off"
                            placeholder="minha-clinica"
                            :aria-invalid="!!fieldError('slug') || (!slugValid && form.slug.length > 0)"
                            :aria-describedby="fieldError('slug') ? 'slug-error' : 'slug-help'"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{
                                'border-danger-500 focus:border-danger-500 focus:ring-danger-50':
                                    fieldError('slug') || (!slugValid && form.slug.length > 0),
                            }"
                        />
                        <p
                            v-if="fieldError('slug')"
                            id="slug-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('slug') }}
                        </p>
                        <p v-else id="slug-help" class="mt-1 text-xs text-foreground-muted">
                            <template v-if="form.slug && !slugValid">
                                <span class="text-danger-600">Use apenas letras minúsculas, números e hífens. Deve começar com letra.</span>
                            </template>
                            <template v-else-if="previewSlug">
                                {{ t('tenant.register.slug_help', { slug: previewSlug }) }}
                            </template>
                            <template v-else>
                                Geramos automaticamente baseado no nome.
                            </template>
                        </p>
                    </div>

                    <!-- Nome do responsável -->
                    <div class="mb-4">
                        <label
                            for="responsible_name"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('tenant.register.responsible_name') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="responsible_name"
                            v-model="form.responsible_name"
                            type="text"
                            name="responsible_name"
                            required
                            autocomplete="name"
                            :aria-invalid="!!fieldError('responsible_name')"
                            :aria-describedby="fieldError('responsible_name') ? 'responsible_name-error' : undefined"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('responsible_name') }"
                            placeholder="Dr. João Silva"
                        />
                        <p
                            v-if="fieldError('responsible_name')"
                            id="responsible_name-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('responsible_name') }}
                        </p>
                    </div>

                    <!-- E-mail do responsável -->
                    <div class="mb-4">
                        <label
                            for="responsible_email"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('tenant.register.responsible_email') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="responsible_email"
                            v-model="form.responsible_email"
                            type="email"
                            name="responsible_email"
                            required
                            autocomplete="email"
                            :aria-invalid="!!fieldError('responsible_email')"
                            :aria-describedby="fieldError('responsible_email') ? 'responsible_email-error' : undefined"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('responsible_email') }"
                            placeholder="joao@clinica.com.br"
                        />
                        <p
                            v-if="fieldError('responsible_email')"
                            id="responsible_email-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('responsible_email') }}
                        </p>
                    </div>

                    <!-- Telefone -->
                    <div class="mb-4">
                        <label
                            for="responsible_phone"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('tenant.register.responsible_phone') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="responsible_phone"
                            :value="form.responsible_phone"
                            type="tel"
                            name="responsible_phone"
                            required
                            autocomplete="tel"
                            inputmode="tel"
                            placeholder="(11) 99999-0000"
                            :aria-invalid="!!fieldError('responsible_phone')"
                            :aria-describedby="fieldError('responsible_phone') ? 'responsible_phone-error' : undefined"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('responsible_phone') }"
                            @input="onPhoneInput"
                        />
                        <p
                            v-if="fieldError('responsible_phone')"
                            id="responsible_phone-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('responsible_phone') }}
                        </p>
                    </div>

                    <!-- Senha -->
                    <div class="mb-4">
                        <label
                            for="password"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('tenant.register.password_label') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            :aria-invalid="!!fieldError('password')"
                            :aria-describedby="fieldError('password') ? 'password-error' : 'password-help'"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('password') }"
                        />
                        <!-- Indicador de força da senha -->
                        <div
                            v-if="form.password.length > 0"
                            class="mt-1.5 flex items-center gap-1.5"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <span
                                class="h-1.5 flex-1 rounded-full transition-colors"
                                :class="passwordStrong ? 'bg-success-500' : 'bg-danger-400'"
                            ></span>
                            <span
                                class="text-xs font-medium"
                                :class="passwordStrong ? 'text-success-600' : 'text-danger-600'"
                            >
                                {{
                                    passwordStrong
                                        ? t('tenant.register.password_strength_ok')
                                        : t('tenant.register.password_strength_weak')
                                }}
                            </span>
                        </div>
                        <p
                            v-if="fieldError('password')"
                            id="password-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('password') }}
                        </p>
                        <p v-else id="password-help" class="mt-1 text-xs text-foreground-muted">
                            {{ t('tenant.register.password_help') }}
                        </p>
                    </div>

                    <!-- Aceite dos termos -->
                    <div class="mb-6">
                        <div class="flex items-start gap-2.5">
                            <input
                                id="terms_accepted"
                                v-model="form.terms_accepted"
                                type="checkbox"
                                name="terms_accepted"
                                required
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-border text-primary-600 accent-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            />
                            <label
                                for="terms_accepted"
                                class="text-sm text-foreground-muted select-none leading-snug"
                            >
                                <i18n-t keypath="tenant.register.terms_label" tag="span">
                                    <template #terms>
                                        <a
                                            href="/terms"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-primary-600 underline hover:text-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                                        >termos de uso</a>
                                    </template>
                                    <template #privacy>
                                        <a
                                            href="/privacy"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-primary-600 underline hover:text-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                                        >política de privacidade</a>
                                    </template>
                                </i18n-t>
                            </label>
                        </div>
                        <p
                            v-if="!form.terms_accepted && validationErrors.terms_accepted"
                            id="terms-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ fieldError('terms_accepted') }}
                        </p>
                    </div>

                    <!-- Botão submit -->
                    <button
                        type="submit"
                        :disabled="loading || !form.terms_accepted || success"
                        class="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ loading ? t('common.loading') : t('tenant.register.submit') }}
                    </button>

                    <!-- Link de volta ao login -->
                    <p class="mt-4 text-center text-sm text-foreground-muted">
                        <a
                            href="/login"
                            class="text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            {{ t('tenant.register.cta_back_to_login') }}
                        </a>
                    </p>
                </form>
            </div>
        </section>

        <!-- ── Coluna direita — hero panel ─────────────────────────────── -->
        <AuthHeroPanel />
    </div>
</template>
