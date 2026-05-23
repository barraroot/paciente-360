<script setup>
import { computed } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },
    width: { type: Number, default: 80 },
    height: { type: Number, default: 24 },
    color: { type: String, default: 'currentColor' },
    strokeWidth: { type: Number, default: 1.5 },
});

const hasData = computed(() => Array.isArray(props.points) && props.points.length >= 2);

const polylinePoints = computed(() => {
    if (! hasData.value) {
        return '';
    }
    const nums = props.points.map((n) => Number(n) || 0);
    const min = Math.min(...nums);
    const max = Math.max(...nums);
    const range = max - min || 1;
    const stepX = props.width / Math.max(1, nums.length - 1);

    return nums
        .map((value, idx) => {
            const x = idx * stepX;
            const y = props.height - ((value - min) / range) * props.height;
            return `${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
});
</script>

<template>
    <svg
        v-if="hasData"
        :width="width"
        :height="height"
        :viewBox="`0 0 ${width} ${height}`"
        :aria-hidden="true"
        class="overflow-visible"
    >
        <polyline
            :points="polylinePoints"
            fill="none"
            :stroke="color"
            :stroke-width="strokeWidth"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
