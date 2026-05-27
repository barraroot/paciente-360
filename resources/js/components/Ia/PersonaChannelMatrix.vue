<script setup>
const props = defineProps({
    rows: { type: Array, required: true },
});

const emit = defineEmits(['toggle']);

const channels = [
    { key: 'whatsapp', label: 'WhatsApp' },
    { key: 'web', label: 'Widget Site' },
    { key: 'instagram', label: 'Instagram' },
];

function onToggle(row, channelKey, event) {
    emit('toggle', {
        ai_persona_id: row.ai_persona_id,
        channel_type: channelKey,
        value: event.target.checked,
    });
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-border bg-white">
        <table class="min-w-full divide-y divide-border">
            <thead class="bg-surface-muted">
                <tr
                    class="text-left text-xs font-medium uppercase tracking-wide text-foreground-muted"
                >
                    <th class="px-4 py-3">Persona</th>
                    <th v-for="c in channels" :key="c.key" class="px-4 py-3 text-center">
                        {{ c.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-for="row in rows" :key="row.ai_persona_id" class="text-sm text-foreground">
                    <td class="px-4 py-3">
                        <span class="font-medium text-foreground">{{ row.name }}</span>
                        <span
                            v-if="!row.is_active"
                            class="ml-2 inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-xs text-foreground-muted"
                            >inativa</span
                        >
                    </td>
                    <td v-for="c in channels" :key="c.key" class="px-4 py-3 text-center">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-border-strong text-indigo-600"
                            :checked="row.channels[c.key]"
                            :aria-label="`${row.name} no canal ${c.label}`"
                            @change="onToggle(row, c.key, $event)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
