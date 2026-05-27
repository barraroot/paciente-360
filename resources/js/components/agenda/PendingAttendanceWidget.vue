<script setup>
/**
 * T115 — Widget de dashboard: consultas pendentes de marcação (clarify nº 14).
 *
 * Lista appointments com auto_flagged_at IS NOT NULL AND status IN active.
 */
import { ref, onMounted } from 'vue';
import api from '@/lib/api';
import { useTimezoneRenderer } from '@/composables/useTimezoneRenderer';
import AttendanceMarkButton from './AttendanceMarkButton.vue';

const items = ref([]);
const renderer = useTimezoneRenderer();

async function load() {
    const { data } = await api.get('/agenda/consultas', {
        params: {
            status: ['scheduled', 'confirmed'],
            from: new Date(Date.now() - 7 * 86400000).toISOString(),
        },
    });
    items.value = (data.data || []).filter((a) => a.auto_flagged_at);
}

function onUpdated(updated) {
    items.value = items.value.filter((a) => a.id !== updated.id);
}

onMounted(load);
</script>

<template>
    <section class="border rounded p-4 space-y-2">
        <header class="flex justify-between items-center">
            <h3 class="font-semibold">Pendentes de marcação de comparecimento</h3>
            <button @click="load" class="text-xs text-primary-600">refresh</button>
        </header>

        <p v-if="!items.length" class="text-sm text-foreground-muted">Nenhuma consulta pendente.</p>

        <ul v-else class="divide-y">
            <li v-for="apt in items" :key="apt.id" class="py-2 flex justify-between items-center">
                <span class="text-sm">
                    {{ renderer.formatDateTime(apt.starts_at) }}
                    <span class="text-foreground-muted">— Paciente #{{ apt.paciente_id }}</span>
                </span>
                <AttendanceMarkButton :appointment="apt" @updated="onUpdated" />
            </li>
        </ul>
    </section>
</template>
