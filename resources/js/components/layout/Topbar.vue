<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import { useNavigation } from '@/composables/useNavigation.js';
import HeroIcon from './icons/HeroIcon.vue';
import UserMenu from './UserMenu.vue';

defineProps({
    showHamburger: { type: Boolean, default: false },
    showCollapseToggle: { type: Boolean, default: false },
    sidebarMode: { type: String, default: 'expanded' },
});

const emit = defineEmits(['open-drawer', 'toggle-sidebar']);

const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();
const { labelKeyForRoute } = useNavigation();

const tenantName = computed(() => auth.tenant?.name ?? '');

const contextTitle = computed(() => {
    const meta = route.meta?.title;
    if (typeof meta === 'function') {
        try {
            return meta(route);
        } catch {
            return '';
        }
    }
    if (typeof meta === 'string' && meta.length > 0) {
        return meta;
    }
    const fallbackKey = labelKeyForRoute(route.name);
    return fallbackKey ? t(fallbackKey) : '';
});

const collapseTitle = computed(() =>
    t(
        // eslint-disable-next-line no-undef
        // istanbul ignore next — i18n string
        // ↓
        // dynamic key based on current mode
        ((m) => (m === 'expanded' ? 'layout.topbar.collapse_sidebar' : 'layout.topbar.expand_sidebar')) // helper
        // we evaluate via prop on the parent caller; keep simple here
        ,
    ),
);
</script>

<template>
    <header
        class="sticky top-0 z-30 flex items-center gap-3 h-14 px-3 sm:px-4 bg-surface-elevated border-b border-border"
    >
        <!-- Hambúrguer (mobile) -->
        <button
            v-if="showHamburger"
            type="button"
            :aria-label="t('layout.topbar.open_sidebar')"
            class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-md hover:bg-surface-muted text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            @click="emit('open-drawer')"
        >
            <HeroIcon name="menu" class="w-5 h-5" />
        </button>

        <!-- Collapse toggle (≥ md) -->
        <button
            v-if="showCollapseToggle"
            type="button"
            :aria-label="sidebarMode === 'expanded' ? t('layout.topbar.collapse_sidebar') : t('layout.topbar.expand_sidebar')"
            :title="sidebarMode === 'expanded' ? t('layout.topbar.collapse_sidebar') : t('layout.topbar.expand_sidebar')"
            class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded-md hover:bg-surface-muted text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            @click="emit('toggle-sidebar')"
        >
            <HeroIcon :name="sidebarMode === 'expanded' ? 'chevron-left' : 'chevron-right'" class="w-5 h-5" />
        </button>

        <!-- Tenant name + título contextual -->
        <div class="flex items-center gap-3 min-w-0 flex-1">
            <span
                :title="tenantName"
                class="font-semibold text-foreground truncate max-w-[180px] sm:max-w-[260px]"
            >
                {{ tenantName }}
            </span>
            <span v-if="contextTitle" class="hidden sm:inline text-foreground-subtle">
                /
            </span>
            <span
                v-if="contextTitle"
                class="hidden sm:inline text-sm text-foreground-muted truncate"
            >
                {{ contextTitle }}
            </span>
        </div>

        <!-- Slots placeholder: busca + notificações (em breve) -->
        <button
            type="button"
            disabled
            :aria-label="t('layout.topbar.search_placeholder')"
            :title="t('layout.topbar.search_disabled_title')"
            class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded-md text-foreground-subtle cursor-not-allowed"
        >
            <HeroIcon name="search" class="w-5 h-5" />
        </button>
        <button
            type="button"
            disabled
            :aria-label="t('layout.topbar.notifications_aria_label')"
            :title="t('layout.topbar.notifications_disabled_title')"
            class="relative inline-flex items-center justify-center w-9 h-9 rounded-md text-foreground-subtle cursor-not-allowed"
        >
            <HeroIcon name="bell" class="w-5 h-5" />
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center w-4 h-4 text-[10px] font-medium rounded-full bg-surface-muted text-foreground-subtle">0</span>
        </button>

        <!-- User menu -->
        <UserMenu />
    </header>
</template>
