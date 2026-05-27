<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useIaStore } from '@/stores/ia.js';

const store = useIaStore();

const form = reactive({
    services: [],
    pricing: [],
    locations: [],
    deposit_policy: { exige_sinal: false, percentual: null, meio: '', texto: '' },
    tone: '',
    qualification_questions: [],
    free_form: '',
});

const errors = ref({});
const loading = ref(false);
const savedAt = ref(null);

function hydrate(data) {
    form.services = Array.isArray(data?.services) ? [...data.services] : [];
    form.pricing = Array.isArray(data?.pricing) ? [...data.pricing] : [];
    form.locations = Array.isArray(data?.locations) ? [...data.locations] : [];
    form.deposit_policy = {
        exige_sinal: data?.deposit_policy?.exige_sinal ?? false,
        percentual: data?.deposit_policy?.percentual ?? null,
        meio: data?.deposit_policy?.meio ?? '',
        texto: data?.deposit_policy?.texto ?? '',
    };
    form.tone = data?.tone ?? '';
    form.qualification_questions = Array.isArray(data?.qualification_questions)
        ? [...data.qualification_questions]
        : [];
    form.free_form = data?.free_form ?? '';
}

onMounted(async () => {
    loading.value = true;
    try {
        hydrate(await store.fetchWorkContext());
    } finally {
        loading.value = false;
    }
});

function addService() {
    form.services.push({ nome: '', descricao: '' });
}
function addPrice() {
    form.pricing.push({ item: '', valor_a_vista: '', valor_cartao: '', observacao: '' });
}
function addLocation() {
    form.locations.push({ cidade: '', endereco: '' });
}
function addQuestion() {
    form.qualification_questions.push('');
}
function removeAt(list, i) {
    list.splice(i, 1);
}

async function save() {
    errors.value = {};
    savedAt.value = null;
    try {
        const payload = {
            services: form.services.filter((s) => s.nome),
            pricing: form.pricing.filter((p) => p.item),
            locations: form.locations.filter((l) => l.cidade),
            deposit_policy: form.deposit_policy,
            tone: form.tone || null,
            qualification_questions: form.qualification_questions.filter((q) => q && q.trim()),
            free_form: form.free_form || null,
        };
        hydrate(await store.saveWorkContext(payload));
        savedAt.value = new Date();
    } catch (e) {
        errors.value = e?.response?.data?.errors ?? {};
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                {{ $t('ia.workContext.title') }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $t('ia.workContext.subtitle') }}
            </p>
        </header>

        <div v-if="loading" class="py-12 text-center text-gray-500">{{ $t('common.loading') }}</div>

        <form v-else class="space-y-8" @submit.prevent="save">
            <!-- Tom de voz -->
            <section class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $t('ia.workContext.tone') }}
                </label>
                <input
                    v-model="form.tone"
                    type="text"
                    maxlength="120"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    :placeholder="$t('ia.workContext.tonePlaceholder')"
                />
            </section>

            <!-- Serviços -->
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ $t('ia.workContext.services') }}
                    </h2>
                    <button
                        type="button"
                        class="text-sm text-primary-600 hover:underline"
                        @click="addService"
                    >
                        + {{ $t('common.add') }}
                    </button>
                </div>
                <div v-for="(s, i) in form.services" :key="`svc-${i}`" class="flex gap-2">
                    <input
                        v-model="s.nome"
                        type="text"
                        :placeholder="$t('ia.workContext.serviceName')"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <input
                        v-model="s.descricao"
                        type="text"
                        :placeholder="$t('ia.workContext.serviceDesc')"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <button
                        type="button"
                        class="px-2 text-gray-400 hover:text-red-600"
                        @click="removeAt(form.services, i)"
                    >
                        ✕
                    </button>
                </div>
            </section>

            <!-- Preços -->
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ $t('ia.workContext.pricing') }}
                    </h2>
                    <button
                        type="button"
                        class="text-sm text-primary-600 hover:underline"
                        @click="addPrice"
                    >
                        + {{ $t('common.add') }}
                    </button>
                </div>
                <div
                    v-for="(p, i) in form.pricing"
                    :key="`price-${i}`"
                    class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center"
                >
                    <input
                        v-model="p.item"
                        type="text"
                        :placeholder="$t('ia.workContext.priceItem')"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <input
                        v-model="p.valor_a_vista"
                        type="text"
                        :placeholder="$t('ia.workContext.priceCash')"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <input
                        v-model="p.valor_cartao"
                        type="text"
                        :placeholder="$t('ia.workContext.priceCard')"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <button
                        type="button"
                        class="px-2 text-gray-400 hover:text-red-600"
                        @click="removeAt(form.pricing, i)"
                    >
                        ✕
                    </button>
                </div>
            </section>

            <!-- Locais -->
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ $t('ia.workContext.locations') }}
                    </h2>
                    <button
                        type="button"
                        class="text-sm text-primary-600 hover:underline"
                        @click="addLocation"
                    >
                        + {{ $t('common.add') }}
                    </button>
                </div>
                <div v-for="(l, i) in form.locations" :key="`loc-${i}`" class="flex gap-2">
                    <input
                        v-model="l.cidade"
                        type="text"
                        :placeholder="$t('ia.workContext.city')"
                        class="w-40 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <input
                        v-model="l.endereco"
                        type="text"
                        :placeholder="$t('ia.workContext.address')"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <button
                        type="button"
                        class="px-2 text-gray-400 hover:text-red-600"
                        @click="removeAt(form.locations, i)"
                    >
                        ✕
                    </button>
                </div>
            </section>

            <!-- Política de sinal -->
            <section class="space-y-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    {{ $t('ia.workContext.depositPolicy') }}
                </h2>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.deposit_policy.exige_sinal" type="checkbox" />
                    {{ $t('ia.workContext.requiresDeposit') }}
                </label>
                <div
                    v-if="form.deposit_policy.exige_sinal"
                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                >
                    <input
                        v-model.number="form.deposit_policy.percentual"
                        type="number"
                        min="0"
                        max="100"
                        :placeholder="$t('ia.workContext.depositPercent')"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <input
                        v-model="form.deposit_policy.meio"
                        type="text"
                        :placeholder="$t('ia.workContext.depositMethod')"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <input
                        v-model="form.deposit_policy.texto"
                        type="text"
                        :placeholder="$t('ia.workContext.depositText')"
                        class="sm:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                </div>
                <p class="text-xs text-gray-500">{{ $t('ia.workContext.depositHint') }}</p>
            </section>

            <!-- Perguntas de qualificação -->
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ $t('ia.workContext.questions') }}
                    </h2>
                    <button
                        type="button"
                        class="text-sm text-primary-600 hover:underline"
                        @click="addQuestion"
                    >
                        + {{ $t('common.add') }}
                    </button>
                </div>
                <p class="text-xs text-gray-500">{{ $t('ia.workContext.questionsHint') }}</p>
                <div
                    v-for="(q, i) in form.qualification_questions"
                    :key="`q-${i}`"
                    class="flex items-center gap-2"
                >
                    <span class="w-5 text-sm text-gray-400">{{ i + 1 }}.</span>
                    <input
                        v-model="form.qualification_questions[i]"
                        type="text"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <button
                        type="button"
                        class="px-2 text-gray-400 hover:text-red-600"
                        @click="removeAt(form.qualification_questions, i)"
                    >
                        ✕
                    </button>
                </div>
            </section>

            <!-- Texto livre -->
            <section class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{
                    $t('ia.workContext.freeForm')
                }}</label>
                <textarea
                    v-model="form.free_form"
                    rows="6"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                    :placeholder="$t('ia.workContext.freeFormPlaceholder')"
                ></textarea>
            </section>

            <div
                v-if="Object.keys(errors).length"
                class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300"
            >
                {{ $t('ia.workContext.validationError') }}
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="store.saving"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                >
                    {{ store.saving ? $t('common.saving') : $t('common.save') }}
                </button>
                <span v-if="savedAt" class="text-sm text-green-600 dark:text-green-400">{{
                    $t('ia.workContext.saved')
                }}</span>
            </div>
        </form>
    </div>
</template>
