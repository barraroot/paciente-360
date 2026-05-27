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
    page_id: '',
    page_access_token: '',
    ig_business_account_id: '',
});

const errors = reactive({
    name: null,
    page_id: null,
    page_access_token: null,
    ig_business_account_id: null,
});

const globalError = ref(null);
const submitting = ref(false);
const showToken = ref(false);

// ─── Submissão ────────────────────────────────────────────────────────────────

function clearErrors() {
    Object.keys(errors).forEach((k) => {
        errors[k] = null;
    });
    globalError.value = null;
}

async function handleSubmit() {
    clearErrors();
    submitting.value = true;

    const payload = {
        type: 'instagram',
        name: form.name,
        credentials: {
            page_id: form.page_id,
            page_access_token: form.page_access_token,
            ig_business_account_id: form.ig_business_account_id,
        },
    };

    try {
        const channel = await canaisStore.connect(payload);
        router.push({
            name: 'canais.show',
            params: { id: channel.id },
            query: { toast: 'connected' },
        });
    } catch (err) {
        const status = err.response?.status;
        const responseData = err.response?.data;

        if (status === 422) {
            const fieldErrors = responseData?.errors ?? {};

            if (fieldErrors.name) {
                errors.name = Array.isArray(fieldErrors.name)
                    ? fieldErrors.name[0]
                    : fieldErrors.name;
            }
            if (fieldErrors['credentials.page_id']) {
                errors.page_id = fieldErrors['credentials.page_id'][0];
            }
            if (fieldErrors['credentials.page_access_token']) {
                errors.page_access_token = fieldErrors['credentials.page_access_token'][0];
            }
            if (fieldErrors['credentials.ig_business_account_id']) {
                errors.ig_business_account_id =
                    fieldErrors['credentials.ig_business_account_id'][0];
            }

            // Erros semânticos do adapter (conta pessoal, token inválido)
            const errorCode = responseData?.error ?? responseData?.message ?? '';
            if (
                errorCode.includes('account_type') ||
                errorCode.includes('PERSONAL') ||
                errorCode === 'account_type_invalid'
            ) {
                globalError.value = t('canais.errors.account_type_invalid');
            } else if (errorCode.includes('invalid_credentials') || errorCode.includes('token')) {
                globalError.value = t('canais.errors.page_token_invalid');
            } else if (!Object.values(errors).some(Boolean)) {
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
                <span class="text-foreground">{{ t('canais.conectar_instagram_titulo') }}</span>
            </nav>

            <!-- ── Card do formulário ─────────────────────────────────────────── -->
            <div
                class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]"
            >
                <!-- Cabeçalho -->
                <div class="mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#833AB4] via-[#C13584] to-[#FD1D1D] text-white text-sm font-bold"
                            aria-hidden="true"
                        >
                            IG
                        </span>
                        <h1 class="text-xl font-bold text-foreground">
                            {{ t('canais.conectar_instagram_titulo') }}
                        </h1>
                    </div>
                    <p class="text-sm text-foreground-muted">
                        {{ t('canais.conectar_instagram_descricao') }}
                        <a
                            href="https://developers.facebook.com/docs/instagram-api/getting-started"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-primary-600 underline hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            Ver tutorial Meta Business </a
                        >.
                    </p>
                </div>

                <!-- Aviso janela 24h -->
                <div
                    class="mb-5 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-xs text-warning-800"
                    role="note"
                >
                    {{ t('canais.instagram_24h_window') }}
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
                <form novalidate @submit.prevent="handleSubmit">
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
                            :class="
                                errors.name
                                    ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                    : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'
                            "
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

                    <!-- Facebook Page ID -->
                    <div class="mb-4">
                        <label for="page-id" class="mb-1 block text-sm font-medium text-foreground">
                            {{ t('canais.instagram_form.page_id') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="page-id"
                            v-model="form.page_id"
                            type="text"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Exemplo: 98765432109876"
                            :aria-invalid="!!errors.page_id"
                            :aria-describedby="`help-page-id${errors.page_id ? ' erro-page-id' : ''}`"
                            class="w-full rounded-lg border px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2 font-mono"
                            :class="
                                errors.page_id
                                    ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                    : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'
                            "
                        />
                        <p id="help-page-id" class="mt-1 text-xs text-foreground-muted">
                            {{ t('canais.instagram_form.page_id_help') }}
                        </p>
                        <p
                            v-if="errors.page_id"
                            id="erro-page-id"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.page_id }}
                        </p>
                    </div>

                    <!-- Page Access Token -->
                    <div class="mb-4">
                        <label
                            for="page-access-token"
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            {{ t('canais.instagram_form.page_access_token') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="page-access-token"
                                v-model="form.page_access_token"
                                :type="showToken ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                spellcheck="false"
                                placeholder="EAA..."
                                :aria-invalid="!!errors.page_access_token"
                                :aria-describedby="`help-token${errors.page_access_token ? ' erro-token' : ''}`"
                                class="w-full rounded-lg border px-3 py-2 pr-20 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2 font-mono"
                                :class="
                                    errors.page_access_token
                                        ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                        : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'
                                "
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-medium text-primary-600 hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                                :aria-label="
                                    showToken
                                        ? t('canais.form.hide_token')
                                        : t('canais.form.show_token')
                                "
                                @click="showToken = !showToken"
                            >
                                {{
                                    showToken
                                        ? t('canais.form.hide_token')
                                        : t('canais.form.show_token')
                                }}
                            </button>
                        </div>
                        <p id="help-token" class="mt-1 text-xs text-foreground-muted">
                            {{ t('canais.instagram_form.page_access_token_help') }}
                        </p>
                        <p
                            v-if="errors.page_access_token"
                            id="erro-token"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.page_access_token }}
                        </p>
                    </div>

                    <!-- IG Business Account ID -->
                    <div class="mb-6">
                        <label
                            for="ig-business-account-id"
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            {{ t('canais.instagram_form.ig_business_account_id') }}
                            <span class="text-danger-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="ig-business-account-id"
                            v-model="form.ig_business_account_id"
                            type="text"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Exemplo: 123456789012345"
                            :aria-invalid="!!errors.ig_business_account_id"
                            :aria-describedby="`help-ig-id${errors.ig_business_account_id ? ' erro-ig-id' : ''}`"
                            class="w-full rounded-lg border px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:ring-2 font-mono"
                            :class="
                                errors.ig_business_account_id
                                    ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                                    : 'border-border bg-surface focus:border-primary-500 focus:ring-primary-200'
                            "
                        />
                        <p id="help-ig-id" class="mt-1 text-xs text-foreground-muted">
                            {{ t('canais.instagram_form.ig_business_account_id_help') }}
                        </p>
                        <p
                            v-if="errors.ig_business_account_id"
                            id="erro-ig-id"
                            role="alert"
                            class="mt-1 text-xs text-danger-600"
                        >
                            {{ errors.ig_business_account_id }}
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
                                    d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"
                                />
                            </svg>
                            {{
                                submitting
                                    ? t('common.loading')
                                    : t('canais.conectar_instagram_titulo')
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</template>
