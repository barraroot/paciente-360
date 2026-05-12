<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    /** Objeto de filtros reativos (v-model:filters) */
    filters: {
        type: Object,
        required: true,
    },
    /**
     * Aggregations vindas do backend para mostrar contadores nos chips.
     * @type {{ by_status: Record<string, number>, unassigned: number, mine: number }}
     */
    aggregations: {
        type: Object,
        default: () => ({ by_status: {}, unassigned: 0, mine: 0 }),
    },
    /** Número total de filtros ativos */
    filtersActive: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits([
    'update:filters',
    'clearFilters',
    'toggleFilter',
    'applyFilter',
]);

// ─── Status ───────────────────────────────────────────────────────────────────

const STATUS_OPTIONS = ['aberta', 'pendente', 'resolvida', 'reaberta'];

function isStatusActive(val) {
    return props.filters.status?.includes(val) ?? false;
}

function toggleStatus(val) {
    emit('toggleFilter', 'status', val);
}

function statusCount(val) {
    return props.aggregations.by_status?.[val] ?? null;
}

const statusChipClass = (val) => {
    const active = isStatusActive(val);
    const baseMap = {
        aberta: active ? 'bg-primary-100 text-primary-700 border-primary-300' : 'bg-surface text-foreground-muted border-border hover:bg-surface-elevated',
        pendente: active ? 'bg-warning-100 text-warning-700 border-warning-300' : 'bg-surface text-foreground-muted border-border hover:bg-surface-elevated',
        resolvida: active ? 'bg-surface text-foreground border-foreground-subtle' : 'bg-surface text-foreground-muted border-border hover:bg-surface-elevated',
        reaberta: active ? 'bg-primary-50 text-primary-600 border-primary-200' : 'bg-surface text-foreground-muted border-border hover:bg-surface-elevated',
    };
    return baseMap[val] ?? (active ? 'bg-primary-100 text-primary-700 border-primary-300' : 'bg-surface text-foreground-muted border-border hover:bg-surface-elevated');
};

// ─── Canal ───────────────────────────────────────────────────────────────────

const CHANNEL_OPTIONS = ['whatsapp', 'instagram', 'web'];

function isChannelActive(val) {
    return props.filters.channel_type?.includes(val) ?? false;
}

function toggleChannel(val) {
    emit('toggleFilter', 'channel_type', val);
}

const channelChipClass = (val) => {
    const active = isChannelActive(val);
    return active
        ? 'bg-primary-100 text-primary-700 border-primary-300'
        : 'bg-surface text-foreground-muted border-border hover:bg-surface-elevated';
};

// ─── Atendente ────────────────────────────────────────────────────────────────

const ASSIGNED_OPTIONS = [
    { value: null, label: 'filtros.todos' },
    { value: '__unassigned__', label: 'filtros.sem_atendente' },
    { value: '__me__', label: 'filtros.eu_mesmo' },
];

function setAssignedUser(val) {
    emit('applyFilter', 'assigned_user_id', val === null ? null : val);
}

const assignedUserValue = computed(() => props.filters.assigned_user_id ?? null);

// ─── Mídia ────────────────────────────────────────────────────────────────────

function setHasMedia(val) {
    // val: null | true | false
    emit('applyFilter', 'has_media', val);
}

// ─── Idade da conversa ────────────────────────────────────────────────────────

const AGE_OPTIONS = [
    { value: null, label: 'filtros.todos' },
    { value: '<1h', label: 'filtros.menor_1h' },
    { value: '1-24h', label: 'filtros.1_a_24h' },
    { value: '1-7d', label: 'filtros.1_a_7d' },
    { value: '>7d', label: 'filtros.maior_7d' },
];

function setAge(val) {
    emit('applyFilter', 'age', val);
}
</script>

<template>
    <aside
        class="flex h-full w-60 flex-col border-r border-border bg-surface-elevated overflow-y-auto"
        aria-label="Filtros da inbox"
    >
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
            <h2 class="text-sm font-semibold text-foreground">
                Filtros
                <span
                    v-if="filtersActive > 0"
                    class="ml-1.5 inline-flex items-center rounded-full bg-primary-700 px-1.5 py-0.5 text-[10px] font-bold text-white"
                    aria-label="`${filtersActive} filtros ativos`"
                >
                    {{ filtersActive }}
                </span>
            </h2>
            <button
                v-if="filtersActive > 0"
                type="button"
                class="text-xs text-primary-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="emit('clearFilters')"
            >
                {{ t('inbox.filtros.limpar') }}
            </button>
        </div>

        <div class="flex flex-col gap-5 px-4 py-4 overflow-y-auto flex-1">
            <!-- ── Status ──────────────────────────────────────────────────── -->
            <fieldset>
                <legend class="mb-2 text-xs font-semibold text-foreground-muted uppercase tracking-wide">
                    {{ t('inbox.filtros.status') }}
                </legend>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="s in STATUS_OPTIONS"
                        :key="s"
                        type="button"
                        :aria-pressed="isStatusActive(s)"
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="statusChipClass(s)"
                        @click="toggleStatus(s)"
                    >
                        {{ t(`inbox.status.${s}`) }}
                        <span
                            v-if="statusCount(s) !== null"
                            class="opacity-70"
                        >({{ statusCount(s) }})</span>
                    </button>
                </div>
            </fieldset>

            <!-- ── Canal ──────────────────────────────────────────────────── -->
            <fieldset>
                <legend class="mb-2 text-xs font-semibold text-foreground-muted uppercase tracking-wide">
                    {{ t('inbox.filtros.canal') }}
                </legend>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="ch in CHANNEL_OPTIONS"
                        :key="ch"
                        type="button"
                        :aria-pressed="isChannelActive(ch)"
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="channelChipClass(ch)"
                        @click="toggleChannel(ch)"
                    >
                        {{ t(`inbox.canal.${ch}`) }}
                    </button>
                </div>
            </fieldset>

            <!-- ── Atendente ──────────────────────────────────────────────── -->
            <div>
                <label
                    for="filter-atendente"
                    class="mb-2 block text-xs font-semibold text-foreground-muted uppercase tracking-wide"
                >
                    {{ t('inbox.filtros.atendente') }}
                </label>
                <select
                    id="filter-atendente"
                    :value="assignedUserValue"
                    class="w-full rounded-lg border border-border bg-surface px-2.5 py-2 text-sm text-foreground focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
                    @change="setAssignedUser($event.target.value === '' ? null : $event.target.value)"
                >
                    <option
                        v-for="opt in ASSIGNED_OPTIONS"
                        :key="String(opt.value)"
                        :value="opt.value ?? ''"
                    >
                        {{ t(`inbox.${opt.label}`) }}
                    </option>
                </select>
            </div>

            <!-- ── Profissional vinculado ─────────────────────────────────── -->
            <div>
                <label
                    for="filter-profissional"
                    class="mb-2 block text-xs font-semibold text-foreground-muted uppercase tracking-wide"
                >
                    {{ t('inbox.filtros.profissional') }}
                </label>
                <select
                    id="filter-profissional"
                    :value="filters.patient_id ?? ''"
                    class="w-full rounded-lg border border-border bg-surface px-2.5 py-2 text-sm text-foreground focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
                    @change="emit('applyFilter', 'patient_id', $event.target.value || null)"
                >
                    <option value="">{{ t('inbox.filtros.todos') }}</option>
                    <!-- T159/Lote I preencherá com lista real de profissionais -->
                </select>
            </div>

            <!-- ── Tag do paciente ─────────────────────────────────────────── -->
            <div>
                <p class="mb-2 text-xs font-semibold text-foreground-muted uppercase tracking-wide">
                    {{ t('inbox.filtros.tag') }}
                </p>
                <!-- Placeholder: tags virão do endpoint da Fase 2 -->
                <p class="text-xs text-foreground-subtle italic">
                    Nenhuma tag disponível.
                </p>
            </div>

            <!-- ── Mídia ──────────────────────────────────────────────────── -->
            <fieldset>
                <legend class="mb-2 text-xs font-semibold text-foreground-muted uppercase tracking-wide">
                    {{ t('inbox.filtros.midia') }}
                </legend>
                <div class="flex flex-col gap-1.5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="has-media"
                            :checked="filters.has_media === null"
                            class="accent-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @change="setHasMedia(null)"
                        />
                        <span class="text-sm text-foreground">{{ t('inbox.filtros.todos') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="has-media"
                            :checked="filters.has_media === true"
                            class="accent-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @change="setHasMedia(true)"
                        />
                        <span class="text-sm text-foreground">{{ t('inbox.filtros.com_midia') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="has-media"
                            :checked="filters.has_media === false"
                            class="accent-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @change="setHasMedia(false)"
                        />
                        <span class="text-sm text-foreground">{{ t('inbox.filtros.sem_midia') }}</span>
                    </label>
                </div>
            </fieldset>

            <!-- ── Idade da conversa ───────────────────────────────────────── -->
            <fieldset>
                <legend class="mb-2 text-xs font-semibold text-foreground-muted uppercase tracking-wide">
                    {{ t('inbox.filtros.idade') }}
                </legend>
                <div class="flex flex-col gap-1.5">
                    <label
                        v-for="opt in AGE_OPTIONS"
                        :key="String(opt.value)"
                        class="flex items-center gap-2 cursor-pointer"
                    >
                        <input
                            type="radio"
                            name="age"
                            :checked="(filters.age ?? null) === opt.value"
                            class="accent-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @change="setAge(opt.value)"
                        />
                        <span class="text-sm text-foreground">{{ t(`inbox.${opt.label}`) }}</span>
                    </label>
                </div>
            </fieldset>
        </div>
    </aside>
</template>
