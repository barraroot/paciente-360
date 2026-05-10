<script setup>
defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

function handleOverlayClick(event) {
    if (event.target === event.currentTarget) {
        emit('close');
    }
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="title ? 'confirm-modal-title' : undefined"
            @click="handleOverlayClick"
            @keydown="handleKeydown"
        >
            <div
                class="bg-surface-elevated rounded-xl shadow-xl max-w-md w-full mx-4 p-6 border border-border"
                tabindex="-1"
            >
                <h2
                    v-if="title"
                    id="confirm-modal-title"
                    class="text-lg font-semibold text-foreground mb-4"
                >
                    {{ title }}
                </h2>

                <div class="text-sm text-foreground">
                    <slot />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="emit('close')"
                    >
                        <slot name="cancel-label">Cancelar</slot>
                    </button>
                    <slot name="actions">
                        <button
                            type="button"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="emit('confirm')"
                        >
                            Confirmar
                        </button>
                    </slot>
                </div>
            </div>
        </div>
    </Teleport>
</template>
