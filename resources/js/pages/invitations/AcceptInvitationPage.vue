<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth.js';
import api from '@/lib/api.js';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

// ─── Token da query string ─────────────────────────────────────────────────

const token = computed(() => route.query.token ?? null);

// ─── Estado do formulário ──────────────────────────────────────────────────

const form = reactive({
    name: '',
    password: '',
    password_confirmation: '',
});

const loading = ref(false);
const error = ref(null);
const expired = ref(false);
const validationErrors = ref({});

onMounted(() => {
    if (!token.value) {
        error.value = t('users.accept_invitation.invalid_link');
    }
});

// ─── Submit ────────────────────────────────────────────────────────────────

async function onSubmit() {
    if (!token.value) { return; }

    error.value = null;
    expired.value = false;
    validationErrors.value = {};
    loading.value = true;

    try {
        const response = await api.post('/users/invitations/accept', {
            token: token.value,
            name: form.name,
            password: form.password,
        });

        // Login automático: popula a auth store com os dados retornados pela API
        // e redireciona para /panel sem precisar de nova requisição de autenticação.
        const userData = response.data.data;
        auth.setUser(userData);
        auth.setTenant(userData.tenant);
        auth.setPermissions(userData.permissions ?? []);

        router.push('/panel');
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 410) {
            expired.value = true;
        } else if (status === 422 && body?.errors) {
            validationErrors.value = body.errors;
        } else {
            error.value = t('common.error_generic');
        }
    } finally {
        loading.value = false;
    }
}

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? null;
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-surface px-4 py-12">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="mb-8 flex items-center gap-2 justify-center">
                <svg
                    width="32"
                    height="32"
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

            <div class="rounded-xl bg-surface-elevated p-8 shadow-[var(--shadow-card)] border border-border">

                <h1 class="text-xl font-semibold text-foreground mb-1">
                    {{ t('users.accept_invitation.title') }}
                </h1>
                <p class="text-sm text-foreground-muted mb-6">
                    {{ t('users.accept_invitation.subtitle') }}
                </p>

                <!-- Link inválido (sem token) -->
                <div
                    v-if="!token"
                    role="alert"
                    aria-live="assertive"
                    class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-500 mb-4"
                >
                    {{ t('users.accept_invitation.invalid_link') }}
                    <div class="mt-3">
                        <RouterLink
                            to="/login"
                            class="font-medium underline hover:no-underline text-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            {{ t('auth.login.title') }}
                        </RouterLink>
                    </div>
                </div>

                <!-- Convite expirado / revogado (410) -->
                <div
                    v-else-if="expired"
                    role="alert"
                    aria-live="assertive"
                    class="rounded-lg border border-warning-400 bg-warning-50 px-4 py-3 text-sm text-warning-800"
                >
                    {{ t('users.accept_invitation.expired') }}
                    <div class="mt-3">
                        <RouterLink
                            to="/login"
                            class="font-medium underline hover:no-underline text-warning-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            {{ t('auth.login.title') }}
                        </RouterLink>
                    </div>
                </div>

                <!-- Formulário (token presente e não expirado) -->
                <template v-else>
                    <!-- Erro global -->
                    <div
                        v-if="error"
                        role="alert"
                        aria-live="assertive"
                        class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-500"
                    >
                        {{ error }}
                    </div>

                    <form @submit.prevent="onSubmit" novalidate>

                        <!-- Nome -->
                        <div class="mb-4">
                            <label
                                for="accept-name"
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                {{ t('users.accept_invitation.name') }}
                            </label>
                            <input
                                id="accept-name"
                                v-model="form.name"
                                type="text"
                                name="name"
                                required
                                autocomplete="name"
                                autofocus
                                :aria-invalid="!!fieldError('name')"
                                :aria-describedby="fieldError('name') ? 'accept-name-error' : undefined"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                :class="{ 'border-danger-500 focus:border-danger-500': fieldError('name') }"
                            />
                            <p
                                v-if="fieldError('name')"
                                id="accept-name-error"
                                role="alert"
                                class="mt-1 text-xs text-danger-500"
                            >
                                {{ fieldError('name') }}
                            </p>
                        </div>

                        <!-- Senha -->
                        <div class="mb-4">
                            <label
                                for="accept-password"
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                {{ t('users.accept_invitation.password') }}
                            </label>
                            <input
                                id="accept-password"
                                v-model="form.password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                :aria-invalid="!!fieldError('password')"
                                :aria-describedby="fieldError('password') ? 'accept-password-error' : undefined"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                :class="{ 'border-danger-500 focus:border-danger-500': fieldError('password') }"
                            />
                            <p
                                v-if="fieldError('password')"
                                id="accept-password-error"
                                role="alert"
                                class="mt-1 text-xs text-danger-500"
                            >
                                {{ fieldError('password') }}
                            </p>
                        </div>

                        <!-- Confirmar senha -->
                        <div class="mb-6">
                            <label
                                for="accept-password-confirmation"
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                {{ t('users.accept_invitation.password_confirmation_label') }}
                            </label>
                            <input
                                id="accept-password-confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                :aria-invalid="!!fieldError('password_confirmation')"
                                :aria-describedby="fieldError('password_confirmation') ? 'accept-pwconf-error' : undefined"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                :class="{ 'border-danger-500 focus:border-danger-500': fieldError('password_confirmation') }"
                            />
                            <p
                                v-if="fieldError('password_confirmation')"
                                id="accept-pwconf-error"
                                role="alert"
                                class="mt-1 text-xs text-danger-500"
                            >
                                {{ fieldError('password_confirmation') }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ loading ? t('common.loading') : t('users.accept_invitation.submit') }}
                        </button>
                    </form>
                </template>
            </div>
        </div>
    </main>
</template>
