<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api.js';

/**
 * T226 — Página de configuração do widget de chat web (admin).
 *
 * Rota: /canais/:id/widget (requer ability channel.connect)
 * API: GET/PUT /api/v1/inbox/widget-configs/{channelId}
 *      GET     /api/v1/inbox/widget-configs/{channelId}/snippet
 */

const props = defineProps({
    channelId: {
        type: [String, Number],
        required: true,
    },
});

const { t } = useI18n();

// ─── State ────────────────────────────────────────────────────────────────────

const loading = ref(true);
const saving = ref(false);
const toast = ref(null);
const snippet = ref('');
const snippetCopied = ref(false);

const form = reactive({
    appearance: {
        primary_color: '#0F59A0',
        position: 'bottom-right',
        logo_url: '',
        welcome_message: '',
    },
    business_hours: {
        monday: '',
        tuesday: '',
        wednesday: '',
        thursday: '',
        friday: '',
        saturday: '',
        sunday: '',
    },
    outside_hours_behavior: 'normal',
    outside_hours_message: '',
    pre_chat_form: 'opcional',
    allowed_origins: '',
    initial_message: '',
});

const errors = reactive({
    appearance: {},
    business_hours: {},
    outside_hours_behavior: null,
    outside_hours_message: null,
    pre_chat_form: null,
    allowed_origins: null,
});

// ─── Computed ─────────────────────────────────────────────────────────────────

const positionOptions = [
    { value: 'bottom-right', label: 'Canto inferior direito' },
    { value: 'bottom-left', label: 'Canto inferior esquerdo' },
    { value: 'top-right', label: 'Canto superior direito' },
    { value: 'top-left', label: 'Canto superior esquerdo' },
];

const behaviorOptions = [
    { value: 'normal', label: 'Normal (aceita mensagens)' },
    { value: 'fila', label: 'Fila (aceita e marca como fora do horário)' },
    { value: 'bloqueia', label: 'Bloqueia (impede envio)' },
];

const preChatOptions = [
    { value: 'opcional', label: 'Opcional (formulário exibido, mas pode pular)' },
    { value: 'exigido_para_iniciar', label: 'Obrigatório para iniciar sessão' },
    { value: 'exigido_para_enviar', label: 'Obrigatório para enviar mensagens' },
    { value: 'oculto', label: 'Oculto (sem formulário)' },
];

const weekdays = [
    { key: 'monday', label: 'Segunda-feira' },
    { key: 'tuesday', label: 'Terça-feira' },
    { key: 'wednesday', label: 'Quarta-feira' },
    { key: 'thursday', label: 'Quinta-feira' },
    { key: 'friday', label: 'Sexta-feira' },
    { key: 'saturday', label: 'Sábado' },
    { key: 'sunday', label: 'Domingo' },
];

const allowedOriginsArray = computed(() => {
    if (!form.allowed_origins.trim()) return [];
    return form.allowed_origins
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
    await Promise.all([loadConfig(), loadSnippet()]);
    loading.value = false;
});

async function loadConfig() {
    try {
        const { data } = await api.get(`/inbox/widget-configs/${props.channelId}`);
        const cfg = data.data ?? data;

        if (cfg.appearance) {
            Object.assign(form.appearance, cfg.appearance);
        }
        if (cfg.business_hours) {
            Object.assign(form.business_hours, cfg.business_hours);
        }
        form.outside_hours_behavior = cfg.outside_hours_behavior ?? 'normal';
        form.outside_hours_message = cfg.outside_hours_message ?? '';
        form.pre_chat_form = cfg.pre_chat_form ?? 'opcional';
        form.initial_message = cfg.initial_message ?? '';
        if (Array.isArray(cfg.allowed_origins) && cfg.allowed_origins.length) {
            form.allowed_origins = cfg.allowed_origins.join('\n');
        }
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Erro ao carregar configuração.', 'error');
    }
}

async function loadSnippet() {
    try {
        const res = await api.get(`/inbox/widget-configs/${props.channelId}/snippet`, {
            headers: { Accept: 'text/plain' },
            responseType: 'text',
        });
        snippet.value = res.data;
    } catch {
        snippet.value = '';
    }
}

// ─── Save ─────────────────────────────────────────────────────────────────────

function clearErrors() {
    errors.appearance = {};
    errors.business_hours = {};
    errors.outside_hours_behavior = null;
    errors.outside_hours_message = null;
    errors.pre_chat_form = null;
    errors.allowed_origins = null;
}

async function handleSave() {
    clearErrors();
    saving.value = true;

    const payload = {
        appearance: { ...form.appearance },
        business_hours: { ...form.business_hours },
        outside_hours_behavior: form.outside_hours_behavior,
        outside_hours_message: form.outside_hours_message,
        pre_chat_form: form.pre_chat_form,
        initial_message: form.initial_message,
        allowed_origins: allowedOriginsArray.value,
    };

    try {
        await api.put(`/inbox/widget-configs/${props.channelId}`, payload);
        showToast('Configuração salva com sucesso!', 'success');
        await loadSnippet();
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([field, messages]) => {
                const [section, key] = field.split('.');
                if (key && errors[section] !== undefined && typeof errors[section] === 'object') {
                    errors[section][key] = messages[0];
                } else if (errors[field] !== undefined) {
                    errors[field] = messages[0];
                }
            });
        }
        showToast(data?.message ?? 'Erro ao salvar. Verifique os campos.', 'error');
    } finally {
        saving.value = false;
    }
}

// ─── Copy Snippet ─────────────────────────────────────────────────────────────

async function copySnippet() {
    try {
        await navigator.clipboard.writeText(snippet.value);
        snippetCopied.value = true;
        setTimeout(() => { snippetCopied.value = false; }, 2500);
    } catch {
        // Clipboard API indisponível
    }
}

// ─── Toast helper ─────────────────────────────────────────────────────────────

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => { toast.value = null; }, 5000);
}
</script>

<template>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <!-- Toast -->
        <Transition name="fade">
            <div
                v-if="toast"
                :class="[
                    'mb-6 rounded-lg px-4 py-3 text-sm font-medium',
                    toast.type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800',
                ]"
            >
                {{ toast.msg }}
            </div>
        </Transition>

        <div v-if="loading" class="text-center py-20 text-gray-400">Carregando...</div>

        <form v-else @submit.prevent="handleSave" class="space-y-8">
            <!-- ── Aparência ─────────────────────────────────────────────── -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Aparência</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Cor primária -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Cor primária
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                v-model="form.appearance.primary_color"
                                class="h-10 w-16 rounded border border-gray-300 cursor-pointer"
                            />
                            <input
                                type="text"
                                v-model="form.appearance.primary_color"
                                placeholder="#0F59A0"
                                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <p v-if="errors.appearance.primary_color" class="mt-1 text-xs text-red-600">
                            {{ errors.appearance.primary_color }}
                        </p>
                    </div>

                    <!-- Posição -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Posição</label>
                        <select
                            v-model="form.appearance.position"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="opt in positionOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Logo URL -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL do logotipo (opcional)</label>
                        <input
                            type="url"
                            v-model="form.appearance.logo_url"
                            placeholder="https://seusite.com.br/logo.png"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <!-- Mensagem de boas-vindas -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Mensagem de boas-vindas
                        </label>
                        <input
                            type="text"
                            v-model="form.appearance.welcome_message"
                            placeholder="Olá! Como posso ajudar?"
                            maxlength="200"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>
            </section>

            <!-- ── Horários de Atendimento ──────────────────────────────── -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-1">Horários de atendimento</h2>
                <p class="text-xs text-gray-500 mb-4">
                    Formato: <code class="bg-gray-100 px-1 rounded">08:00-18:00</code> — deixe em branco para o dia fechado.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-for="day in weekdays" :key="day.key">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ day.label }}</label>
                        <input
                            type="text"
                            v-model="form.business_hours[day.key]"
                            placeholder="08:00-18:00"
                            pattern="^(\d{2}:\d{2}-\d{2}:\d{2})?$"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="errors.business_hours[day.key]" class="mt-1 text-xs text-red-600">
                            {{ errors.business_hours[day.key] }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- ── Comportamento fora do horário ───────────────────────── -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Fora do horário de atendimento</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Comportamento</label>
                        <select
                            v-model="form.outside_hours_behavior"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="opt in behaviorOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="errors.outside_hours_behavior" class="mt-1 text-xs text-red-600">
                            {{ errors.outside_hours_behavior }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Mensagem exibida fora do horário
                        </label>
                        <input
                            type="text"
                            v-model="form.outside_hours_message"
                            placeholder="Estamos fechados no momento. Retornaremos em breve."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>
            </section>

            <!-- ── Formulário pré-chat ──────────────────────────────────── -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Formulário pré-chat</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modo</label>
                    <select
                        v-model="form.pre_chat_form"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option v-for="opt in preChatOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="errors.pre_chat_form" class="mt-1 text-xs text-red-600">{{ errors.pre_chat_form }}</p>
                </div>
            </section>

            <!-- ── Domínios permitidos ─────────────────────────────────── -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-1">Domínios permitidos</h2>
                <p class="text-xs text-gray-500 mb-4">
                    Um domínio por linha (ex: <code class="bg-gray-100 px-1 rounded">https://seusite.com.br</code>).
                    Deixe em branco para aceitar qualquer origem.
                </p>
                <textarea
                    v-model="form.allowed_origins"
                    rows="4"
                    placeholder="https://seusite.com.br&#10;https://blog.seusite.com.br"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p v-if="errors.allowed_origins" class="mt-1 text-xs text-red-600">{{ errors.allowed_origins }}</p>
            </section>

            <!-- ── Snippet de instalação ───────────────────────────────── -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-1">Código de instalação</h2>
                <p class="text-xs text-gray-500 mb-4">
                    Cole este snippet no <code class="bg-gray-100 px-1 rounded">&lt;head&gt;</code> ou antes do
                    <code class="bg-gray-100 px-1 rounded">&lt;/body&gt;</code> do seu site.
                </p>

                <div class="relative">
                    <pre class="bg-gray-900 text-green-300 rounded-lg p-4 text-xs overflow-x-auto whitespace-pre-wrap break-all">{{ snippet || 'Salve a configuração para gerar o snippet.' }}</pre>
                    <button
                        v-if="snippet"
                        type="button"
                        @click="copySnippet"
                        class="absolute top-2 right-2 rounded bg-gray-700 px-2 py-1 text-xs text-white hover:bg-gray-600 transition"
                    >
                        {{ snippetCopied ? 'Copiado!' : 'Copiar' }}
                    </button>
                </div>
            </section>

            <!-- ── Actions ─────────────────────────────────────────────── -->
            <div class="flex justify-end gap-3">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    {{ saving ? 'Salvando...' : 'Salvar configuração' }}
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
