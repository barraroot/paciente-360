<?php

return [
    'persona' => [
        'created' => 'Persona criada com sucesso.',
        'updated' => 'Persona atualizada com sucesso.',
        'activated' => 'Persona ativada.',
        'deactivated' => 'Persona desativada.',
        'deleted' => 'Persona removida.',
        'model_inactive' => 'O modelo de IA selecionado não está ativo e não pode ser usado em novas personas.',
        'invalid_settings' => 'As configurações informadas são incompatíveis com o modelo selecionado.',
    ],
    'knowledge_base' => [
        'created' => 'Base de conhecimento criada.',
        'updated' => 'Base de conhecimento atualizada.',
        'activated' => 'Base de conhecimento ativada.',
        'deactivated' => 'Base de conhecimento desativada.',
        'deleted' => 'Base de conhecimento removida.',
        'indexing' => 'A base está sendo indexada para uso pela IA.',
        'invalid_association' => 'Não é possível associar bases de conhecimento de outra clínica.',
    ],
    'guardrail' => [
        'created' => 'Guardrail criado.',
        'updated' => 'Guardrail atualizado.',
        'activated' => 'Guardrail ativado.',
        'deactivated' => 'Guardrail desativado.',
        'deleted' => 'Guardrail removido.',
        'invalid_association' => 'Não é possível associar guardrails de outra clínica.',
    ],
    'matrix' => [
        'updated' => 'Matriz Persona × Canal atualizada.',
        'cross_tenant' => 'Não é possível associar registros de outra clínica.',
    ],
    'conversation' => [
        'paused' => 'IA pausada nesta conversa.',
        'resumed' => 'IA reativada nesta conversa.',
        'already_closed' => 'Não é possível reativar a IA em uma conversa encerrada.',
    ],
    'markdown' => [
        'unsafe_removed' => 'Conteúdo inseguro foi removido do Markdown.',
        'required' => 'O conteúdo é obrigatório.',
    ],
];
