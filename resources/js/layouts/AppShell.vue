<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import { useBreakpoint } from '@/composables/useBreakpoint.js';
import { useShellPreferences } from '@/composables/useShellPreferences.js';
import { useNavigation } from '@/composables/useNavigation.js';
import Sidebar from '@/components/layout/Sidebar.vue';
import Topbar from '@/components/layout/Topbar.vue';
import MobileDrawer from '@/components/layout/MobileDrawer.vue';
import ShellSkeleton from '@/components/layout/ShellSkeleton.vue';
import HeroIcon from '@/components/layout/icons/HeroIcon.vue';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const { isMobile } = useBreakpoint();
const { sidebarMode, toggleSidebarMode } = useShellPreferences();
const { isEmpty } = useNavigation();

const drawerOpen = ref(false);

// Ao cruzar breakpoint mobile→desktop, drawer fecha automaticamente (FR-022).
watch(isMobile, (now) => {
    if (! now) {
        drawerOpen.value = false;
    }
});

const isBooting = computed(() => auth.token !== null && auth.user === null);
const showEmptyState = computed(() =>
    ! isBooting.value && auth.user !== null && isEmpty.value,
);

async function handleEmptyLogout() {
    try {
        await auth.logout();
    } catch {
        auth.reset();
    }
    router.push({ name: 'auth.login' });
}
</script>

<template>
    <ShellSkeleton v-if="isBooting" />

    <div v-else class="flex h-screen bg-surface">
        <!-- Sidebar fixa (desktop/tablet) -->
        <Sidebar
            v-if="!isMobile"
            :mode="sidebarMode"
            class="shrink-0"
        />

        <!-- Drawer (mobile) -->
        <MobileDrawer
            v-if="isMobile"
            :open="drawerOpen"
            @update:open="drawerOpen = $event"
        />

        <!-- Coluna principal -->
        <div class="flex-1 flex flex-col min-w-0">
            <Topbar
                :show-hamburger="isMobile"
                :show-collapse-toggle="!isMobile"
                :sidebar-mode="sidebarMode"
                @open-drawer="drawerOpen = true"
                @toggle-sidebar="toggleSidebarMode"
            />

            <main class="flex-1 overflow-y-auto">
                <!-- Empty state: usuário autenticado mas sem nenhuma permission de módulo -->
                <div
                    v-if="showEmptyState"
                    class="h-full flex items-center justify-center px-6"
                >
                    <div class="max-w-md text-center">
                        <div class="inline-flex w-12 h-12 items-center justify-center rounded-full bg-warning-50 text-warning-600 mb-4">
                            <HeroIcon name="shield" class="w-6 h-6" />
                        </div>
                        <h2 class="text-xl font-semibold text-foreground mb-2">
                            {{ t('layout.empty_state.no_modules_title') }}
                        </h2>
                        <p class="text-sm text-foreground-muted mb-6">
                            {{ t('layout.empty_state.no_modules_message') }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="handleEmptyLogout"
                        >
                            <HeroIcon name="logout" class="w-4 h-4" />
                            {{ t('layout.user_menu.logout') }}
                        </button>
                    </div>
                </div>

                <!-- Conteúdo da rota filha -->
                <router-view v-else />
            </main>
        </div>
    </div>
</template>
