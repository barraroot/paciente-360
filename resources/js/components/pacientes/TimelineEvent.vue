<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';

const { t } = useI18n();
const { formatDateTime } = useI18nFormat();

const props = defineProps({
    evento: {
        type: Object,
        required: true,
    },
});

// ─── Ícone por tipo ───────────────────────────────────────────────────────────

const ICONE_POR_TIPO = {
    'paciente.criado': '📝',
    'paciente.atualizado': '✏️',
    'paciente.status_alterado': '🔁',
    'paciente.tag_aplicada': '🏷️',
    'paciente.tag_removida': '🏷️',
    'paciente.anotacao_criada': '📌',
    'paciente.anotacao_retratada': '↺',
    'paciente.mesclado': '🔀',
    'paciente.mesclagem_revertida': '↩️',
    'lead.movido_no_funil': '📊',
    'paciente.anonimizado': '🔒',
    'paciente.importado': '📥',
    'paciente.exportado': '📤',
};

function icone(tipo) {
    return ICONE_POR_TIPO[tipo] ?? '⏺';
}

// ─── Rótulo de ação traduzido ─────────────────────────────────────────────────

function acaoLabel(tipo) {
    const chave = `timeline.tipo.${tipo}`;
    const traduzido = t(chave);
    // Se a tradução não existe, vue-i18n retorna a própria chave
    return traduzido !== chave ? traduzido : tipo;
}

// ─── Actor ───────────────────────────────────────────────────────────────────

function actorLabel(evento) {
    if (evento.actor?.user_name) {
        return evento.actor.user_name;
    }
    if (evento.actor?.type === 'webhook') {
        return 'Webhook';
    }
    return t('audit.list.actor_system');
}

// ─── Payload resumido ─────────────────────────────────────────────────────────

function payloadResumo(evento) {
    const p = evento.payload ?? {};
    if (evento.tipo === 'paciente.status_alterado') {
        if (p.status_anterior && p.novo_status) {
            return `${p.status_anterior} → ${p.novo_status}`;
        }
    }
    if (evento.tipo === 'paciente.atualizado' && p.campos_alterados) {
        const campos = Object.keys(p.campos_alterados).join(', ');
        return `Campos: ${campos}`;
    }
    if (evento.tipo === 'paciente.tag_aplicada' || evento.tipo === 'paciente.tag_removida') {
        return p.tag_nome ? `#${p.tag_nome}` : '';
    }
    return null;
}

// ─── Expandir texto de anotação ───────────────────────────────────────────────

const expandido = ref(false);

const LIMITE_CHARS = 200;

function textoAnotacao() {
    return (props.evento.payload ?? {}).texto ?? null;
}

function textoExibido() {
    const texto = textoAnotacao();
    if (!texto) { return null; }
    if (expandido.value || texto.length <= LIMITE_CHARS) { return texto; }
    return texto.slice(0, LIMITE_CHARS) + '…';
}
</script>

<template>
    <div class="flex gap-3 rounded-xl border border-border bg-surface-elevated p-4">
        <!-- Ícone -->
        <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface text-lg"
            aria-hidden="true"
        >
            {{ icone(evento.tipo) }}
        </div>

        <!-- Conteúdo -->
        <div class="min-w-0 flex-1">
            <!-- Linha 1: ação -->
            <p class="text-sm font-medium text-foreground">
                {{ acaoLabel(evento.tipo) }}
            </p>

            <!-- Linha 2: actor + timestamp -->
            <p class="mt-0.5 text-xs text-foreground-muted">
                {{ actorLabel(evento) }}
                <span v-if="evento.created_at" class="mx-1">·</span>
                <time
                    v-if="evento.created_at"
                    :datetime="evento.created_at"
                >
                    {{ formatDateTime(evento.created_at) }}
                </time>
            </p>

            <!-- Linha 3: payload resumido -->
            <p
                v-if="payloadResumo(evento)"
                class="mt-1 text-xs text-foreground-muted"
            >
                {{ payloadResumo(evento) }}
            </p>

            <!-- Texto de anotação (expandível) -->
            <template v-if="textoAnotacao()">
                <p class="mt-2 text-sm text-foreground">
                    {{ textoExibido() }}
                </p>
                <button
                    v-if="textoAnotacao().length > LIMITE_CHARS"
                    type="button"
                    class="mt-1 text-xs font-medium text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="expandido = !expandido"
                >
                    {{ expandido ? t('common.show_less') : t('common.show_more') }}
                </button>
            </template>
        </div>
    </div>
</template>
