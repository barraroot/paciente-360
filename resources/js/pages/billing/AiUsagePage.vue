<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';
import { useAuthStore } from '@/stores/auth.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import api from '@/lib/api.js';

const { t } = useI18n();
const { formatCurrency, formatDate, formatNumber } = useI18nFormat();
const auth = useAuthStore();

// ─── Estado principal ────────────────────────────────────────────────────────

const usage = ref(null);
const loading = ref(false);
const error = ref(null);

// ─── Estado: hard cap edit ───────────────────────────────────────────────────

const isEditing = ref(false);
const hardCapInput = ref('');
const removeCap = ref(false);
const capLoading = ref(false);
const capError = ref(null);
const showZeroConfirm = ref(false);

// ─── Estado: toast ───────────────────────────────────────────────────────────

const toast = ref(null); // { message, type: 'success' | 'error' }

// ─── Permissão de edição ─────────────────────────────────────────────────────

const canEditCap = computed(
    () => auth.hasRole('admin-clinica') || auth.hasRole('financeiro'),
);

// ─── Computed de métricas ────────────────────────────────────────────────────

const progressPercent = computed(() => {
    if (!usage.value) return 0;
    const { consumed, included_quota } = usage.value;
    if (!included_quota || included_quota <= 0) return 0;
    return Math.min(100, Math.round((consumed / included_quota) * 100));
});

const overageUnitPriceCents = computed(() => {
    if (!usage.value) return 0;
    const { overage, overage_cost_estimate_cents } = usage.value;
    if (!overage || overage <= 0) return 0;
    return overage_cost_estimate_cents / overage;
});

const hardCapDisplay = computed(() => {
    if (!usage.value) return '';
    const cap = usage.value.hard_cap;
    if (cap === null || cap === undefined) return t('billing.ai_usage.hard_cap_none');
    return formatNumber(cap);
});

// ─── Carregamento ────────────────────────────────────────────────────────────

async function fetchUsage() {
    loading.value = true;
    error.value = null;
    try {
        const response = await api.get('/billing/ai-usage');
        usage.value = response.data.data ?? response.data;
    } catch {
        error.value = t('common.error_generic');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchUsage);

// ─── Hard cap: edição ────────────────────────────────────────────────────────

function openEdit() {
    const cap = usage.value?.hard_cap;
    hardCapInput.value = cap !== null && cap !== undefined ? String(cap) : '';
    removeCap.value = false;
    capError.value = null;
    isEditing.value = true;
}

function cancelEdit() {
    isEditing.value = false;
    capError.value = null;
}

function requestSave() {
    capError.value = null;

    if (removeCap.value) {
        submitHardCap(null);
        return;
    }

    const val = parseInt(hardCapInput.value, 10);

    if (!Number.isInteger(val) || val < 0) {
        capError.value = t('common.error_generic');
        return;
    }

    if (val === 0) {
        showZeroConfirm.value = true;
        return;
    }

    submitHardCap(val);
}

async function submitHardCap(value) {
    showZeroConfirm.value = false;
    capLoading.value = true;
    capError.value = null;

    try {
        const response = await api.patch('/billing/ai-usage/hard-cap', { hard_cap: value });
        usage.value = response.data.data ?? response.data;
        isEditing.value = false;
        showToast(t('billing.ai_usage.hard_cap_set_success'), 'success');
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            const first = Object.values(err.response.data?.errors ?? {})?.[0]?.[0];
            capError.value = first ?? t('common.error_generic');
        } else {
            capError.value = t('common.error_generic');
        }
        showToast(capError.value, 'error');
    } finally {
        capLoading.value = false;
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
        <div class="mx-auto max-w-4xl">

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

            <!-- Título + período -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-foreground">
                    {{ t('billing.ai_usage.title') }}
                </h1>
                <p
                    v-if="usage"
                    class="mt-1 text-sm text-foreground-muted"
                >
                    {{
                        t('billing.ai_usage.cycle_label', {
                            start: formatDate(usage.cycle_start),
                            end: formatDate(usage.cycle_end),
                        })
                    }}
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

            <!-- Erro genérico -->
            <div
                v-else-if="error"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
            >
                {{ error }}
            </div>

            <template v-else-if="usage">

                <!-- Alertas condicionais -->
                <div class="space-y-3 mb-6">
                    <!-- Hard cap atingido -->
                    <div
                        v-if="usage.hard_cap_triggered_at != null"
                        role="alert"
                        aria-live="assertive"
                        class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700"
                    >
                        {{ t('billing.ai_usage.hard_cap_reached') }}
                    </div>

                    <!-- Overage -->
                    <div
                        v-if="usage.overage > 0"
                        role="alert"
                        aria-live="polite"
                        class="rounded-lg border border-warning-400 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700"
                    >
                        {{
                            t('billing.ai_usage.overage_warning', {
                                price: formatCurrency(overageUnitPriceCents),
                            })
                        }}
                    </div>
                </div>

                <!-- Grid de métricas (4 colunas, responsivo) -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">

                    <!-- 1. Cota inclusa -->
                    <div class="rounded-xl border border-border bg-surface-elevated p-5 shadow-[var(--shadow-card)]">
                        <p class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                            {{ t('billing.ai_usage.included_quota_label') }}
                        </p>
                        <p class="text-2xl font-bold text-foreground">
                            {{ formatNumber(usage.included_quota) }}
                        </p>
                        <p class="mt-1 text-xs text-foreground-muted">
                            {{ t('billing.ai_usage.meter_label') }}
                        </p>
                    </div>

                    <!-- 2. Consumido (com progress bar) -->
                    <div class="rounded-xl border border-border bg-surface-elevated p-5 shadow-[var(--shadow-card)]">
                        <p class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                            {{ t('billing.ai_usage.consumed_label') }}
                        </p>
                        <p class="text-2xl font-bold text-foreground">
                            {{ formatNumber(usage.consumed) }}
                        </p>
                        <div class="mt-2">
                            <div
                                class="h-2 w-full rounded-full bg-surface"
                                role="progressbar"
                                :aria-valuenow="progressPercent"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-label="t('billing.ai_usage.consumed_label')"
                            >
                                <div
                                    class="h-2 rounded-full transition-all"
                                    :class="progressPercent >= 100 ? 'bg-danger-500' : progressPercent >= 80 ? 'bg-warning-500' : 'bg-primary-600'"
                                    :style="{ width: progressPercent + '%' }"
                                />
                            </div>
                            <p class="mt-1 text-xs text-foreground-muted text-right">
                                {{ progressPercent }}%
                            </p>
                        </div>
                    </div>

                    <!-- 3. Projeção fim de mês -->
                    <div class="rounded-xl border border-border bg-surface-elevated p-5 shadow-[var(--shadow-card)]">
                        <p class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                            {{ t('billing.ai_usage.projected_label') }}
                        </p>
                        <p class="text-2xl font-bold text-foreground">
                            {{ formatNumber(usage.projected_end_of_month) }}
                        </p>
                    </div>

                    <!-- 4. Custo estimado de excedente -->
                    <div class="rounded-xl border border-border bg-surface-elevated p-5 shadow-[var(--shadow-card)]">
                        <p class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                            {{ t('billing.ai_usage.overage_cost_label') }}
                        </p>
                        <p
                            class="text-2xl font-bold"
                            :class="usage.overage_cost_estimate_cents > 0 ? 'text-warning-700' : 'text-foreground'"
                        >
                            {{ formatCurrency(usage.overage_cost_estimate_cents) }}
                        </p>
                    </div>
                </div>

                <!-- Card: Limite máximo (hard cap) -->
                <div class="rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)]">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-base font-semibold text-foreground">
                            {{ t('billing.ai_usage.hard_cap_label') }}
                        </h2>
                    </div>

                    <div class="p-6">
                        <!-- Estado: visualização -->
                        <div v-if="!isEditing" class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex-1">
                                <p class="text-xs font-medium text-foreground-muted uppercase tracking-wide mb-1">
                                    {{ t('billing.ai_usage.hard_cap_label') }}
                                </p>
                                <p class="text-xl font-bold text-foreground">
                                    {{ hardCapDisplay }}
                                </p>
                            </div>
                            <button
                                v-if="canEditCap"
                                type="button"
                                class="self-start sm:self-center rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openEdit"
                            >
                                {{ t('billing.ai_usage.set_hard_cap') }}
                            </button>
                        </div>

                        <!-- Estado: edição -->
                        <div v-else>
                            <!-- Toggle: remover limite -->
                            <label class="mb-4 flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    v-model="removeCap"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-border text-primary-700 focus:ring-primary-500"
                                    :aria-label="t('billing.ai_usage.hard_cap_remove')"
                                />
                                <span class="text-sm text-foreground">
                                    {{ t('billing.ai_usage.hard_cap_remove') }}
                                </span>
                            </label>

                            <!-- Input de valor numérico (desabilitado se removeCap) -->
                            <div class="mb-4" :class="removeCap ? 'opacity-40 pointer-events-none' : ''">
                                <label
                                    for="hard-cap-input"
                                    class="mb-1.5 block text-sm font-medium text-foreground"
                                >
                                    {{ t('billing.ai_usage.hard_cap_label') }}
                                </label>
                                <input
                                    id="hard-cap-input"
                                    v-model="hardCapInput"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :disabled="removeCap"
                                    class="w-full max-w-xs rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200 disabled:cursor-not-allowed disabled:opacity-50"
                                    :aria-label="t('billing.ai_usage.hard_cap_label')"
                                />
                            </div>

                            <!-- Erro -->
                            <div
                                v-if="capError"
                                role="alert"
                                aria-live="assertive"
                                class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
                            >
                                {{ capError }}
                            </div>

                            <!-- Ações -->
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    :disabled="capLoading"
                                    class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="requestSave"
                                >
                                    {{ capLoading ? t('common.loading') : t('billing.ai_usage.hard_cap_save') }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="capLoading"
                                    class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="cancelEdit"
                                >
                                    {{ t('common.cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Modal de confirmação: hard_cap = 0 desliga IA imediatamente -->
        <ConfirmModal
            :open="showZeroConfirm"
            :title="t('billing.ai_usage.hard_cap_label')"
            @close="showZeroConfirm = false"
            @confirm="submitHardCap(0)"
        >
            <p>{{ t('billing.ai_usage.hard_cap_zero_warning') }}</p>
        </ConfirmModal>
    </main>
</template>
