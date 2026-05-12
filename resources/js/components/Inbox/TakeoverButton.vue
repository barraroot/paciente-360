<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';

const { t } = useI18n();
const store = useInboxStore();

const props = defineProps({
    conversationId: {
        type: [String, Number],
        required: true,
    },
});

const emit = defineEmits(['taken-over']);

const loading = ref(false);
const showCustomModal = ref(false);
const customHours = ref(2);

const DURATION_OPTIONS = [
    { label: '1h', hours: 1 },
    { label: '4h', hours: 4 },
    { label: '8h', hours: 8 },
    { label: '24h', hours: 24 },
];

async function takeover(hours = null) {
    loading.value = true;
    try {
        const payload = hours !== null ? { duration_hours: hours } : {};
        const updated = await store.takeoverAi(props.conversationId, payload);
        emit('taken-over', updated);
    } catch (err) {
        console.error('[TakeoverButton] failed', err);
    } finally {
        loading.value = false;
    }
}

async function takeoverCustom() {
    showCustomModal.value = false;
    await takeover(customHours.value);
}

const dropdownOpen = ref(false);

function toggleDropdown() {
    dropdownOpen.value = !dropdownOpen.value;
}

function closeDropdown() {
    dropdownOpen.value = false;
}
</script>

<template>
    <div class="takeover-button">
        <button
            :disabled="loading"
            class="takeover-button__primary"
            @click="takeover()"
        >
            {{ loading ? t('inbox.ai_pause.taking_over') : t('inbox.ai_pause.takeover') }}
        </button>

        <button
            class="takeover-button__caret"
            :disabled="loading"
            @click.stop="toggleDropdown"
            @blur="closeDropdown"
        >
            ▾
        </button>

        <ul v-if="dropdownOpen" class="takeover-button__dropdown">
            <li
                v-for="opt in DURATION_OPTIONS"
                :key="opt.hours"
                class="takeover-button__option"
                @mousedown.prevent="takeover(opt.hours); closeDropdown()"
            >
                {{ t('inbox.ai_pause.takeover_for', { duration: opt.label }) }}
            </li>
            <li
                class="takeover-button__option"
                @mousedown.prevent="showCustomModal = true; closeDropdown()"
            >
                {{ t('inbox.ai_pause.takeover_custom') }}…
            </li>
        </ul>

        <!-- Custom duration modal -->
        <div v-if="showCustomModal" class="takeover-modal-backdrop" @click.self="showCustomModal = false">
            <div class="takeover-modal">
                <h3>{{ t('inbox.ai_pause.custom_duration') }}</h3>
                <label>
                    {{ t('inbox.ai_pause.hours_label') }}
                    <input
                        v-model.number="customHours"
                        type="number"
                        min="1"
                        max="24"
                        class="takeover-modal__input"
                    />
                </label>
                <div class="takeover-modal__actions">
                    <button @click="showCustomModal = false">{{ t('common.cancel') }}</button>
                    <button @click="takeoverCustom">{{ t('common.confirm') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.takeover-button {
    position: relative;
    display: inline-flex;
}

.takeover-button__primary {
    padding: 6px 12px;
    background: #f59e0b;
    color: #fff;
    border: none;
    border-radius: 4px 0 0 4px;
    font-weight: 600;
    cursor: pointer;
}

.takeover-button__primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.takeover-button__caret {
    padding: 6px 8px;
    background: #d97706;
    color: #fff;
    border: none;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
}

.takeover-button__dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 50;
    list-style: none;
    margin: 4px 0 0;
    padding: 4px 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    min-width: 160px;
}

.takeover-button__option {
    padding: 8px 16px;
    cursor: pointer;
    white-space: nowrap;
}

.takeover-button__option:hover {
    background: #f3f4f6;
}

.takeover-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
}

.takeover-modal {
    background: #fff;
    padding: 24px;
    border-radius: 8px;
    min-width: 280px;
}

.takeover-modal__input {
    display: block;
    margin-top: 8px;
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

.takeover-modal__actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 16px;
}
</style>
