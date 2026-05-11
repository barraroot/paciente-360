<?php

return [
    'cpf_invalido' => 'CPF inválido.',
    'cpf_ja_cadastrado' => 'Já existe um paciente com este CPF neste tenant.',
    'documento_obrigatorio' => 'Informe CPF ou documento de estrangeiro.',
    'status' => [
        'lead' => 'Lead',
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
        'bloqueado' => 'Bloqueado',
    ],
    'status_transicao_invalida' => 'Transição de status não permitida para o seu perfil.',
    'tag' => [
        'sys_reservado' => 'O prefixo "sys:" é reservado para tags do sistema.',
        'soft_limit' => 'Recomendamos no máximo 10 tags por paciente para manter segmentação clara.',
    ],
    'funil' => [
        'motivo' => [
            'sem_interesse' => 'Sem interesse',
            'sem_retorno' => 'Sem retorno',
            'preco' => 'Preço',
            'outro' => 'Outro',
        ],
        'motivo_obrigatorio' => 'Selecione um motivo para mover o card para esta coluna.',
        'motivo_outro_obrigatorio' => 'Descreva o motivo (mín. 10 caracteres).',
    ],
    'import' => [
        'limite_excedido' => 'Arquivo acima do limite (5 MB ou 10.000 linhas).',
        'formato_invalido' => 'Formato inválido. Use CSV ou XLSX.',
        'sem_permissao' => 'Apenas Admin Clínica pode importar pacientes.',
    ],
    'export' => [
        'sem_permissao' => 'Apenas Admin Clínica pode exportar pacientes.',
    ],
    'mesclagem' => [
        'expirada' => 'A janela de reversão de 30 dias expirou.',
        'ja_revertida' => 'Esta mesclagem já foi revertida.',
    ],
    'anotacao' => [
        'imutavel' => 'Anotações são imutáveis; use o fluxo de retratação.',
        'tipo' => [
            'geral' => 'Geral',
            'clinica' => 'Clínica',
            'comportamental' => 'Comportamental',
            'financeira' => 'Financeira',
        ],
    ],
    'origem' => [
        'site' => 'Site',
        'indicacao' => 'Indicação',
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'telefone' => 'Telefone',
        'presencial' => 'Presencial',
        'outro' => 'Outro',
    ],
];
