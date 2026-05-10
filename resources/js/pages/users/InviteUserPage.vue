<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';

const { t } = useI18n();
const router = useRouter();

// ─── Estado ──────────────────────────────────────────────────────────────────

const form = reactive({
    email: '',
    intended_role: '',
});

const loading = ref(false);
const successMessage = ref(null);
const error = ref(null);
const validationErrors = ref({});

const ROLES = [
    { value: 'admin-clinica', label: t('users.invite.role_options.admin-clinica') },
    { value: 'medico', label: t('users.invite.role_options.medico') },
    { value: 'atendente', label: t('users.invite.role_options.atendente') },
    { value: 'recepcionista', label: t('users.invite.role_options.recepcionista') },
    { value: 'financeiro', label: t('users.invite.role_options.financeiro') },
];

// ─── Submit ───────────────────────────────────────────────────────────────────

async function onSubmit() {
    error.value = null;
    successMessage.value = null;
    validationErrors.value = {};
    loading.value = true;

    try {
        await api.post('/users/invitations', {
            email: form.email,
            intended_role: form.intended_role,
        });

        successMessage.value = t('users.invite.sent', { email: form.email });

        // Redireciona para a lista após breve pausa para o usuário ler o toast
        setTimeout(() => {
            router.push({ name: 'users.list' });
        }, 1500);
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 409 && body?.error === 'plan_limit_reached') {
            error.value = t('users.invite.plan_limit');
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
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-lg">

            <!-- Cabeçalho -->
            <div class="mb-6">
                <button
                    type="button"
                    class="mb-4 inline-flex items-center gap-1.5 text-sm text-foreground-muted hover:text-foreground transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                    @click="router.push({ name: 'users.list' })"
                >
                    <span aria-hidden="true">&#8592;</span>
                    {{ t('common.back') }}
                </button>
                <h1 class="text-2xl font-bold text-foreground">
                    {{ t('users.invite.title') }}
                </h1>
                <p class="mt-1 text-sm text-foreground-muted">
                    {{ t('users.invite.subtitle') }}
                </p>
            </div>

            <!-- Card do formulário -->
            <div class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">

                <!-- Sucesso -->
                <div
                    v-if="successMessage"
                    role="alert"
                    aria-live="polite"
                    class="mb-4 rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-700"
                >
                    {{ successMessage }}
                </div>

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

                    <!-- E-mail -->
                    <div class="mb-5">
                        <label
                            for="invite-email"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            {{ t('users.invite.email') }}
                        </label>
                        <input
                            id="invite-email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            :aria-invalid="!!fieldError('email')"
                            :aria-describedby="fieldError('email') ? 'invite-email-error' : undefined"
                            placeholder="colega@clinica.com.br"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            :class="{ 'border-danger-500 focus:border-danger-500': fieldError('email') }"
                        />
                        <p
                            v-if="fieldError('email')"
                            id="invite-email-error"
                            role="alert"
                            class="mt-1 text-xs text-danger-500"
                        >
                            {{ fieldError('email') }}
                        </p>
                    </div>

                    <!-- Papel (radio buttons) -->
                    <div class="mb-6">
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-foreground">
                                {{ t('users.invite.role') }}
                            </legend>

                            <div
                                v-if="fieldError('intended_role')"
                                id="invite-role-error"
                                role="alert"
                                class="mb-2 text-xs text-danger-500"
                            >
                                {{ fieldError('intended_role') }}
                            </div>

                            <div class="space-y-2">
                                <label
                                    v-for="roleOpt in ROLES"
                                    :key="roleOpt.value"
                                    class="flex items-center gap-3 cursor-pointer rounded-lg border border-border px-3 py-2.5 transition hover:bg-surface"
                                    :class="{
                                        'border-primary-500 bg-primary-50': form.intended_role === roleOpt.value,
                                        'border-danger-300': fieldError('intended_role'),
                                    }"
                                >
                                    <input
                                        type="radio"
                                        :value="roleOpt.value"
                                        v-model="form.intended_role"
                                        name="intended_role"
                                        :aria-describedby="fieldError('intended_role') ? 'invite-role-error' : undefined"
                                        class="h-4 w-4 border-border text-primary-600 accent-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    />
                                    <span class="text-sm text-foreground">{{ roleOpt.label }}</span>
                                </label>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Ações -->
                    <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="router.push({ name: 'users.list' })"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="loading || !!successMessage"
                            class="rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ loading ? t('common.loading') : t('users.invite.submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</template>
