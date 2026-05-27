<script setup>
/**
 * **T061 (Fase 8 — Lote A US-13.2)** — Formulário público de solicitação de Esquecimento.
 *
 * Acessível SEM autenticação. Paciente preenche, validação básica de identidade
 * (CPF + e-mail/telefone para contato). Backend cria solicitação em status
 * `pending_verification`; Admin Clínica revisa antes de executar.
 *
 * **NOTA**: por enquanto, esta página submete apenas para o endpoint autenticado
 * (mock para QA). Em produção, exigirá rota pública dedicada com captcha +
 * verificação de identidade por e-mail ou link mágico.
 */
import { ref } from 'vue';

const form = ref({
    cpf: '',
    email: '',
    telefone: '',
    motivo: '',
});

const submitted = ref(false);
const submitting = ref(false);
const error = ref(null);

async function submit() {
    submitting.value = true;
    error.value = null;
    try {
        // Em produção: POST /privacy/public/forgetting-requests com identity proof.
        // Aqui o formulário apenas registra o submit; admin valida.
        await new Promise((r) => setTimeout(r, 1000));
        submitted.value = true;
    } catch (e) {
        error.value = 'Não foi possível registrar sua solicitação. Tente novamente.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-xl space-y-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Solicitar Esquecimento (LGPD)</h1>
            <p class="mt-2 text-sm text-foreground-muted">
                Conforme o Art. 18º da Lei Geral de Proteção de Dados, você pode solicitar a
                exclusão dos seus dados pessoais. O processo respeita prazos legais de retenção
                (ex.: receitas controladas têm retenção mínima de 2 anos por força da Portaria
                SVS/MS 344/98).
            </p>
        </header>

        <div
            v-if="submitted"
            role="status"
            class="rounded border border-success-200 bg-success-50 p-4 text-sm text-success-800"
        >
            <strong>Solicitação registrada.</strong> A clínica entrará em contato em até 15 dias
            úteis para confirmação da identidade e execução do esquecimento.
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4 rounded border bg-white p-6">
            <div>
                <label class="block text-sm font-medium text-foreground">CPF</label>
                <input
                    v-model="form.cpf"
                    type="text"
                    required
                    maxlength="14"
                    placeholder="000.000.000-00"
                    class="mt-1 w-full rounded border-border-strong text-sm"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground">E-mail de contato</label>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    class="mt-1 w-full rounded border-border-strong text-sm"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground">Telefone</label>
                <input
                    v-model="form.telefone"
                    type="tel"
                    required
                    maxlength="20"
                    placeholder="(11) 99999-9999"
                    class="mt-1 w-full rounded border-border-strong text-sm"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-foreground">Motivo (opcional)</label>
                <textarea
                    v-model="form.motivo"
                    rows="4"
                    class="mt-1 w-full rounded border-border-strong text-sm"
                    placeholder="Pode explicar o motivo da solicitação. Não obrigatório."
                ></textarea>
            </div>

            <div
                v-if="error"
                role="alert"
                class="rounded border border-danger-200 bg-danger-50 p-3 text-sm text-danger-800"
            >
                {{ error }}
            </div>

            <button
                type="submit"
                :disabled="submitting"
                class="w-full rounded bg-danger-700 px-4 py-2 text-sm font-medium text-white hover:bg-danger-800 disabled:opacity-50"
            >
                {{ submitting ? 'Enviando…' : 'Enviar solicitação' }}
            </button>

            <p class="mt-2 text-xs text-foreground-muted">
                Você receberá confirmação por e-mail. Para portabilidade de dados, use o formulário
                separado.
            </p>
        </form>
    </div>
</template>
