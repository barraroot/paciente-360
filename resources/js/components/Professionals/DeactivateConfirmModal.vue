<script setup>
import { computed, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';

/**
 * **T037 (Spec 012)** — Confirmação acessível de desativação de profissional.
 *
 * Substitui o `window.confirm()` nativo (FR-015/FR-032 + regra do projeto:
 * proibido confirm/prompt/alert nativos). Modal a11y: Teleport, focus trap,
 * Esc fecha, overlay click fecha, foco retorna ao trigger ao fechar.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    professional: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const { t } = useI18n();
const modalEl = useTemplateRef('modalEl');
const openRef = computed(() => props.open);
useShellFocusTrap(modalEl, openRef);

function cancel() {
    emit('cancel');
}

function confirm() {
    emit('confirm');
}

function onKeydown(e) {
    if (e.key === 'Escape' && props.open) {
        e.preventDefault();
        cancel();
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center px-4" @keydown="onKeydown">
            <div class="absolute inset-0 bg-black/40" @click="cancel"></div>
            <div
                ref="modalEl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="deactivate-modal-title"
                aria-describedby="deactivate-modal-body"
                class="relative bg-surface-elevated rounded-xl shadow-popover w-full max-w-md"
            >
                <div class="flex items-center gap-3 px-5 py-4 border-b border-border">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-danger-50 text-danger-600" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </span>
                    <h2 id="deactivate-modal-title" class="text-lg font-semibold text-foreground">
                        {{ t('professionals.modal.deactivate_title') }}
                    </h2>
                </div>

                <div class="px-5 py-4">
                    <p id="deactivate-modal-body" class="text-sm text-foreground-muted">
                        {{ t('professionals.modal.deactivate_body', { name: professional?.name ?? '' }) }}
                    </p>
                </div>

                <footer class="flex justify-end gap-2 px-5 py-4 border-t border-border">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg border border-border text-sm text-foreground hover:bg-surface-muted"
                        @click="cancel"
                    >
                        {{ t('professionals.modal.deactivate_cancel') }}
                    </button>
                    <button
                        type="button"
                        :disabled="loading"
                        class="px-4 py-2 rounded-lg bg-danger-600 text-white text-sm font-semibold hover:bg-danger-700 disabled:opacity-60"
                        @click="confirm"
                    >
                        {{ loading ? '...' : t('professionals.modal.deactivate_confirm') }}
                    </button>
                </footer>
            </div>
        </div>
    </Teleport>
</template>
