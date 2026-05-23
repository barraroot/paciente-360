<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import HeroIcon from './icons/HeroIcon.vue';

const props = defineProps({
    item: { type: Object, required: true },
    isActive: { type: Boolean, default: false },
    mode: { type: String, default: 'expanded' }, // 'expanded' | 'compact'
    isSubItem: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate']);

const { t } = useI18n();

const label = computed(() => t(props.item.labelKey));
const compact = computed(() => props.mode === 'compact');
</script>

<template>
    <router-link
        :to="{ name: item.routeName }"
        :title="compact ? label : undefined"
        :aria-current="isActive ? 'page' : undefined"
        :aria-label="compact ? label : undefined"
        class="group flex items-center gap-3 rounded-md text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
        :class="[
            compact ? 'justify-center px-2 py-2' : 'px-3 py-2',
            isSubItem ? (compact ? '' : 'pl-9') : '',
            isActive
                ? 'bg-primary-50 text-primary-700 font-medium'
                : 'text-foreground-muted hover:bg-surface-muted hover:text-foreground',
        ]"
        @click="emit('navigate', item)"
    >
        <HeroIcon
            v-if="item.icon"
            :name="item.icon"
            class="shrink-0 w-5 h-5"
            :class="isActive ? 'text-primary-700' : 'text-foreground-muted group-hover:text-foreground'"
        />
        <span v-if="!compact" class="truncate">{{ label }}</span>
        <span v-else-if="!item.icon" class="text-xs font-medium">{{ label.charAt(0) }}</span>
    </router-link>
</template>
