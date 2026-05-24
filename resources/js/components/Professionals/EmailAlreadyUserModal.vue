<script setup>
import { computed, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';

/**
 * **T059 (Spec 012)** — Confirmação explícita Q2 / FR-005a.
 *
 * Quando o email informado no modo "convite" já pertence a um usuário do
 * tenant, o admin DEVE confirmar explicitamente o vínculo antes do POST ser
 * re-enviado com `confirmed_existing_user=true`. Substitui o `window.confirm()`
 * nativo (inacessível + bloqueia tab-order).
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    existingUser: { type: Object, default: null },
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
        <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center px-4" @keydown="onKeydown">
            <div class="absolute inset-0 bg-black/40" @click="cancel"></div>
            <div
                ref="modalEl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="email-already-user-title"
                aria-describedby="email-already-user-body"
                class="relative bg-surface-elevated rounded-xl shadow-popover w-full max-w-md"
            >
                <div class="px-5 py-4 border-b border-border">
                    <h2 id="email-already-user-title" class="text-lg font-semibold text-foreground">
                        {{ t('professionals.modal.email_already_user_title') }}
                    </h2>
                </div>

                <div class="px-5 py-4">
                    <p id="email-already-user-body" class="text-sm text-foreground-muted">
                        {{ t('professionals.modal.email_already_user_body', { name: existingUser?.name ?? '' }) }}
                    </p>
                </div>

                <footer class="flex justify-end gap-2 px-5 py-4 border-t border-border">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg border border-border text-sm text-foreground hover:bg-surface-muted"
                        @click="cancel"
                    >
                        {{ t('professionals.form.cancel') }}
                    </button>
                    <button
                        type="button"
                        :disabled="loading"
                        class="px-4 py-2 rounded-lg bg-primary-700 text-white text-sm font-semibold hover:bg-primary-800 disabled:opacity-60"
                        @click="confirm"
                    >
                        {{ loading ? '...' : t('professionals.modal.email_already_user_link') }}
                    </button>
                </footer>
            </div>
        </div>
    </Teleport>
</template>
