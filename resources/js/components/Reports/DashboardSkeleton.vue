<script setup>
/**
 * Skeleton da página completa enquanto a primeira carga acontece.
 * Replica visualmente: 8 KPI cards (4×2) + 2 seções inferiores.
 */
</script>

<template>
    <div aria-hidden="true">
        <!-- KPI grid skeleton -->
        <ul role="list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <li v-for="n in 8" :key="n" class="p-4 rounded-xl border border-border bg-surface-elevated">
                <div class="h-3 w-32 rounded bg-surface-muted animate-pulse mb-3"></div>
                <div class="h-9 w-20 rounded bg-surface-muted animate-pulse mb-2"></div>
                <div class="h-4 w-24 rounded bg-surface-muted animate-pulse"></div>
            </li>
        </ul>

        <!-- 2 sections skeleton -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <section v-for="n in 2" :key="n" class="rounded-xl border border-border bg-surface-elevated p-5">
                <div class="h-4 w-40 rounded bg-surface-muted animate-pulse mb-4"></div>
                <ul role="list" class="space-y-2">
                    <li v-for="m in 5" :key="m" class="h-9 rounded-lg bg-surface-muted animate-pulse"></li>
                </ul>
            </section>
        </div>
    </div>
</template>
