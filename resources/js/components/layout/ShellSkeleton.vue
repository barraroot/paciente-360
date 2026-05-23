<script setup>
/**
 * Skeleton de loading exibido durante o boot da SPA enquanto `auth.fetchMe()`
 * está em curso. Replica a estrutura visual do shell para evitar flash de tela
 * branca (FR-023, SC-004).
 */
</script>

<template>
    <div class="flex h-screen bg-surface" aria-hidden="true">
        <!-- Sidebar skeleton -->
        <div class="hidden md:flex w-60 flex-col border-r border-border p-3 space-y-2">
            <div v-for="n in 8" :key="n" class="h-9 rounded-md bg-surface-muted animate-pulse"></div>
        </div>
        <!-- Main column skeleton -->
        <div class="flex-1 flex flex-col">
            <div class="h-14 border-b border-border flex items-center px-4 gap-3">
                <div class="w-32 h-5 rounded bg-surface-muted animate-pulse"></div>
                <div class="ml-auto w-9 h-9 rounded-full bg-surface-muted animate-pulse"></div>
            </div>
            <div class="flex-1 p-6 space-y-4">
                <div class="h-8 w-1/3 rounded bg-surface-muted animate-pulse"></div>
                <div class="h-32 rounded-lg bg-surface-muted animate-pulse"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="h-24 rounded-lg bg-surface-muted animate-pulse"></div>
                    <div class="h-24 rounded-lg bg-surface-muted animate-pulse"></div>
                </div>
            </div>
        </div>
    </div>
</template>
