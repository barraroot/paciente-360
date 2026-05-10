<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';
import api from '@/lib/api.js';

const { t } = useI18n();
const { formatCurrency } = useI18nFormat();
const router = useRouter();

// ─── Estado ─────────────────────────────────────────────────────────────────

const plans = ref([]);
const loading = ref(false);
const error = ref(null);

// painel de checkout inline por plano: { [plan.id]: { open, quantity, loading, error } }
const checkoutPanels = ref({});

// ─── Carregamento dos planos ─────────────────────────────────────────────────

async function fetchPlans() {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get('/billing/plans');
        plans.value = response.data.data ?? response.data;

        // Inicializa painel de checkout por plano
        plans.value.forEach((plan) => {
            checkoutPanels.value[plan.id] = {
                open: false,
                quantity: plan.included_professionals,
                loading: false,
                error: null,
            };
        });
    } catch {
        error.value = t('common.error_generic');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchPlans);

// ─── Checkout ────────────────────────────────────────────────────────────────

function openCheckout(plan) {
    // Fecha outros painéis
    Object.keys(checkoutPanels.value).forEach((id) => {
        if (Number(id) !== plan.id) {
            checkoutPanels.value[id].open = false;
        }
    });
    checkoutPanels.value[plan.id].open = true;
    checkoutPanels.value[plan.id].error = null;
    checkoutPanels.value[plan.id].quantity = plan.included_professionals;
}

function closeCheckout(plan) {
    checkoutPanels.value[plan.id].open = false;
}

async function submitCheckout(plan) {
    const panel = checkoutPanels.value[plan.id];
    panel.loading = true;
    panel.error = null;

    try {
        const response = await api.post('/billing/checkout', {
            plan_code: plan.code,
            professionals_quantity: panel.quantity,
        });

        const { checkout_url } = response.data;
        window.location.href = checkout_url;
    } catch (err) {
        const status = err.response?.status;

        if (status === 409) {
            panel.error = t('billing.checkout.already_subscribed');
        } else if (status === 403) {
            panel.error = t('billing.checkout.permission_denied');
        } else {
            panel.error = t('common.error_generic');
        }
    } finally {
        panel.loading = false;
    }
}
</script>

<template>
    <main class="min-h-screen bg-surface py-12 px-4">
        <div class="mx-auto max-w-5xl">
            <!-- Cabeçalho -->
            <div class="mb-10 text-center">
                <h1 class="text-3xl font-bold text-foreground">
                    {{ t('billing.plans.title') }}
                </h1>
                <p class="mt-2 text-foreground-muted">
                    {{ t('billing.plans.subtitle') }}
                </p>
            </div>

            <!-- Loading -->
            <div
                v-if="loading"
                class="flex items-center justify-center py-20"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="text-foreground-muted">{{ t('common.loading') }}</span>
            </div>

            <!-- Erro global -->
            <div
                v-else-if="error"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-500"
            >
                {{ error }}
            </div>

            <!-- Grid de planos -->
            <div
                v-else
                class="grid grid-cols-1 gap-6 lg:grid-cols-3"
            >
                <article
                    v-for="plan in plans"
                    :key="plan.id"
                    class="rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)] flex flex-col"
                    :aria-label="plan.name"
                >
                    <!-- Cabeçalho do card -->
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-semibold text-foreground">
                            {{ plan.name }}
                        </h2>
                        <p class="mt-3 text-3xl font-bold text-primary-700">
                            {{ formatCurrency(plan.base_price_cents) }}
                            <span class="text-sm font-normal text-foreground-muted">/mês</span>
                        </p>
                        <p
                            v-if="plan.description"
                            class="mt-2 text-sm text-foreground-muted"
                        >
                            {{ plan.description }}
                        </p>
                    </div>

                    <!-- Benefícios -->
                    <ul class="p-6 flex-1 space-y-3" role="list">
                        <li class="flex items-start gap-2 text-sm text-foreground">
                            <span class="text-success-600 font-bold" aria-hidden="true">✓</span>
                            {{ t('billing.plan.professionals_included', { count: plan.included_professionals }) }}
                        </li>
                        <li class="flex items-start gap-2 text-sm text-foreground">
                            <span class="text-success-600 font-bold" aria-hidden="true">✓</span>
                            {{ t('billing.plan.users_max', { count: plan.max_users }) }}
                        </li>
                        <li class="flex items-start gap-2 text-sm text-foreground">
                            <span class="text-success-600 font-bold" aria-hidden="true">✓</span>
                            {{ t('billing.plan.ai_credits_monthly', { count: plan.included_ai_messages }) }}
                        </li>
                    </ul>

                    <!-- Ação / Painel inline de checkout -->
                    <div class="p-6 pt-0">
                        <div v-if="!checkoutPanels[plan.id]?.open">
                            <button
                                type="button"
                                class="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openCheckout(plan)"
                            >
                                {{ t('billing.checkout.cta') }}
                            </button>
                        </div>

                        <!-- Painel inline de quantidade -->
                        <div
                            v-else
                            class="rounded-lg border border-border bg-surface p-4"
                        >
                            <!-- Erro do checkout -->
                            <div
                                v-if="checkoutPanels[plan.id].error"
                                role="alert"
                                aria-live="assertive"
                                class="mb-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-500"
                            >
                                {{ checkoutPanels[plan.id].error }}
                                <span v-if="checkoutPanels[plan.id].error === t('billing.checkout.already_subscribed')">
                                    &nbsp;
                                    <RouterLink
                                        to="/panel/billing/subscription"
                                        class="font-medium underline hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                                    >
                                        {{ t('billing.subscription.title') }}
                                    </RouterLink>
                                </span>
                            </div>

                            <!-- Input de quantidade -->
                            <label
                                :for="`quantity-${plan.id}`"
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                {{ t('billing.checkout.choose_quantity') }}
                            </label>
                            <input
                                :id="`quantity-${plan.id}`"
                                v-model.number="checkoutPanels[plan.id].quantity"
                                type="number"
                                :min="1"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200 mb-3"
                                :aria-label="t('billing.checkout.choose_quantity')"
                            />

                            <!-- Botões -->
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    :disabled="checkoutPanels[plan.id].loading"
                                    class="flex-1 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="submitCheckout(plan)"
                                >
                                    {{
                                        checkoutPanels[plan.id].loading
                                            ? t('common.loading')
                                            : t('billing.checkout.continue')
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="closeCheckout(plan)"
                                >
                                    {{ t('common.cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </main>
</template>
