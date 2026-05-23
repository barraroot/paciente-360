<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import HeroIcon from './icons/HeroIcon.vue';
import SidebarItem from './SidebarItem.vue';

const props = defineProps({
    group: { type: Object, required: true },
    isExpanded: { type: Boolean, default: false },
    forceExpand: { type: Boolean, default: false }, // grupo da rota corrente sempre aberto
    mode: { type: String, default: 'expanded' },
    currentItemKey: { type: String, default: null },
});

const emit = defineEmits(['toggle', 'navigate']);

const { t } = useI18n();

const label = computed(() => t(props.group.labelKey));
const compact = computed(() => props.mode === 'compact');
const effectivelyExpanded = computed(() => props.forceExpand || props.isExpanded);

// Popover state (modo compacto): controlado por hover/focus do trigger
const popoverOpen = ref(false);
let closeTimer = null;

function openPopover() {
    if (closeTimer) {
        clearTimeout(closeTimer);
        closeTimer = null;
    }
    popoverOpen.value = true;
}

function schedulePopoverClose() {
    closeTimer = setTimeout(() => {
        popoverOpen.value = false;
    }, 120);
}

watch(compact, (now) => {
    if (! now) {
        popoverOpen.value = false;
    }
});

function onToggle() {
    if (compact.value) {
        return;
    }
    emit('toggle', props.group.key);
}
</script>

<template>
    <div
        class="relative"
        @mouseenter="compact && openPopover()"
        @mouseleave="compact && schedulePopoverClose()"
    >
        <!-- Trigger do grupo -->
        <button
            type="button"
            class="w-full group flex items-center gap-3 rounded-md text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            :class="[
                compact ? 'justify-center px-2 py-2' : 'px-3 py-2 justify-between',
                'text-foreground-muted hover:bg-surface-muted hover:text-foreground',
            ]"
            :aria-expanded="effectivelyExpanded"
            :aria-controls="`sidebar-group-${group.key}`"
            :title="compact ? label : undefined"
            :aria-label="compact ? label : undefined"
            @click="onToggle"
            @focus="compact && openPopover()"
            @blur="compact && schedulePopoverClose()"
        >
            <div class="flex items-center gap-3 min-w-0">
                <HeroIcon
                    v-if="group.icon"
                    :name="group.icon"
                    class="shrink-0 w-5 h-5"
                />
                <span v-if="!compact" class="truncate text-left">{{ label }}</span>
            </div>
            <HeroIcon
                v-if="!compact"
                name="chevron-down"
                class="shrink-0 w-4 h-4 transition-transform"
                :class="effectivelyExpanded ? 'rotate-0' : '-rotate-90'"
            />
        </button>

        <!-- Sub-items expandidos (modo expanded) -->
        <ul
            v-if="!compact && effectivelyExpanded"
            :id="`sidebar-group-${group.key}`"
            role="list"
            class="mt-1 space-y-0.5"
        >
            <li v-for="child in group.children" :key="child.key">
                <SidebarItem
                    :item="child"
                    :is-active="child.key === currentItemKey"
                    :mode="mode"
                    :is-sub-item="true"
                    @navigate="(item) => emit('navigate', item)"
                />
            </li>
        </ul>

        <!-- Popover de sub-items (modo compacto) -->
        <div
            v-if="compact && popoverOpen"
            class="absolute left-full top-0 ml-2 z-50 min-w-[220px] rounded-lg border border-border bg-surface-elevated shadow-popover p-2"
            role="menu"
            @mouseenter="openPopover"
            @mouseleave="schedulePopoverClose"
        >
            <div class="px-2 pb-2 mb-1 border-b border-border text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                {{ label }}
            </div>
            <ul role="list" class="space-y-0.5">
                <li v-for="child in group.children" :key="child.key">
                    <SidebarItem
                        :item="child"
                        :is-active="child.key === currentItemKey"
                        mode="expanded"
                        :is-sub-item="false"
                        @navigate="(item) => { emit('navigate', item); popoverOpen = false; }"
                    />
                </li>
            </ul>
        </div>
    </div>
</template>
