<script setup>
/**
 * T242 — QuickReplyAutocomplete
 *
 * Dropdown de respostas rápidas ativado quando o usuário digita `/` no MessageInput.
 * Filtra por shortcut conforme digitação. Emite `select` com a resposta rápida escolhida.
 *
 * Uso:
 *   <QuickReplyAutocomplete
 *     :query="queryStr"
 *     :replies="allReplies"
 *     @select="handleSelect"
 *     @close="handleClose"
 *   />
 *
 * Props:
 *   query   — string após `/` para filtrar (ex.: "preco" filtra "/preco", "/precos")
 *   replies — array de QuickReply objects { id, shortcut, content, scope, usage_count }
 *
 * Emits:
 *   select(reply) — usuário escolheu uma resposta rápida
 *   close         — usuário fechou sem escolher (Escape)
 */

import { computed, ref, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    /** Texto após a `/` para filtrar os atalhos */
    query: {
        type: String,
        default: '',
    },
    /** Lista completa de respostas rápidas visíveis para o usuário */
    replies: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['select', 'close']);

// ─── Filtro ───────────────────────────────────────────────────────────────────

const filtered = computed(() => {
    const q = props.query.toLowerCase();
    return props.replies
        .filter((r) => r.shortcut.toLowerCase().includes(q))
        .slice(0, 8); // máximo 8 itens visíveis
});

// ─── Navegação por teclado ────────────────────────────────────────────────────

const activeIndex = ref(0);
const listRef = ref(null);

watch(
    () => props.query,
    () => {
        activeIndex.value = 0;
    },
);

watch(activeIndex, async (idx) => {
    await nextTick();
    const items = listRef.value?.querySelectorAll('[role="option"]');
    if (items?.[idx]) {
        items[idx].scrollIntoView({ block: 'nearest' });
    }
});

function onKeydown(event) {
    if (!filtered.value.length) { return; }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            activeIndex.value = (activeIndex.value + 1) % filtered.value.length;
            break;
        case 'ArrowUp':
            event.preventDefault();
            activeIndex.value = (activeIndex.value - 1 + filtered.value.length) % filtered.value.length;
            break;
        case 'Enter':
        case 'Tab':
            event.preventDefault();
            select(filtered.value[activeIndex.value]);
            break;
        case 'Escape':
            emit('close');
            break;
    }
}

function select(reply) {
    if (!reply) { return; }
    emit('select', reply);
}

// Exposição para o MessageInput usar onKeydown em captura
defineExpose({ onKeydown });
</script>

<template>
    <div
        v-if="filtered.length > 0"
        class="absolute bottom-full left-0 right-0 z-20 mb-1 max-h-60 overflow-y-auto rounded-xl border border-border bg-surface shadow-lg"
        role="listbox"
        :aria-label="t('inbox.respostas_rapidas.autocomplete_label')"
        ref="listRef"
    >
        <ul class="py-1">
            <li
                v-for="(reply, idx) in filtered"
                :key="reply.id"
                role="option"
                :aria-selected="idx === activeIndex"
                class="flex cursor-pointer items-start gap-3 px-4 py-2.5 transition-colors"
                :class="idx === activeIndex ? 'bg-primary-50 text-primary-900' : 'hover:bg-surface-raised'"
                @click="select(reply)"
                @mouseenter="activeIndex = idx"
            >
                <span
                    class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 font-mono text-xs font-semibold"
                    :class="
                        idx === activeIndex
                            ? 'bg-primary-100 text-primary-700'
                            : 'bg-surface-raised text-foreground-muted'
                    "
                >
                    {{ reply.shortcut }}
                </span>
                <span class="line-clamp-2 text-sm text-foreground">{{ reply.content }}</span>
            </li>
        </ul>

        <div class="border-t border-border px-4 py-1.5">
            <p class="text-xs text-foreground-muted">
                {{ t('inbox.respostas_rapidas.autocomplete_hint') }}
            </p>
        </div>
    </div>
</template>
