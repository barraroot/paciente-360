<script setup>
import { ref, computed, nextTick } from 'vue';
import DOMPurify from 'dompurify';
import MarkdownToolbar from '@/components/Ia/MarkdownToolbar.vue';
import { markdownTemplates } from '@/components/Ia/markdownTemplates.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    rows: { type: Number, default: 12 },
    templateKey: { type: String, default: null }, // persona | knowledge_base | guardrail
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const tab = ref('edit'); // 'edit' | 'preview'
const textareaRef = ref(null);
const copied = ref(false);

const template = computed(() => (props.templateKey ? markdownTemplates[props.templateKey] : null));
const isEmpty = computed(() => !props.modelValue || props.modelValue.trim() === '');
const showRequiredHint = computed(() => props.required && isEmpty.value);

/** Renderização Markdown→HTML mínima e determinística; DOMPurify é o gate de segurança. */
const previewHtml = computed(() =>
    DOMPurify.sanitize(renderMarkdown(props.modelValue ?? ''), {
        ALLOWED_TAGS: [
            'h1',
            'h2',
            'h3',
            'p',
            'strong',
            'em',
            'code',
            'pre',
            'blockquote',
            'ul',
            'ol',
            'li',
            'a',
            'br',
            'table',
            'thead',
            'tbody',
            'tr',
            'th',
            'td',
            'hr',
        ],
        ALLOWED_ATTR: ['href'],
        ALLOWED_URI_REGEXP: /^(?:https?:|mailto:|tel:)/i,
    }),
);

function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function inline(s) {
    return escapeHtml(s)
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^_])_([^_]+)_/g, '$1<em>$2</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
}

function renderMarkdown(md) {
    const lines = md.replace(/\r\n/g, '\n').split('\n');
    const out = [];
    let listType = null;
    const closeList = () => {
        if (listType) {
            out.push(`</${listType}>`);
            listType = null;
        }
    };

    for (const raw of lines) {
        const line = raw.trimEnd();
        if (line === '') {
            closeList();
            continue;
        }

        let m;
        if ((m = line.match(/^(#{1,3})\s+(.*)$/))) {
            closeList();
            out.push(`<h${m[1].length}>${inline(m[2])}</h${m[1].length}>`);
            continue;
        }
        if (/^(-{3,}|\*{3,})$/.test(line)) {
            closeList();
            out.push('<hr>');
            continue;
        }
        if ((m = line.match(/^>\s?(.*)$/))) {
            closeList();
            out.push(`<blockquote>${inline(m[1])}</blockquote>`);
            continue;
        }
        if ((m = line.match(/^\d+\.\s+(.*)$/))) {
            if (listType !== 'ol') {
                closeList();
                out.push('<ol>');
                listType = 'ol';
            }
            out.push(`<li>${inline(m[1])}</li>`);
            continue;
        }
        if ((m = line.match(/^[-*]\s+(.*)$/))) {
            if (listType !== 'ul') {
                closeList();
                out.push('<ul>');
                listType = 'ul';
            }
            out.push(`<li>${inline(m[1])}</li>`);
            continue;
        }
        if (/^\|.*\|$/.test(line)) {
            closeList();
            out.push(`<p>${inline(line)}</p>`);
            continue;
        }
        closeList();
        out.push(`<p>${inline(line)}</p>`);
    }
    closeList();
    return out.join('\n');
}

function onInput(e) {
    emit('update:modelValue', e.target.value);
}

async function applyInsert(op) {
    const el = textareaRef.value;
    const value = props.modelValue ?? '';
    const start = el ? el.selectionStart : value.length;
    const end = el ? el.selectionEnd : value.length;
    const selected = value.slice(start, end) || op.placeholder || '';

    let prefix = op.before ?? '';
    if (op.block && start > 0 && value[start - 1] !== '\n') {
        prefix = '\n' + prefix;
    }
    const insertion = prefix + selected + (op.after ?? '');
    const next = value.slice(0, start) + insertion + value.slice(end);
    emit('update:modelValue', next);

    await nextTick();
    if (el) {
        const caret = start + prefix.length;
        el.focus();
        el.setSelectionRange(caret, caret + selected.length);
    }
}

function applyTemplate() {
    if (template.value) {
        emit('update:modelValue', template.value.content);
        tab.value = 'edit';
    }
}

async function copy() {
    try {
        await navigator.clipboard.writeText(props.modelValue ?? '');
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // clipboard indisponível — silencioso
    }
}
</script>

<template>
    <div>
        <div v-if="label" class="mb-1 flex items-center justify-between">
            <label class="block text-sm font-medium text-foreground">
                {{ label }} <span v-if="required" class="text-danger-500">*</span>
            </label>
            <div class="flex items-center gap-2 text-xs">
                <button
                    v-if="template"
                    type="button"
                    class="text-indigo-600 hover:underline"
                    @click="applyTemplate"
                >
                    Usar modelo
                </button>
                <button type="button" class="text-foreground-muted hover:underline" @click="copy">
                    {{ copied ? 'Copiado!' : 'Copiar' }}
                </button>
            </div>
        </div>

        <!-- Abas Editar / Visualizar -->
        <div class="flex gap-1 text-xs">
            <button
                type="button"
                :class="
                    tab === 'edit'
                        ? 'bg-white border-border-strong text-foreground'
                        : 'bg-surface-muted border-transparent text-foreground-muted'
                "
                class="rounded-t-md border border-b-0 px-3 py-1 font-medium"
                @click="tab = 'edit'"
            >
                Editar
            </button>
            <button
                type="button"
                :class="
                    tab === 'preview'
                        ? 'bg-white border-border-strong text-foreground'
                        : 'bg-surface-muted border-transparent text-foreground-muted'
                "
                class="rounded-t-md border border-b-0 px-3 py-1 font-medium"
                @click="tab = 'preview'"
            >
                Visualizar
            </button>
        </div>

        <template v-if="tab === 'edit'">
            <MarkdownToolbar @insert="applyInsert" />
            <textarea
                ref="textareaRef"
                :value="modelValue"
                :rows="rows"
                class="w-full rounded-b-lg border-border-strong font-mono text-sm"
                :class="{ 'border-danger-400': error || showRequiredHint }"
                @input="onInput"
            ></textarea>
        </template>

        <div
            v-else
            class="prose prose-sm max-w-none rounded-b-lg border border-border-strong bg-white p-4 text-sm"
            v-html="previewHtml"
        ></div>

        <p v-if="error" class="mt-1 text-xs text-danger-600">{{ error }}</p>
        <p v-else-if="showRequiredHint" class="mt-1 text-xs text-foreground-subtle">
            Conteúdo obrigatório.
        </p>
    </div>
</template>
