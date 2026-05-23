<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth.js';
import { useNavigation } from '@/composables/useNavigation.js';
import HeroIcon from '@/components/layout/icons/HeroIcon.vue';

const { t } = useI18n();
const auth = useAuthStore();
const { visibleNav } = useNavigation();

const userName = computed(() => auth.user?.name ?? '');

// Atalhos: até 6 items diretos da nav (sem expandir grupos)
const shortcuts = computed(() => {
    const items = [];
    for (const entry of visibleNav.value) {
        if (Array.isArray(entry.children)) {
            // pega o primeiro sub-item de cada grupo
            if (entry.children.length > 0) {
                items.push({
                    ...entry.children[0],
                    icon: entry.icon,
                    parentLabelKey: entry.labelKey,
                });
            }
        } else if (entry.key !== 'dashboard') {
            items.push(entry);
        }
        if (items.length >= 6) {
            break;
        }
    }
    return items;
});
</script>

<template>
    <div class="p-6 max-w-5xl mx-auto">
        <h1 class="text-2xl font-semibold text-foreground">
            {{ t('layout.panel_home.welcome', { name: userName }) }}
        </h1>
        <p class="mt-1 text-sm text-foreground-muted">
            {{ t('layout.panel_home.subtitle') }}
        </p>

        <section v-if="shortcuts.length > 0" class="mt-8">
            <h2 class="text-sm font-semibold text-foreground-muted uppercase tracking-wide mb-3">
                {{ t('layout.panel_home.shortcuts_heading') }}
            </h2>
            <ul role="list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <li v-for="item in shortcuts" :key="item.key">
                    <router-link
                        :to="{ name: item.routeName }"
                        class="flex items-center gap-3 p-4 rounded-lg border border-border bg-surface-elevated hover:border-primary-300 hover:shadow-card transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    >
                        <span class="inline-flex w-10 h-10 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                            <HeroIcon v-if="item.icon" :name="item.icon" class="w-5 h-5" />
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-foreground">
                                {{ t(item.labelKey) }}
                            </span>
                            <span v-if="item.parentLabelKey" class="block text-xs text-foreground-muted">
                                {{ t(item.parentLabelKey) }}
                            </span>
                        </span>
                    </router-link>
                </li>
            </ul>
        </section>
    </div>
</template>
