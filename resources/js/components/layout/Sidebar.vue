<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNavigation } from '@/composables/useNavigation.js';
import { useShellPreferences } from '@/composables/useShellPreferences.js';
import SidebarItem from './SidebarItem.vue';
import SidebarGroup from './SidebarGroup.vue';

const props = defineProps({
    mode: { type: String, default: 'expanded' }, // 'expanded' | 'compact'
});

const emit = defineEmits(['navigate']);

const { t } = useI18n();
const { visibleNav, currentGroupKey, currentItemKey } = useNavigation();
const { isGroupExpanded, toggleGroup } = useShellPreferences();

const isCompact = computed(() => props.mode === 'compact');

function isGroup(entry) {
    return Array.isArray(entry.children);
}

function onToggleGroup(groupKey) {
    toggleGroup(groupKey);
}

function onNavigate(item) {
    emit('navigate', item);
}
</script>

<template>
    <nav
        role="navigation"
        :aria-label="t('layout.sidebar.aria_label')"
        class="h-full flex flex-col bg-surface border-r border-border overflow-y-auto"
        :class="isCompact ? 'w-16' : 'w-60'"
    >
        <!-- Spacer top: nas variantes desktop/tablet a topbar fica fora; aqui só padding -->
        <div class="px-3 py-4 flex-1">
            <ul role="list" class="space-y-1">
                <li v-for="entry in visibleNav" :key="entry.key">
                    <SidebarGroup
                        v-if="isGroup(entry)"
                        :group="entry"
                        :is-expanded="isGroupExpanded(entry.key)"
                        :force-expand="entry.key === currentGroupKey"
                        :mode="mode"
                        :current-item-key="currentItemKey"
                        @toggle="onToggleGroup"
                        @navigate="onNavigate"
                    />
                    <SidebarItem
                        v-else
                        :item="entry"
                        :is-active="entry.key === currentItemKey"
                        :mode="mode"
                        @navigate="onNavigate"
                    />
                </li>
            </ul>
        </div>
    </nav>
</template>
