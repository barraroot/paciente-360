<script setup>
import { ref, computed, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { onClickOutside } from '@vueuse/core';
import { useAuthStore } from '@/stores/auth.js';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';
import HeroIcon from './icons/HeroIcon.vue';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();

const isOpen = ref(false);
const triggerEl = useTemplateRef('triggerEl');
const menuEl = useTemplateRef('menuEl');

useShellFocusTrap(menuEl, isOpen);

onClickOutside(menuEl, () => {
    isOpen.value = false;
}, { ignore: [triggerEl] });

const initials = computed(() => {
    const name = auth.user?.name ?? '';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('') || '?';
});

function toggle() {
    isOpen.value = ! isOpen.value;
}

function onKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        isOpen.value = false;
        triggerEl.value?.focus();
    }
}

function goSessions() {
    isOpen.value = false;
    router.push({ name: 'auth.tokens' });
}

async function handleLogout() {
    isOpen.value = false;
    try {
        await auth.logout();
    } catch {
        // Mesmo em erro, limpe estado local e siga para login (fail-safe).
        auth.reset();
    }
    router.push({ name: 'auth.login' });
}
</script>

<template>
    <div class="relative" @keydown="onKeydown">
        <button
            ref="triggerEl"
            type="button"
            :aria-label="t('layout.topbar.open_user_menu')"
            :aria-expanded="isOpen"
            aria-haspopup="menu"
            class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            @click="toggle"
        >
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 text-sm font-semibold">
                {{ initials }}
            </span>
            <span class="hidden md:inline text-sm font-medium text-foreground truncate max-w-[140px]">
                {{ auth.user?.name }}
            </span>
            <HeroIcon name="chevron-down" class="hidden md:inline w-4 h-4 text-foreground-muted" />
        </button>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="menuEl"
                role="menu"
                aria-orientation="vertical"
                class="fixed z-50 mt-2 w-64 rounded-lg border border-border bg-surface-elevated shadow-popover py-2"
                :style="{
                    top: (triggerEl?.getBoundingClientRect().bottom ?? 0) + 4 + 'px',
                    right: 'calc(100vw - ' + ((triggerEl?.getBoundingClientRect().right ?? 0)) + 'px)',
                }"
            >
                <!-- Header: nome + email -->
                <div class="px-3 py-2 border-b border-border">
                    <p class="text-sm font-semibold text-foreground truncate">{{ auth.user?.name }}</p>
                    <p class="text-xs text-foreground-muted truncate">{{ auth.user?.email }}</p>
                </div>

                <!-- Itens -->
                <button
                    type="button"
                    role="menuitem"
                    class="w-full text-left px-3 py-2 text-sm text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="goSessions"
                >
                    {{ t('layout.user_menu.sessions') }}
                </button>

                <div class="my-1 border-t border-border"></div>

                <button
                    type="button"
                    role="menuitem"
                    class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500"
                    @click="handleLogout"
                >
                    <HeroIcon name="logout" class="w-4 h-4" />
                    {{ t('layout.user_menu.logout') }}
                </button>
            </div>
        </Teleport>
    </div>
</template>
