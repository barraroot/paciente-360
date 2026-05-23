<script setup>
import { watch, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';
import Sidebar from './Sidebar.vue';
import HeroIcon from './icons/HeroIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'navigate']);

const { t } = useI18n();
const drawerEl = useTemplateRef('drawerEl');

useShellFocusTrap(drawerEl, () => props.open);

function close() {
    emit('update:open', false);
}

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        event.preventDefault();
        close();
    }
}

function onNavigate(item) {
    // Q1 — fecha IMEDIATAMENTE ao escolher item; navegação ocorre em paralelo.
    close();
    emit('navigate', item);
}

watch(() => props.open, (now) => {
    if (typeof document === 'undefined') {
        return;
    }
    document.body.style.overflow = now ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="md:hidden fixed inset-0 z-50" @keydown="onKeydown">
            <!-- Overlay -->
            <div
                class="absolute inset-0 bg-black/40 transition-opacity"
                @click="close"
            ></div>

            <!-- Drawer -->
            <div
                ref="drawerEl"
                role="dialog"
                aria-modal="true"
                :aria-label="t('layout.drawer.aria_label')"
                class="absolute inset-y-0 left-0 w-72 max-w-[85vw] bg-surface shadow-xl flex flex-col"
            >
                <!-- Header com botão de fechar -->
                <div class="flex items-center justify-end h-14 px-3 border-b border-border">
                    <button
                        type="button"
                        :aria-label="t('layout.topbar.close_sidebar')"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-md hover:bg-surface-muted text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="close"
                    >
                        <HeroIcon name="close" class="w-5 h-5" />
                    </button>
                </div>

                <!-- Sidebar reusada (sempre expanded em drawer) -->
                <Sidebar mode="expanded" class="flex-1 border-r-0" @navigate="onNavigate" />
            </div>
        </div>
    </Teleport>
</template>
