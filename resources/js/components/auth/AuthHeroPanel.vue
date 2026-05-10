<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <aside
        class="hidden lg:flex flex-col justify-center gap-6 bg-primary-950 px-12 py-16"
        :aria-label="t('auth.hero.aria_label')"
    >
        <!-- Headline principal -->
        <div class="mb-4">
            <p class="text-4xl font-bold leading-tight text-white">
                {{ t('auth.hero.headline_prefix') }}
                <span class="text-success-300">{{ t('auth.hero.headline_stat') }}</span>
                {{ t('auth.hero.headline_suffix') }}
            </p>
        </div>

        <!-- Card 1 -->
        <div class="rounded-xl bg-primary-900 p-6">
            <h2 class="text-base font-semibold text-white mb-1">
                {{ t('auth.hero.card1_title') }}
            </h2>
            <p class="text-sm text-primary-200 leading-relaxed">
                {{ t('auth.hero.card1_body') }}
            </p>
        </div>

        <!-- Card 2 -->
        <div class="rounded-xl bg-primary-900 p-6">
            <h2 class="text-base font-semibold text-white mb-1">
                {{ t('auth.hero.card2_title') }}
            </h2>
            <p class="text-sm text-primary-200 leading-relaxed">
                {{ t('auth.hero.card2_body') }}
            </p>
        </div>

        <!-- Card 3 -->
        <div class="rounded-xl bg-primary-900 p-6">
            <h2 class="text-base font-semibold text-white mb-1">
                {{ t('auth.hero.card3_title') }}
            </h2>
            <p class="text-sm text-primary-200 leading-relaxed">
                {{ t('auth.hero.card3_body') }}
            </p>
        </div>
    </aside>
</template>
