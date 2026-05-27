<script setup>
import { ref, reactive, computed } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import AuthHeroPanel from '@/components/auth/AuthHeroPanel.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

// ─── Estado ─────────────────────────────────────────────────────────────────

const form = reactive({
    email: route.query.email ?? '',
    password: '',
    password_confirmation: '',
});

/** Token vem do parâmetro de rota /reset-password/:token */
const token = route.params.token;

const loading = ref(false);
const successMessage = ref(null);
const tokenInvalid = ref(false);
const error = ref(null);
const validationErrors = ref({});

// ─── Validação client-side ───────────────────────────────────────────────────

const clientErrors = computed(() => {
    const errs = {};

    if (form.password && form.password.length < 8) {
        errs.password = t('auth.password_reset.password_min');
    }

    if (
        form.password_confirmation &&
        form.password !== form.password_confirmation
    ) {
        errs.password_confirmation = t('auth.password_reset.password_mismatch');
    }

    return errs;
});

// ─── Submit ──────────────────────────────────────────────────────────────────

async function onSubmit() {
    // Bloqueia se há erros client-side
    if (Object.keys(clientErrors.value).length > 0) {
        return;
    }

    error.value = null;
    validationErrors.value = {};
    loading.value = true;

    try {
        await api.post('/auth/password/reset', {
            email: form.email,
            token,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });

        // 204 — sucesso
        successMessage.value = t('auth.password_reset.success');

        // Redireciona automaticamente após 2 s
        setTimeout(() => {
            router.push({ name: 'auth.login' });
        }, 2000);
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 410) {
            tokenInvalid.value = true;
        } else if (status === 422 && body?.errors) {
            validationErrors.value = body.errors;
        } else if (status === 429) {
            const retryAfter =
                err.response.headers?.['retry-after'] ?? body?.retry_after ?? '?';
            error.value = t('auth.errors.throttle', { seconds: retryAfter });
        } else {
            error.value = t('common.error_generic');
        }
    } finally {
        loading.value = false;
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? clientErrors.value[field] ?? null;
}
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-screen bg-surface">
        <!-- ── Coluna esquerda — formulário ──────────────────────────────── -->
        <section
            class="flex min-h-screen items-center justify-center px-8 py-12"
            aria-label="Formulário de redefinição de senha"
        >
            <div class="w-full max-w-md">
                <!-- Logotipo -->
                <div class="mb-10 flex items-center gap-2">
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

                <!-- Card -->
                <div class="rounded-xl bg-surface-elevated p-8 shadow-[var(--shadow-card)]">

                    <!-- Banner: token inválido/expirado (410) -->
                    <div
                        v-if="tokenInvalid"
                        role="alert"
                        aria-live="assertive"
                        class="mb-6 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-700"
                    >
                        <p class="mb-3">{{ t('auth.password_reset.token_expired_or_invalid') }}</p>
                        <RouterLink
                            to="/forgot-password"
                            class="font-semibold text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            {{ t('auth.password_reset.new_link_cta') }}
                        </RouterLink>
                    </div>

                    <!-- Banner: sucesso (204) -->
                    <div
                        v-else-if="successMessage"
                        role="alert"
                        aria-live="polite"
                        class="mb-6 rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-700"
                    >
                        <p class="mb-3">{{ successMessage }}</p>
                        <RouterLink
                            :to="{ name: 'auth.login' }"
                            class="font-semibold text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            {{ t('auth.password_reset.go_to_login') }}
                        </RouterLink>
                    </div>

                    <template v-else>
                        <h1 class="text-xl font-semibold text-foreground mb-6">
                            {{ t('auth.password_reset.reset_title') }}
                        </h1>

                        <!-- Banner de erro global -->
                        <div
                            v-if="error"
                            role="alert"
                            aria-live="assertive"
                            class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
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
                                    class="mt-1 text-xs text-danger-600"
                                >
                                    {{ fieldError('email') }}
                                </p>
                            </div>

                            <!-- Campo nova senha -->
                            <div class="mb-4">
                                <label
                                    for="password"
                                    class="mb-1.5 block text-sm font-medium text-foreground"
                                >
                                    {{ t('auth.login.password') }}
                                </label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    :aria-invalid="!!fieldError('password')"
                                    :aria-describedby="fieldError('password') ? 'password-error' : undefined"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('password') }"
                                />
                                <p
                                    v-if="fieldError('password')"
                                    id="password-error"
                                    role="alert"
                                    class="mt-1 text-xs text-danger-600"
                                >
                                    {{ fieldError('password') }}
                                </p>
                            </div>

                            <!-- Campo confirmação de senha -->
                            <div class="mb-6">
                                <label
                                    for="password_confirmation"
                                    class="mb-1.5 block text-sm font-medium text-foreground"
                                >
                                    {{ t('auth.password_reset.password_confirm_label') }}
                                </label>
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    :aria-invalid="!!fieldError('password_confirmation')"
                                    :aria-describedby="fieldError('password_confirmation') ? 'password-confirmation-error' : undefined"
                                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    :class="{ 'border-danger-500 focus:border-danger-500 focus:ring-danger-50': fieldError('password_confirmation') }"
                                />
                                <p
                                    v-if="fieldError('password_confirmation')"
                                    id="password-confirmation-error"
                                    role="alert"
                                    class="mt-1 text-xs text-danger-600"
                                >
                                    {{ fieldError('password_confirmation') }}
                                </p>
                            </div>

                            <!-- Botão submit -->
                            <button
                                type="submit"
                                :disabled="loading"
                                class="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ loading ? t('common.loading') : t('auth.password_reset.submit') }}
                            </button>
                        </form>
                    </template>

                    <!-- Link voltar ao login -->
                    <div class="mt-6 text-center">
                        <RouterLink
                            :to="{ name: 'auth.login' }"
                            class="text-sm text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            &larr; {{ t('common.back') }}
                        </RouterLink>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Coluna direita — hero ──────────────────────────────────────── -->
        <AuthHeroPanel />
    </div>
</template>
