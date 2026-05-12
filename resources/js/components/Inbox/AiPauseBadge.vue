<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    aiPausedUntil: {
        type: String,
        default: null,
    },
});

const now = ref(Date.now());
let intervalId = null;

onMounted(() => {
    intervalId = setInterval(() => {
        now.value = Date.now();
    }, 30_000); // update every 30s
});

onUnmounted(() => {
    if (intervalId !== null) {
        clearInterval(intervalId);
    }
});

/**
 * Returns true when ai_paused_until is in the future.
 */
const isPaused = computed(() => {
    if (!props.aiPausedUntil) return false;
    return new Date(props.aiPausedUntil).getTime() > now.value;
});

/**
 * Seconds remaining until pause expires.
 */
const secondsRemaining = computed(() => {
    if (!isPaused.value) return 0;
    return Math.max(0, Math.floor((new Date(props.aiPausedUntil).getTime() - now.value) / 1000));
});

/**
 * Human-readable countdown: "Xh Ymin" or "Ymin" or "Xs".
 */
const countdownLabel = computed(() => {
    const secs = secondsRemaining.value;
    if (secs <= 0) return '';

    const hours = Math.floor(secs / 3600);
    const mins = Math.floor((secs % 3600) / 60);
    const remainSecs = secs % 60;

    if (hours > 0 && mins > 0) return `${hours}h ${mins}min`;
    if (hours > 0) return `${hours}h`;
    if (mins > 0) return `${mins}min`;
    return `${remainSecs}s`;
});

/**
 * Badge color:
 *  - Last 5 minutes: red (danger)
 *  - Otherwise: amber (warning)
 */
const badgeClass = computed(() => {
    if (secondsRemaining.value <= 300) {
        return 'ai-pause-badge ai-pause-badge--danger';
    }
    return 'ai-pause-badge ai-pause-badge--warning';
});
</script>

<template>
    <span
        v-if="isPaused"
        :class="badgeClass"
        :title="t('inbox.ai_pause.badge_title', { until: aiPausedUntil })"
    >
        {{ t('inbox.ai_pause.badge_label', { countdown: countdownLabel }) }}
    </span>
</template>

<style scoped>
.ai-pause-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.ai-pause-badge--warning {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.ai-pause-badge--danger {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}
</style>
