<script setup>
/**
 * Auxiliar de formatação determinístico (US8 / T091) — sem IA.
 *
 * Cada botão emite uma instrução de inserção de Markdown; o MarkdownEditor
 * aplica no cursor. Estrutura: { before, after, placeholder, block }.
 */
const emit = defineEmits(['insert']);

const actions = [
    {
        key: 'h1',
        label: 'Título',
        title: 'Título (H1)',
        op: { before: '# ', placeholder: 'Título', block: true },
    },
    {
        key: 'h2',
        label: 'Subtítulo',
        title: 'Subtítulo (H2)',
        op: { before: '## ', placeholder: 'Subtítulo', block: true },
    },
    {
        key: 'bold',
        label: 'B',
        title: 'Negrito',
        class: 'font-bold',
        op: { before: '**', after: '**', placeholder: 'texto' },
    },
    {
        key: 'italic',
        label: 'I',
        title: 'Itálico',
        class: 'italic',
        op: { before: '_', after: '_', placeholder: 'texto' },
    },
    {
        key: 'p',
        label: '¶',
        title: 'Parágrafo',
        op: { before: '\n\n', placeholder: '', block: false },
    },
    {
        key: 'quote',
        label: '❝',
        title: 'Citação',
        op: { before: '> ', placeholder: 'citação', block: true },
    },
    {
        key: 'ul',
        label: '•',
        title: 'Lista',
        op: { before: '- ', placeholder: 'item', block: true },
    },
    {
        key: 'checklist',
        label: '☑',
        title: 'Checklist',
        op: { before: '- [ ] ', placeholder: 'tarefa', block: true },
    },
    {
        key: 'link',
        label: '🔗',
        title: 'Link',
        op: { before: '[', after: '](https://)', placeholder: 'texto' },
    },
    {
        key: 'table',
        label: '▦',
        title: 'Tabela',
        op: {
            before: '| Coluna A | Coluna B |\n| --- | --- |\n| valor | valor |\n',
            placeholder: '',
            block: true,
        },
    },
];
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-1 rounded-t-lg border border-b-0 border-border-strong bg-surface-muted px-2 py-1.5"
    >
        <button
            v-for="action in actions"
            :key="action.key"
            type="button"
            :title="action.title"
            :aria-label="action.title"
            :class="action.class"
            class="inline-flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-sm text-foreground-muted transition hover:bg-border"
            @click="emit('insert', action.op)"
        >
            {{ action.label }}
        </button>
    </div>
</template>
