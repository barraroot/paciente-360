<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth.js';
import AuthHeroPanel from '@/components/auth/AuthHeroPanel.vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

// ─── Estado do formulário ────────────────────────────────────────────────────
const form = reactive({
    email: '',
    password: '',
    remember: false,
});

const loading = ref(false);
const error = ref(null);
const validationErrors = ref({});

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Calcula minutos restantes até `isoDateString`, arredondando pra cima.
 *
 * @param {string} isoDateString - ISO 8601 (ex.: "2025-05-10T14:30:00Z")
 * @returns {number}
 */
function minutesUntil(isoDateString) {
    const diff = new Date(isoDateString) - Date.now();
    return Math.ceil(Math.max(0, diff) / 60_000);
}

// ─── Submit ─────────────────────────────────────────────────────────────────

async function onSubmit() {
    error.value = null;
    validationErrors.value = {};
    loading.value = true;

    try {
        await auth.login({
            email: form.email,
            password: form.password,
            remember: form.remember,
        });

        router.push(route.query.redirect || '/panel');
    } catch (err) {
        loading.value = false;

        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 401 && body?.error === 'invalid_credentials') {
            error.value = t('auth.errors.failed');
        } else if (status === 423 && body?.error === 'account_locked') {
            error.value = t('auth.login.locked', { minutes: minutesUntil(body.locked_until) });
        } else if (status === 403 && body?.error === 'tenant_suspended') {
            error.value = t('tenant.suspended.message');
        } else if (status === 422 && body?.errors) {
            // Erros de validação por campo
            validationErrors.value = body.errors;
            error.value = null;
        } else if (status === 429) {
            const retryAfter = err.response.headers?.['retry-after'] ?? body?.retry_after ?? '?';
            error.value = t('auth.errors.throttle', { seconds: retryAfter });
        } else {
            error.value = t('common.error_generic');
        }
    }
}

// ─── Helpers de validação de campo ───────────────────────────────────────────

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? null;
}
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-screen bg-surface">
        <!-- ── Coluna esquerda — formulário ─────────────────────────────── -->
        <section
            class="flex min-h-screen items-center justify-center px-8 py-12"
            aria-label="Formulário de acesso"
        >
            <div class="w-full max-w-md">
                <!-- Logotipo -->
                <div class="mb-10 flex items-center gap-2">
                    <!-- Logo SVG inline simples com o ícone P do brand -->
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

                <!-- Card branco -->
                <div class="rounded-xl bg-surface-elevated p-8 shadow-[var(--shadow-card)]">
                    <h1 class="text-xl font-semibold text-foreground mb-6">
                        {{ t('auth.login.title') }}
                    </h1>

                    <!-- Alerta de erro global -->
                    <div
                        v-if="error"
                        role="alert"
                        aria-live="assertive"
                        class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-500"
                    >
                        {{ error }}
                    </div>

                    <form @submit.prevent="onSubmit" novalidate>
                        <!-- Campo e-mail -->
                        <div class="mb-4">
                            <label
                                for="email"
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                {{ t('auth.login.email') }}
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                name="email"
                                required
                                autocomplete="email"
                                autofocus
                                :aria-invalid="!!fieldError('email')"
                                :aria-describedby="fieldError('email') ? 'email-error' : undefined"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('email') }"
                                placeholder="voce@clinica.com.br"
                            />
                            <p
                                v-if="fieldError('email')"
                                id="email-error"
                                role="alert"
                                class="mt-1 text-xs text-danger-500"
                            >
                                {{ fieldError('email') }}
                            </p>
                        </div>

                        <!-- Campo senha -->
                        <div class="mb-4">
                            <div class="mb-1.5 flex items-center justify-between">
                                <label
                                    for="password"
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{ t('auth.login.password') }}
                                </label>
                                <RouterLink
                                    to="/forgot-password"
                                    class="text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                                >
                                    {{ t('auth.login.forgot_password') }}
                                </RouterLink>
                            </div>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                :aria-invalid="!!fieldError('password')"
                                :aria-describedby="fieldError('password') ? 'password-error' : undefined"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('password') }"
                            />
                            <p
                                v-if="fieldError('password')"
                                id="password-error"
                                role="alert"
                                class="mt-1 text-xs text-danger-500"
                            >
                                {{ fieldError('password') }}
                            </p>
                        </div>

                        <!-- Checkbox lembrar-me -->
                        <div class="mb-6 flex items-center gap-2.5">
                            <input
                                id="remember"
                                v-model="form.remember"
                                type="checkbox"
                                name="remember"
                                class="h-4 w-4 rounded border-border text-primary-600 accent-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            />
                            <label for="remember" class="text-sm text-foreground-muted select-none">
                                {{ t('auth.login.remember') }}
                            </label>
                        </div>

                        <!-- Botão submit -->
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ loading ? t('common.loading') : t('auth.login.submit') }}
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- ── Coluna direita — hero / value props ──────────────────────── -->
        <AuthHeroPanel />
    </div>
</template>
