<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProfessionalsStore } from '@/stores/professionalsStore.js';
import ProfessionalFormModal from '@/components/Professionals/ProfessionalFormModal.vue';
import DeactivateConfirmModal from '@/components/Professionals/DeactivateConfirmModal.vue';

const { t } = useI18n();
const store = useProfessionalsStore();

const formOpen = ref(false);
const editingProfessional = ref(null);
const deactivateTarget = ref(null);
const deactivating = ref(false);
const toast = ref(null);

const items = computed(() => store.list);
const loading = computed(() => store.loading);
const forbidden = computed(() => store.forbidden);
const isEmpty = computed(
    () => ! loading.value && ! forbidden.value && items.value.length === 0,
);

let searchTimer = null;
function onSearchInput(value) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        store.setFilter('search', value);
    }, 300);
}

function openCreate() {
    editingProfessional.value = null;
    formOpen.value = true;
}

function openEdit(prof) {
    editingProfessional.value = prof;
    formOpen.value = true;
}

function onDeactivate(prof) {
    deactivateTarget.value = prof;
}

function cancelDeactivate() {
    deactivateTarget.value = null;
}

async function confirmDeactivate() {
    const prof = deactivateTarget.value;
    if (! prof) {
        return;
    }
    deactivating.value = true;
    try {
        await store.deactivate(prof.id);
        showToast(t('professionals.success.deactivated'));
        deactivateTarget.value = null;
    } catch {
        showToast(t('professionals.errors.deactivate_failed'), 'error');
    } finally {
        deactivating.value = false;
    }
}

async function onReactivate(prof) {
    try {
        await store.activate(prof.id);
        showToast(t('professionals.success.reactivated'));
    } catch {
        showToast(t('professionals.errors.deactivate_failed'), 'error');
    }
}

function onSaved() {
    showToast(editingProfessional.value ? t('professionals.success.updated') : t('professionals.success.created'));
}

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => { toast.value = null; }, 4000);
}

onMounted(() => {
    store.fetchList();
});
</script>

<template>
    <div class="p-4 sm:p-6 max-w-6xl mx-auto">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">{{ t('professionals.page_title') }}</h1>
                <p class="mt-0.5 text-sm text-foreground-muted">{{ t('professionals.page_subtitle') }}</p>
            </div>
            <button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-700 text-white text-sm font-semibold hover:bg-primary-800" @click="openCreate">
                + {{ t('professionals.toolbar.new') }}
            </button>
        </header>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <select
                :value="store.filters.is_active"
                @change="store.setFilter('is_active', $event.target.value)"
                :aria-label="t('professionals.toolbar.filter_label')"
                class="rounded-lg border border-border bg-surface px-3 py-2 text-sm"
            >
                <option value="true">{{ t('professionals.toolbar.filter_active') }}</option>
                <option value="false">{{ t('professionals.toolbar.filter_inactive') }}</option>
                <option value="all">{{ t('professionals.toolbar.filter_all') }}</option>
            </select>
            <input
                type="search"
                :placeholder="t('professionals.toolbar.search_placeholder')"
                @input="onSearchInput($event.target.value)"
                class="flex-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
            />
        </div>

        <!-- Loading skeleton -->
        <ul v-if="loading && items.length === 0" role="list" aria-hidden="true" class="space-y-2">
            <li v-for="n in 4" :key="n" class="h-14 rounded-lg bg-surface-muted animate-pulse"></li>
        </ul>

        <!-- Acesso negado (403) -->
        <div v-else-if="forbidden" role="alert" class="text-center py-12 border border-dashed border-danger-300 rounded-xl bg-danger-50">
            <p class="text-base font-semibold text-danger-700">{{ t('professionals.forbidden.title') }}</p>
            <p class="mt-1 text-sm text-danger-600">{{ t('professionals.forbidden.body') }}</p>
        </div>

        <!-- Empty state -->
        <div v-else-if="isEmpty" class="text-center py-12 border border-dashed border-border rounded-xl">
            <p class="text-base font-semibold text-foreground">{{ t('professionals.empty_state.title') }}</p>
            <p class="mt-1 text-sm text-foreground-muted">{{ t('professionals.empty_state.body') }}</p>
            <button type="button" class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-700 text-white text-sm font-semibold hover:bg-primary-800" @click="openCreate">
                + {{ t('professionals.empty_state.cta') }}
            </button>
        </div>

        <!-- Tabela -->
        <table v-else class="w-full border border-border rounded-xl overflow-hidden bg-surface-elevated">
            <thead class="bg-surface-muted">
                <tr class="text-left text-xs uppercase tracking-wide text-foreground-muted">
                    <th class="px-4 py-3">{{ t('professionals.table.name') }}</th>
                    <th class="px-4 py-3">{{ t('professionals.table.council') }}</th>
                    <th class="px-4 py-3">{{ t('professionals.table.especialidade') }}</th>
                    <th class="px-4 py-3">{{ t('professionals.table.status') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('professionals.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="prof in items" :key="prof.id" class="border-t border-border">
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-foreground">{{ prof.name }}</p>
                        <p v-if="prof.user" class="text-xs text-foreground-muted">{{ prof.user.name }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-foreground">
                        {{ prof.council_type === 'OUTRO' ? prof.council_type_other : prof.council_type }}
                        {{ prof.council_number }}/{{ prof.council_state }}
                    </td>
                    <td class="px-4 py-3 text-sm text-foreground-muted">{{ prof.especialidade ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                            :class="prof.is_active ? 'bg-success-50 text-success-700' : 'bg-surface-muted text-foreground-muted'"
                        >
                            <span aria-hidden="true">{{ prof.is_active ? '●' : '○' }}</span>
                            {{ prof.is_active ? t('professionals.table.status_active') : t('professionals.table.status_inactive') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-sm text-primary-700 hover:underline mr-3" @click="openEdit(prof)">{{ t('professionals.actions.edit') }}</button>
                        <button
                            v-if="prof.is_active"
                            class="text-sm text-danger-600 hover:underline"
                            @click="onDeactivate(prof)"
                        >{{ t('professionals.actions.deactivate') }}</button>
                        <button
                            v-else
                            class="text-sm text-primary-700 hover:underline"
                            @click="onReactivate(prof)"
                        >{{ t('professionals.actions.reactivate') }}</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Toast -->
        <div
            v-if="toast"
            role="status"
            aria-live="polite"
            class="fixed right-4 top-20 z-50 rounded-lg px-4 py-3 text-sm font-medium shadow-popover"
            :class="toast.type === 'error' ? 'border border-danger-500 bg-danger-50 text-danger-700' : 'border border-success-500 bg-success-50 text-success-700'"
        >
            {{ toast.msg }}
        </div>

        <!-- Form modal -->
        <ProfessionalFormModal
            :open="formOpen"
            :professional="editingProfessional"
            @update:open="formOpen = $event"
            @saved="onSaved"
        />

        <!-- Deactivate confirm modal (a11y — substitui window.confirm) -->
        <DeactivateConfirmModal
            :open="deactivateTarget !== null"
            :professional="deactivateTarget"
            :loading="deactivating"
            @confirm="confirmDeactivate"
            @cancel="cancelDeactivate"
        />
    </div>
</template>
