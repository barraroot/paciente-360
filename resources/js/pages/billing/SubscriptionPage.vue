<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import api from '@/lib/api.js';

const { t } = useI18n();
const { formatCurrency, formatDate } = useI18nFormat();

// ─── Estado principal ────────────────────────────────────────────────────────

const subscription = ref(null);
const loading = ref(false);
const noSubscription = ref(false);
const error = ref(null);

// ─── Estado: stepper de profissionais ────────────────────────────────────────

const localQty = ref(1);
const showProfessionalsModal = ref(false);
const professionalsLoading = ref(false);
const professionalsError = ref(null);
const professionalsProrationBehavior = ref('create_prorations');
const proratedPreview = ref(null);
const proratedPreviewLoading = ref(false);

// ─── Estado: troca de plano ──────────────────────────────────────────────────

const showPlanModal = ref(false);
const plans = ref([]);
const plansLoading = ref(false);
const selectedPlanCode = ref('');
const planProrationBehavior = ref('create_prorations');
const planLoading = ref(false);
const planError = ref(null);

// ─── Estado: toast ───────────────────────────────────────────────────────────

const toast = ref(null); // { message, type: 'success' | 'error' }

// ─── Computed ────────────────────────────────────────────────────────────────

const statusLabel = computed(() => {
    const s = subscription.value?.stripe_status;
    if (!s) return '';
    const map = {
        active: t('billing.subscription.active'),
        past_due: t('billing.subscription.past_due'),
        canceled: t('billing.subscription.cancelled'),
        cancelled: t('billing.subscription.cancelled'),
        trialing: t('billing.subscription.trial'),
        trial: t('billing.subscription.trial'),
    };
    return map[s] ?? s;
});

const statusIsAlert = computed(() => {
    const s = subscription.value?.stripe_status;
    return s === 'past_due' || s === 'canceled' || s === 'cancelled';
});

const nextInvoiceAmount = computed(() => {
    if (!subscription.value?.plan) return '';
    const amount = subscription.value.plan.base_price_cents * (subscription.value.professionals_quantity ?? 1);
    return formatCurrency(amount);
});

const nextInvoiceDate = computed(() => {
    const d = subscription.value?.current_period_end;
    return d ? formatDate(d) : '';
});

const currentQty = computed(() =>
    subscription.value?.professionals_quantity ?? subscription.value?.quantity ?? 1
);

const qtyDelta = computed(() => localQty.value - currentQty.value);

const qtyPreviewMessage = computed(() => {
    if (qtyDelta.value === 0) return '';
    if (qtyDelta.value > 0) {
        return t('billing.subscription.preview_increase', {
            old: currentQty.value,
            new: localQty.value,
        });
    }
    return t('billing.subscription.preview_decrease', {
        old: currentQty.value,
        new: localQty.value,
    });
});

// Plano selecionado completo
const selectedPlan = computed(() =>
    plans.value.find((p) => p.code === selectedPlanCode.value) ?? null
);

const planChangeIsUpgrade = computed(() => {
    if (!selectedPlan.value || !subscription.value?.plan) return true;
    return selectedPlan.value.base_price_cents > subscription.value.plan.base_price_cents;
});

const planPreviewMessage = computed(() => {
    if (!selectedPlan.value) return '';
    if (planChangeIsUpgrade.value) {
        return t('billing.subscription.preview_upgrade', { plan: selectedPlan.value.name });
    }
    return t('billing.subscription.preview_downgrade', { plan: selectedPlan.value.name });
});

// Valor de proration_preview retornado pelo backend (defensivo)
const proratedAmountDue = computed(() => {
    const preview = proratedPreview.value;
    if (!preview) return null;
    const cents =
        preview.amount_due_cents ??
        preview.amount_due ??
        preview.amount_remaining ??
        null;
    return cents !== null ? cents : null;
});

// ─── Carregamento ────────────────────────────────────────────────────────────

async function fetchSubscription() {
    loading.value = true;
    error.value = null;
    noSubscription.value = false;

    try {
        const response = await api.get('/billing/subscription');
        subscription.value = response.data.data ?? response.data;
        localQty.value = currentQty.value;
    } catch (err) {
        if (err.response?.status === 404) {
            noSubscription.value = true;
        } else {
            error.value = t('common.error_generic');
        }
    } finally {
        loading.value = false;
    }
}

async function fetchPlans() {
    plansLoading.value = true;
    try {
        const response = await api.get('/billing/plans');
        plans.value = response.data.data ?? response.data;
    } catch {
        // silencioso — modal mostrará lista vazia
    } finally {
        plansLoading.value = false;
    }
}

onMounted(fetchSubscription);

// ─── Stepper de profissionais ─────────────────────────────────────────────────

function decrementQty() {
    if (localQty.value > 1) localQty.value -= 1;
}

function incrementQty() {
    localQty.value += 1;
}

function openProfessionalsModal() {
    professionalsError.value = null;
    proratedPreview.value = null;
    // Default: upgrade = create_prorations, downgrade = none
    professionalsProrationBehavior.value = qtyDelta.value >= 0 ? 'create_prorations' : 'none';
    showProfessionalsModal.value = true;
}

// Busca proration_preview do backend ao abrir modal de upgrade
watch(showProfessionalsModal, async (isOpen) => {
    if (!isOpen) return;
    if (qtyDelta.value <= 0) return; // só em upgrade
    await fetchProratedPreview({
        professionals_quantity: localQty.value,
        proration_behavior: professionalsProrationBehavior.value,
    });
});

async function fetchProratedPreview(body) {
    proratedPreviewLoading.value = true;
    proratedPreview.value = null;
    try {
        const response = await api.patch('/billing/subscription', body);
        const data = response.data.data ?? response.data;
        proratedPreview.value = data.proration_preview ?? null;
    } catch {
        // não bloqueia o modal
    } finally {
        proratedPreviewLoading.value = false;
    }
}

async function confirmProfessionalsChange() {
    professionalsLoading.value = true;
    professionalsError.value = null;

    try {
        await api.patch('/billing/subscription', {
            professionals_quantity: localQty.value,
            proration_behavior: professionalsProrationBehavior.value,
        });
        showProfessionalsModal.value = false;
        await fetchSubscription();
        showToast(
            qtyDelta.value >= 0
                ? t('billing.subscription.upgrade_success')
                : t('billing.subscription.downgrade_scheduled', { date: nextInvoiceDate.value }),
            'success'
        );
    } catch (err) {
        const status = err.response?.status;
        if (status === 502) {
            professionalsError.value = t('billing.subscription.gateway_error');
        } else if (status === 422) {
            const first = Object.values(err.response.data?.errors ?? {})?.[0]?.[0];
            professionalsError.value = first ?? t('common.error_generic');
        } else {
            professionalsError.value = t('common.error_generic');
        }
    } finally {
        professionalsLoading.value = false;
    }
}

// ─── Troca de plano ───────────────────────────────────────────────────────────

async function openPlanModal() {
    planError.value = null;
    selectedPlanCode.value = subscription.value?.plan?.code ?? '';
    planProrationBehavior.value = 'create_prorations';
    showPlanModal.value = true;
    await fetchPlans();
}

// Ajusta default de proration ao selecionar novo plano
watch(selectedPlanCode, () => {
    planProrationBehavior.value = planChangeIsUpgrade.value ? 'create_prorations' : 'none';
});

async function confirmPlanChange() {
    if (!selectedPlanCode.value || selectedPlanCode.value === subscription.value?.plan?.code) {
        showPlanModal.value = false;
        return;
    }

    planLoading.value = true;
    planError.value = null;

    try {
        const response = await api.patch('/billing/subscription', {
            plan_code: selectedPlanCode.value,
            proration_behavior: planProrationBehavior.value,
        });
        const data = response.data.data ?? response.data;
        proratedPreview.value = data.proration_preview ?? null;

        showPlanModal.value = false;
        await fetchSubscription();
        showToast(
            planChangeIsUpgrade.value
                ? t('billing.subscription.upgrade_success')
                : t('billing.subscription.downgrade_scheduled', { date: nextInvoiceDate.value }),
            'success'
        );
    } catch (err) {
        const status = err.response?.status;
        if (status === 502) {
            planError.value = t('billing.subscription.gateway_error');
        } else if (status === 422) {
            const first = Object.values(err.response.data?.errors ?? {})?.[0]?.[0];
            planError.value = first ?? t('common.error_generic');
        } else {
            planError.value = t('common.error_generic');
        }
    } finally {
        planLoading.value = false;
    }
}

// ─── Toast ────────────────────────────────────────────────────────────────────

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => {
        toast.value = null;
    }, 5000);
}
</script>

<template>
    <main class="min-h-screen bg-surface py-12 px-4">
        <div class="mx-auto max-w-2xl">
            <h1 class="text-2xl font-bold text-foreground mb-8">
                {{ t('billing.subscription.title') }}
            </h1>

            <!-- Toast global -->
            <div
                v-if="toast"
                role="alert"
                aria-live="assertive"
                class="mb-4 rounded-lg px-4 py-3 text-sm font-medium"
                :class="{
                    'border border-success-500 bg-success-50 text-success-700': toast.type === 'success',
                    'border border-danger-500 bg-danger-50 text-danger-600': toast.type === 'error',
                }"
            >
                {{ toast.message }}
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

            <!-- Erro genérico -->
            <div
                v-else-if="error"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
            >
                {{ error }}
            </div>

            <!-- Estado trial sem assinatura (404) -->
            <div
                v-else-if="noSubscription"
                class="rounded-xl border border-border bg-surface-elevated p-8 text-center shadow-[var(--shadow-card)]"
            >
                <h2 class="text-lg font-semibold text-foreground mb-2">
                    {{ t('billing.subscription.no_subscription_title') }}
                </h2>
                <p class="text-sm text-foreground-muted mb-6">
                    {{ t('billing.subscription.no_subscription_body') }}
                </p>
                <RouterLink
                    to="/panel/billing/plans"
                    class="inline-block rounded-lg bg-primary-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                >
                    {{ t('billing.subscription.see_plans') }}
                </RouterLink>
            </div>

            <!-- Assinatura ativa -->
            <div v-else-if="subscription" class="space-y-4">
                <!-- Alerta de status negativo -->
                <div
                    v-if="statusIsAlert"
                    role="alert"
                    aria-live="assertive"
                    class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600 font-medium"
                >
                    {{ t('tenant.overdue.banner', { since: nextInvoiceDate }) }}
                </div>

                <!-- Card principal: Plano -->
                <div class="rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)]">
                    <div class="p-6 border-b border-border flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                                {{ t('billing.subscription.current_plan') }}
                            </p>
                            <p class="text-xl font-bold text-foreground">
                                {{ subscription.plan?.name ?? '—' }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="{
                                'bg-success-100 text-success-700': subscription.stripe_status === 'active',
                                'bg-warning-100 text-warning-700': subscription.stripe_status === 'trialing' || subscription.stripe_status === 'trial',
                                'bg-danger-100 text-danger-700': statusIsAlert,
                            }"
                        >
                            {{ statusLabel }}
                        </span>
                    </div>

                    <dl class="p-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Próxima fatura -->
                        <div>
                            <dt class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                                {{ t('billing.subscription.next_invoice') }}
                            </dt>
                            <dd class="text-sm font-medium text-foreground">
                                {{
                                    t('billing.subscription.next_invoice_due', {
                                        amount: nextInvoiceAmount,
                                        date: nextInvoiceDate,
                                    })
                                }}
                            </dd>
                        </div>

                        <!-- Profissionais ativos + stepper -->
                        <div>
                            <dt class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                                {{ t('billing.subscription.professionals_active') }}
                            </dt>
                            <dd class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="flex h-7 w-7 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="localQty <= 1"
                                    :aria-label="t('billing.subscription.modify_professionals') + ' -1'"
                                    @click="decrementQty"
                                >
                                    &minus;
                                </button>
                                <span class="text-sm font-semibold text-foreground min-w-[2rem] text-center">
                                    {{ localQty }}
                                </span>
                                <button
                                    type="button"
                                    class="flex h-7 w-7 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    :aria-label="t('billing.subscription.modify_professionals') + ' +1'"
                                    @click="incrementQty"
                                >
                                    +
                                </button>
                            </dd>
                        </div>
                    </dl>

                    <!-- Preview de alteração de profissionais -->
                    <div
                        v-if="qtyPreviewMessage"
                        class="mx-6 mb-4 rounded-lg border border-primary-200 bg-primary-50 px-4 py-2 text-sm text-primary-700"
                        aria-live="polite"
                    >
                        {{ qtyPreviewMessage }}
                    </div>

                    <!-- Ações -->
                    <div class="px-6 pb-6 flex flex-wrap gap-3">
                        <!-- Confirmar alteração de profissionais (só aparece se qty mudou) -->
                        <button
                            v-if="qtyDelta !== 0"
                            type="button"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="openProfessionalsModal"
                        >
                            {{ t('billing.subscription.confirm_change') }}
                        </button>

                        <!-- Trocar plano -->
                        <button
                            type="button"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="openPlanModal"
                        >
                            {{ t('billing.subscription.modify_plan') }}
                        </button>

                        <!-- Cancelamento placeholder (US4 — Lote L) -->
                        <button
                            type="button"
                            disabled
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground-muted cursor-not-allowed opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            :title="t('billing.subscription.cancel')"
                            aria-disabled="true"
                        >
                            {{ t('billing.subscription.cancel') }}
                            <span class="ml-1 text-xs">{{ t('tenant.onboarding.locked_tooltip') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modal: alterar profissionais ─────────────────────────────────── -->
        <ConfirmModal
            :open="showProfessionalsModal"
            :title="t('billing.subscription.modify_professionals')"
            @close="showProfessionalsModal = false"
            @confirm="confirmProfessionalsChange"
        >
            <!-- Resumo da alteração -->
            <p class="mb-3 text-foreground">{{ qtyPreviewMessage }}</p>

            <!-- Mensagem de proration contextual -->
            <div v-if="qtyDelta > 0">
                <p class="text-sm text-foreground-muted mb-2">
                    {{ t('billing.subscription.proration_immediate') }}
                </p>

                <!-- Preview de valor proration -->
                <div v-if="proratedPreviewLoading" class="text-sm text-foreground-muted italic">
                    {{ t('common.loading') }}
                </div>
                <p
                    v-else-if="proratedAmountDue !== null"
                    class="text-sm font-medium text-primary-700"
                >
                    {{
                        t('billing.subscription.proration_amount_due', {
                            amount: formatCurrency(proratedAmountDue),
                        })
                    }}
                </p>
            </div>
            <div v-else>
                <p class="text-sm text-foreground-muted">
                    {{
                        t('billing.subscription.proration_next_cycle', {
                            date: nextInvoiceDate,
                        })
                    }}
                </p>
            </div>

            <!-- Toggle proration_behavior -->
            <label class="mt-4 flex items-center gap-2 cursor-pointer select-none">
                <input
                    v-model="professionalsProrationBehavior"
                    type="checkbox"
                    true-value="create_prorations"
                    false-value="none"
                    class="h-4 w-4 rounded border-border text-primary-700 focus:ring-primary-500"
                    :aria-label="t('billing.subscription.toggle_immediate_proration')"
                />
                <span class="text-sm text-foreground">
                    {{ t('billing.subscription.toggle_immediate_proration') }}
                </span>
            </label>

            <!-- Erro -->
            <div
                v-if="professionalsError"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
            >
                {{ professionalsError }}
            </div>

            <template #actions>
                <button
                    type="button"
                    :disabled="professionalsLoading"
                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmProfessionalsChange"
                >
                    {{ professionalsLoading ? t('common.loading') : t('billing.subscription.confirm_change') }}
                </button>
            </template>
        </ConfirmModal>

        <!-- ── Modal: trocar plano ────────────────────────────────────────────── -->
        <ConfirmModal
            :open="showPlanModal"
            :title="t('billing.subscription.modify_plan')"
            @close="showPlanModal = false"
            @confirm="confirmPlanChange"
        >
            <!-- Loading de planos -->
            <div v-if="plansLoading" class="text-sm text-foreground-muted italic mb-3">
                {{ t('common.loading') }}
            </div>

            <!-- Dropdown de planos -->
            <div v-else>
                <label
                    for="plan-select"
                    class="mb-1.5 block text-sm font-medium text-foreground"
                >
                    {{ t('billing.subscription.modify_plan') }}
                </label>
                <select
                    id="plan-select"
                    v-model="selectedPlanCode"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200 mb-3"
                >
                    <option value="" disabled>— selecione —</option>
                    <option
                        v-for="plan in plans"
                        :key="plan.id"
                        :value="plan.code"
                    >
                        {{ plan.name }} — {{ formatCurrency(plan.base_price_cents) }}/mês
                    </option>
                </select>

                <!-- Descrição e benefícios do plano selecionado -->
                <div
                    v-if="selectedPlan"
                    class="mb-3 rounded-lg border border-border bg-surface p-3 text-sm text-foreground-muted space-y-1"
                >
                    <p v-if="selectedPlan.description">{{ selectedPlan.description }}</p>
                    <p>{{ t('billing.plan.professionals_included', { count: selectedPlan.included_professionals }) }}</p>
                    <p>{{ t('billing.plan.users_max', { count: selectedPlan.max_users }) }}</p>
                    <p>{{ t('billing.plan.ai_credits_monthly', { count: selectedPlan.included_ai_messages }) }}</p>
                </div>

                <!-- Preview da direção da mudança -->
                <p
                    v-if="planPreviewMessage"
                    class="mb-3 text-sm font-medium"
                    :class="planChangeIsUpgrade ? 'text-success-700' : 'text-warning-700'"
                    aria-live="polite"
                >
                    {{ planPreviewMessage }}
                </p>

                <!-- Toggle proration_behavior -->
                <label class="flex items-center gap-2 cursor-pointer select-none mb-3">
                    <input
                        v-model="planProrationBehavior"
                        type="checkbox"
                        true-value="create_prorations"
                        false-value="none"
                        class="h-4 w-4 rounded border-border text-primary-700 focus:ring-primary-500"
                        :aria-label="t('billing.subscription.toggle_immediate_proration')"
                    />
                    <span class="text-sm text-foreground">
                        {{ t('billing.subscription.toggle_immediate_proration') }}
                    </span>
                </label>
            </div>

            <!-- Erro -->
            <div
                v-if="planError"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
            >
                {{ planError }}
            </div>

            <template #actions>
                <button
                    type="button"
                    :disabled="planLoading || !selectedPlanCode"
                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmPlanChange"
                >
                    {{ planLoading ? t('common.loading') : t('billing.subscription.confirm_change') }}
                </button>
            </template>
        </ConfirmModal>
    </main>
</template>
