<script setup>
/**
 * RescheduleConfirmModal — Confirmação obrigatória ao arrastar consulta (clarify nº 9).
 *
 * Snap-back: emite @cancel quando usuário cancela ou fecha — caller deve chamar
 * `changeInfo.revert()` ao receber o evento.
 *
 * Acessibilidade: role=dialog + aria-modal + Teleport + focus trap + Esc.
 */
import { ref, watch, nextTick } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    pacienteName: { type: String, default: '' },
    fromLabel: { type: String, default: '' },
    toLabel: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const dialogEl = ref(null)

watch(() => props.modelValue, async (open) => {
    if (open) {
        await nextTick()
        // Foca o botão de cancelar por segurança (ação menos destrutiva)
        dialogEl.value?.querySelector('button')?.focus()
    }
})

function getFocusable() {
    if (!dialogEl.value) return []
    return Array.from(
        dialogEl.value.querySelectorAll('button:not([disabled]), [tabindex]:not([tabindex="-1"])')
    )
}

function trapFocus(e) {
    const focusable = getFocusable()
    if (!focusable.length) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (e.key === 'Tab') {
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus()
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus()
        }
    }
}

function cancel() {
    emit('cancel')
    emit('update:modelValue', false)
}

function confirm() {
    emit('confirm')
    emit('update:modelValue', false)
}

function handleOverlayClick(e) {
    if (e.target === e.currentTarget) cancel()
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
            @click="handleOverlayClick"
            @keydown.esc.prevent="cancel"
            @keydown="trapFocus"
        >
            <div
                ref="dialogEl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="reschedule-modal-title"
                class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
            >
                <div class="px-6 py-4 border-b border-border">
                    <h2 id="reschedule-modal-title" class="text-base font-semibold text-foreground">
                        Confirmar reagendamento
                    </h2>
                </div>

                <div class="px-6 py-4">
                    <p class="text-sm text-foreground">
                        Reagendar a consulta de <strong class="font-semibold">{{ pacienteName }}</strong>
                        de <strong class="font-semibold">{{ fromLabel }}</strong>
                        para <strong class="font-semibold">{{ toLabel }}</strong>?
                    </p>
                    <p class="mt-2 text-xs text-foreground-muted">
                        O paciente será notificado sobre a alteração de horário.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                        @click="cancel"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                        @click="confirm"
                    >
                        Confirmar e notificar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
