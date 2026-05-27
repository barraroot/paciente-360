<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth.js';
import api from '@/lib/api.js';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

// ─── Template download ────────────────────────────────────────────────────────

function baixarTemplate(formato) {
    const url = `${api.defaults.baseURL ?? '/api/v1'}/pacientes/importacao/template?formato=${formato}`;
    window.location.href = url;
}

// ─── Upload form ──────────────────────────────────────────────────────────────

const form = reactive({
    arquivo: null,
    status_inicial: 'lead',
});

const fileInput = ref(null);
const uploading = ref(false);
const errors = reactive({});

function onFileChange(event) {
    form.arquivo = event.target.files?.[0] ?? null;
    errors.arquivo = null;
}

async function submit() {
    if (!form.arquivo) {
        errors.arquivo = t('import.erro_arquivo_obrigatorio');
        return;
    }

    uploading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    const formData = new FormData();
    formData.append('arquivo', form.arquivo);
    formData.append('status_inicial', form.status_inicial);

    try {
        const { data } = await api.post('/pacientes/importacao', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const importacaoId = data.data?.id;
        if (importacaoId) {
            router.push({ name: 'pacientes.import.status', params: { id: importacaoId } });
        }
    } catch (err) {
        const status = err.response?.status;

        if (status === 413) {
            errors.arquivo = t('import.erro_arquivo_grande');
        } else if (status === 422) {
            const apiErrors = err.response?.data?.errors ?? {};
            Object.assign(errors, {
                arquivo: apiErrors.arquivo?.[0] ?? null,
                status_inicial: apiErrors.status_inicial?.[0] ?? null,
                _geral: apiErrors._geral?.[0] ?? null,
            });
        } else {
            errors._geral = t('common.error_generic');
        }
    } finally {
        uploading.value = false;
    }
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-2xl">
            <h1 class="mb-6 text-2xl font-bold text-foreground">
                {{ t('import.title') }}
            </h1>

            <!-- ── Baixar template ─────────────────────────────────────────── -->
            <section
                class="mb-8 rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]"
            >
                <h2 class="mb-2 text-base font-semibold text-foreground">
                    {{ t('import.template_title') }}
                </h2>
                <p class="mb-4 text-sm text-foreground-muted">
                    {{ t('import.template_help') }}
                </p>
                <div class="flex gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="baixarTemplate('csv')"
                    >
                        {{ t('import.download_csv') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="baixarTemplate('xlsx')"
                    >
                        {{ t('import.download_xlsx') }}
                    </button>
                </div>
            </section>

            <!-- ── Formulário de upload ────────────────────────────────────── -->
            <section
                class="rounded-xl border border-border bg-surface-elevated p-6 shadow-[var(--shadow-card)]"
            >
                <h2 class="mb-4 text-base font-semibold text-foreground">
                    {{ t('import.upload_title') }}
                </h2>

                <form @submit.prevent="submit" novalidate>
                    <!-- Arquivo -->
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-foreground">
                            {{ t('import.field_arquivo') }}
                        </label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            :aria-label="t('import.field_arquivo')"
                            class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground file:mr-4 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            :class="{ 'border-danger-500': errors.arquivo }"
                            @change="onFileChange"
                        />
                        <p v-if="errors.arquivo" role="alert" class="mt-1 text-xs text-danger-600">
                            {{ errors.arquivo }}
                        </p>
                        <p class="mt-1 text-xs text-foreground-muted">
                            {{ t('import.field_arquivo_help') }}
                        </p>
                    </div>

                    <!-- Status inicial -->
                    <div class="mb-6">
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-foreground">
                                {{ t('import.field_status_inicial') }}
                            </legend>
                            <div class="flex gap-6">
                                <label
                                    class="flex items-center gap-2 text-sm text-foreground cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        name="status_inicial"
                                        value="lead"
                                        v-model="form.status_inicial"
                                        class="accent-primary-600"
                                    />
                                    {{ t('paciente.status.lead') }}
                                </label>
                                <label
                                    class="flex items-center gap-2 text-sm text-foreground cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        name="status_inicial"
                                        value="ativo"
                                        v-model="form.status_inicial"
                                        class="accent-primary-600"
                                    />
                                    {{ t('paciente.status.ativo') }}
                                </label>
                            </div>
                            <p
                                v-if="errors.status_inicial"
                                role="alert"
                                class="mt-1 text-xs text-danger-600"
                            >
                                {{ errors.status_inicial }}
                            </p>
                        </fieldset>
                    </div>

                    <!-- Erro geral -->
                    <div
                        v-if="errors._geral"
                        role="alert"
                        aria-live="assertive"
                        class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
                    >
                        {{ errors._geral }}
                    </div>

                    <!-- Ações -->
                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            :disabled="uploading"
                            class="inline-flex items-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ uploading ? t('common.loading') : t('import.submit_cta') }}
                        </button>
                        <button
                            type="button"
                            class="text-sm text-foreground-muted underline hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="router.push('/panel/pacientes')"
                        >
                            {{ t('common.back') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</template>
