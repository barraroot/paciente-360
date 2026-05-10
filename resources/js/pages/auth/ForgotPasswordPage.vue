<script setup>
import { ref, reactive } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';
import AuthHeroPanel from '@/components/auth/AuthHeroPanel.vue';

const { t } = useI18n();

// ─── Estado ─────────────────────────────────────────────────────────────────

const form = reactive({ email: '' });
const loading = ref(false);
const submitted = ref(false);
const error = ref(null);
const validationErrors = ref({});

// ─── Submit ──────────────────────────────────────────────────────────────────

async function onSubmit() {
    error.value = null;
    validationErrors.value = {};
    loading.value = true;

    try {
        await api.post('/auth/password/forgot', { email: form.email });
        // 202 — resposta sempre genérica, independente de o e-mail existir.
        submitted.value = true;
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 429) {
            const retryAfter =
                err.response.headers?.['retry-after'] ?? body?.retry_after ?? '?';
            error.value = t('auth.errors.throttle', { seconds: retryAfter });
        } else if (status === 422 && body?.errors) {
            validationErrors.value = body.errors;
        } else {
            // Qualquer outro erro: mostrar banner de sucesso mesmo assim
            // para não vazar se o e-mail existe ou não.
            submitted.value = true;
        }
    } finally {
        loading.value = false;
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? null;
}
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-screen bg-surface">
        <!-- ── Coluna esquerda — formulário ──────────────────────────────── -->
        <section
            class="flex min-h-screen items-center justify-center px-8 py-12"
            aria-label="Formulário de recuperação de senha"
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
                    <!-- Banner de sucesso (após envio) -->
                    <div
                        v-if="submitted"
                        role="alert"
                        aria-live="polite"
                        class="mb-6 rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-700"
                    >
                        {{ t('auth.password_reset.email_sent') }}
                    </div>

                    <template v-else>
                        <h1 class="text-xl font-semibold text-foreground mb-2">
                            {{ t('auth.password_reset.request_title') }}
                        </h1>
                        <p class="text-sm text-foreground-muted mb-6">
                            {{ t('auth.password_reset.request_help') }}
                        </p>

                        <!-- Banner de erro global (somente 429) -->
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
                            <div class="mb-6">
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

                            <!-- Botão submit -->
                            <button
                                type="submit"
                                :disabled="loading"
                                class="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ loading ? t('common.loading') : t('auth.password_reset.request_cta') }}
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
