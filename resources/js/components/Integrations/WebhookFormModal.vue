<script setup>
import { ref, watch, computed } from 'vue';

/**
 * T203 (Fase 8 — Lote D US-11.1) — Modal de criação/edição de webhook.
 *
 * Padrão Fase 6: Teleport + role=dialog + focus trap + Esc + bottom-sheet mobile.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    endpoint: { type: Object, default: null },
});

const emit = defineEmits(['close', 'save']);

const allEvents = [
    'agendamento.criado',
    'agendamento.confirmado',
    'agendamento.cancelado',
    'agendamento.reagendado',
    'paciente.criado',
    'paciente.atualizado',
    'mensagem.recebida',
    'mensagem.enviada',
    'prescricao.criada',
    'prescricao.renovada',
    'campanha.disparada',
    'consentimento.registrado',
    'consentimento.revogado',
];

const form = ref({ name: '', url: '', events_subscribed: [], is_active: true });
const errors = ref({});
const saving = ref(false);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        errors.value = {};
        if (props.endpoint) {
            form.value = {
                name: props.endpoint.name,
                url: props.endpoint.url,
                events_subscribed: [...(props.endpoint.events_subscribed ?? [])],
                is_active: props.endpoint.is_active,
            };
        } else {
            form.value = { name: '', url: '', events_subscribed: [], is_active: true };
        }
    },
);

const isEdit = computed(() => props.endpoint !== null);

async function onSubmit() {
    errors.value = {};
    if (!form.value.name) errors.value.name = 'Obrigatório.';
    if (!form.value.url) errors.value.url = 'Obrigatório.';
    if (form.value.events_subscribed.length === 0)
        errors.value.events_subscribed = 'Selecione ao menos um evento.';
    if (Object.keys(errors.value).length > 0) return;

    saving.value = true;
    try {
        await emit('save', form.value);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="modal-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="wh-form-title"
            @click.self="emit('close')"
            @keydown.esc.prevent="emit('close')"
        >
            <div class="modal-panel">
                <header class="modal-panel__header">
                    <h2 id="wh-form-title">{{ isEdit ? 'Editar webhook' : 'Novo webhook' }}</h2>
                    <button
                        type="button"
                        class="btn-icon"
                        aria-label="Fechar"
                        @click="emit('close')"
                    >
                        ×
                    </button>
                </header>

                <form class="modal-panel__body" @submit.prevent="onSubmit">
                    <div class="field">
                        <label for="wh-name">Nome</label>
                        <input id="wh-name" v-model="form.name" type="text" maxlength="120" />
                        <p v-if="errors.name" class="field__error">{{ errors.name }}</p>
                    </div>

                    <div class="field">
                        <label for="wh-url">URL (HTTPS)</label>
                        <input
                            id="wh-url"
                            v-model="form.url"
                            type="url"
                            placeholder="https://api.exemplo.com/webhook"
                        />
                        <p v-if="errors.url" class="field__error">{{ errors.url }}</p>
                    </div>

                    <fieldset class="field">
                        <legend>Eventos a assinar</legend>
                        <div class="events-grid">
                            <label v-for="ev in allEvents" :key="ev" class="event-checkbox">
                                <input
                                    type="checkbox"
                                    :value="ev"
                                    v-model="form.events_subscribed"
                                />
                                <span>{{ ev }}</span>
                            </label>
                        </div>
                        <p v-if="errors.events_subscribed" class="field__error">
                            {{ errors.events_subscribed }}
                        </p>
                    </fieldset>

                    <div class="field">
                        <label>
                            <input type="checkbox" v-model="form.is_active" />
                            Ativo
                        </label>
                    </div>

                    <footer class="modal-panel__footer">
                        <button type="button" class="btn btn--secondary" @click="emit('close')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn--primary" :disabled="saving">
                            {{ saving ? 'Salvando…' : isEdit ? 'Salvar' : 'Criar' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    z-index: 50;
}
@media (min-width: 640px) {
    .modal-overlay {
        align-items: center;
    }
}
.modal-panel {
    background: var(--color-surface-elevated);
    border-radius: 0.75rem 0.75rem 0 0;
    padding: 1.25rem;
    max-width: 640px;
    width: 100%;
    max-height: 90vh;
    overflow: auto;
}
@media (min-width: 640px) {
    .modal-panel {
        border-radius: 0.75rem;
    }
}
.modal-panel__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.modal-panel__footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 1rem;
}
.field {
    margin-bottom: 1rem;
}
.field label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.25rem;
}
.field input[type='text'],
.field input[type='url'] {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--color-border-strong);
    border-radius: 0.375rem;
}
.field__error {
    color: var(--color-danger-600);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}
.events-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
.event-checkbox {
    font-size: 0.875rem;
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
}
.btn--primary {
    background: var(--color-primary-700);
    color: white;
    border: none;
}
.btn--secondary {
    background: var(--color-border);
    color: var(--color-foreground);
    border: 1px solid var(--color-border-strong);
}
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-icon {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}
</style>
