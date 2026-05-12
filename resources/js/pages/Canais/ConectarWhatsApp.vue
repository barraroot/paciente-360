<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useCanaisStore } from '@/stores/canais.js';

const { t } = useI18n();
const router = useRouter();
const canaisStore = useCanaisStore();

// ─── Form state ───────────────────────────────────────────────────────────────

const form = reactive({
    name: '',
    account_sid: '',
    auth_token: '',
    messaging_service_sid: '',
    whatsapp_sender: '',
});

const errors = reactive({
    name: null,
    account_sid: null,
    auth_token: null,
    messaging_service_sid: null,
    whatsapp_sender: null,
});

const globalError = ref(null);
const submitting = ref(false);
const showToken = ref(false);

// ─── Submissão ────────────────────────────────────────────────────────────────

function clearErrors() {
    Object.keys(errors).forEach((k) => { errors[k] = null; });
    globalError.value = null;
}

async function handleSubmit() {
    clearErrors();
    submitting.value = true;

    const payload = {
        type: 'whatsapp',
        name: form.name,
        credentials: {
            account_sid: form.account_sid,
            auth_token: form.auth_token,
            messaging_service_sid: form.messaging_service_sid,
            whatsapp_sender: form.whatsapp_sender,
        },
    };

    try {
        const channel = await canaisStore.connect(payload);
        // Redireciona para a página de detalhe com toast de sucesso (simulado via query)
        router.push({
            name: 'canais.show',
            params: { id: channel.id },
            query: { toast: 'connected' },
        });
    } catch (err) {
        const status = err.response?.status;
        const responseData = err.response?.data;

        if (status === 422) {
            // Validação de campos
            const fieldErrors = responseData?.errors ?? {};
            const credErrors = responseData?.errors?.credentials ?? {};

            // Mapeia erros de campos diretos
            if (fieldErrors.name) { errors.name = Array.isArray(fieldErrors.name) ? fieldErrors.name[0] : fieldErrors.name; }
            if (fieldErrors['credentials.account_sid'] || credErrors.account_sid) {
                errors.account_sid = fieldErrors['credentials.account_sid']?.[0] ?? credErrors.account_sid?.[0] ?? null;
            }
            if (fieldErrors['credentials.auth_token'] || credErrors.auth_token) {
                errors.auth_token = fieldErrors['credentials.auth_token']?.[0] ?? credErrors.auth_token?.[0] ?? null;
            }
            if (fieldErrors['credentials.messaging_service_sid'] || credErrors.messaging_service_sid) {
                errors.messaging_service_sid = fieldErrors['credentials.messaging_service_sid']?.[0] ?? credErrors.messaging_service_sid?.[0] ?? null;
            }
            if (fieldErrors['credentials.whatsapp_sender'] || credErrors.whatsapp_sender) {
                errors.whatsapp_sender = fieldErrors['credentials.whatsapp_sender']?.[0] ?? credErrors.whatsapp_sender?.[0] ?? null;
            }

            // Erros semânticos (invalid_credentials, sender_not_business, etc.)
            const errorCode = responseData?.error ?? responseData?.message;
            if (errorCode === 'invalid_credentials' || errorCode?.includes('invalid_credentials')) {
                globalError.value = t('canais.errors.invalid_credentials');
            } else if (!Object.values(errors).some(Boolean)) {
                // Fallback genérico para 422 sem campo específico
                globalError.value = responseData?.message ?? t('common.error_generic');
            }
        } else if (status === 409) {
            globalError.value = t('canais.errors.already_connected');
        } else if (status === 403) {
            globalError.value = t('canais.errors.no_permission');
        } else {
            globalError.value = t('common.error_generic');
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-lg">

            <!-- ── Breadcrumb ─────────────────────────────────────────────────── -->
            <nav
                aria-label="Breadcrumb"
                class="mb-4 flex items-center gap-1.5 text-xs text-foreground-muted"
            >
                <a
                    href="/panel"
                    class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                >
                    Painel
                </a>
                <span aria-hidden="true">›</span>
                <a
                    href="/panel/canais"
                    class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                    @click.prevent="router.push({ name: 'canais.index' })"
                >
                    {{ t('canais.titulo') }}
                </a>
                <span aria-hidden="true">›</span>
                <span class="text-foreground">{{ t('canais.conectar_whatsapp') }}</span>
            </nav>

            <!-- ── Card do formulário ─────────────────────────────────────────── -->
            <div class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]">

                <!-- Cabeçalho -->
                <div class="mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white text-sm font-bold"
                            aria-hidden="true"
                        >
                            W
                        </span>
                        <h1 class="text-xl font-bold text-foreground">
                            {{ t('canais.conectar_whatsapp') }}
                        </h1>
                    </div>
                    <p class="text-sm text-foreground-muted">
                        Precisamos das credenciais Twilio do seu Messaging Service.
                        <a
                            href="https://console.twilio.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-primary-600 underline hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            Ver no Twilio Console
                        </a>.
                    </p>
                </div>

                <!-- Alerta de erro global -->
                <div
                    v-if="globalError"
                    role="alert"
                    aria-live="assertive"
                    class="mb-5 rounded-lg border border-danger-400 bg-danger-50 px-4 py-3 text-sm text-danger-700"
                >
                    {{ globalError }}
                </div>

                <!-- Formulário -->
                <form
                    novalidate
                    @submit.prevent="handleSubmit"
                >
                    <!-- Nome do canal -->
                    <div class="mb-4">
                        <label
                            for="canal-nome"
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            {{ t('canais.form.nome') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="canal-nome"
                            v-model="form.name"
                            type="text"
                            required
                            autocomplete="off"
                            :placeholder="t('canais.form.nome_placeholder')"
                            :aria-invalid="!!errors.name"
                            :aria-describedby="errors.name ? 'erro-nome' : undefined"
                            class="w-full rounded-lg border px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2"
                            :class="errors.name
                                ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'"
                        />
                        <p
                            v-if="errors.name"
                            id="erro-nome"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.name }}
                        </p>
                    </div>

                    <!-- Account SID -->
                    <div class="mb-4">
                        <label
                            for="account-sid"
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            {{ t('canais.form.account_sid') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="account-sid"
                            v-model="form.account_sid"
                            type="text"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            :placeholder="t('canais.form.account_sid_placeholder')"
                            :aria-invalid="!!errors.account_sid"
                            :aria-describedby="`help-account-sid${errors.account_sid ? ' erro-account-sid' : ''}`"
                            class="w-full rounded-lg border px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2 font-mono"
                            :class="errors.account_sid
                                ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'"
                        />
                        <p
                            id="help-account-sid"
                            class="mt-1 text-xs text-foreground-muted"
                        >
                            {{ t('canais.form.account_sid_help') }}
                        </p>
                        <p
                            v-if="errors.account_sid"
                            id="erro-account-sid"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.account_sid }}
                        </p>
                    </div>

                    <!-- Auth Token -->
                    <div class="mb-4">
                        <label
                            for="auth-token"
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            {{ t('canais.form.auth_token') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="auth-token"
                                v-model="form.auth_token"
                                :type="showToken ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                spellcheck="false"
                                placeholder="••••••••••••••••••••••••••••••••"
                                :aria-invalid="!!errors.auth_token"
                                :aria-describedby="`help-auth-token${errors.auth_token ? ' erro-auth-token' : ''}`"
                                class="w-full rounded-lg border px-3 py-2 pr-20 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2 font-mono"
                                :class="errors.auth_token
                                    ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                    : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'"
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-medium text-primary-600 hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                                :aria-label="showToken ? t('canais.form.hide_token') : t('canais.form.show_token')"
                                @click="showToken = !showToken"
                            >
                                {{ showToken ? t('canais.form.hide_token') : t('canais.form.show_token') }}
                            </button>
                        </div>
                        <p
                            id="help-auth-token"
                            class="mt-1 text-xs text-foreground-muted"
                        >
                            {{ t('canais.form.auth_token_help') }}
                        </p>
                        <p
                            v-if="errors.auth_token"
                            id="erro-auth-token"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.auth_token }}
                        </p>
                    </div>

                    <!-- Messaging Service SID -->
                    <div class="mb-4">
                        <label
                            for="messaging-service-sid"
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            {{ t('canais.form.messaging_service_sid') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="messaging-service-sid"
                            v-model="form.messaging_service_sid"
                            type="text"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            :placeholder="t('canais.form.messaging_service_sid_placeholder')"
                            :aria-invalid="!!errors.messaging_service_sid"
                            :aria-describedby="`help-msg-sid${errors.messaging_service_sid ? ' erro-msg-sid' : ''}`"
                            class="w-full rounded-lg border px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2 font-mono"
                            :class="errors.messaging_service_sid
                                ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'"
                        />
                        <p
                            id="help-msg-sid"
                            class="mt-1 text-xs text-foreground-muted"
                        >
                            {{ t('canais.form.messaging_service_sid_help') }}
                        </p>
                        <p
                            v-if="errors.messaging_service_sid"
                            id="erro-msg-sid"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.messaging_service_sid }}
                        </p>
                    </div>

                    <!-- WhatsApp Sender -->
                    <div class="mb-6">
                        <label
                            for="whatsapp-sender"
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            {{ t('canais.form.whatsapp_sender') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="whatsapp-sender"
                            v-model="form.whatsapp_sender"
                            type="text"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            :placeholder="t('canais.form.whatsapp_sender_placeholder')"
                            :aria-invalid="!!errors.whatsapp_sender"
                            :aria-describedby="`help-sender${errors.whatsapp_sender ? ' erro-sender' : ''}`"
                            class="w-full rounded-lg border px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2 font-mono"
                            :class="errors.whatsapp_sender
                                ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'"
                        />
                        <p
                            id="help-sender"
                            class="mt-1 text-xs text-foreground-muted"
                        >
                            {{ t('canais.form.whatsapp_sender_help') }}
                        </p>
                        <p
                            v-if="errors.whatsapp_sender"
                            id="erro-sender"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.whatsapp_sender }}
                        </p>
                    </div>

                    <!-- Botões -->
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="router.push({ name: 'canais.index' })"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg
                                v-if="submitting"
                                class="h-4 w-4 animate-spin"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ submitting ? t('common.loading') : t('canais.form.submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</template>
