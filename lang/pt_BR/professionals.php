<?php

return [
    'validation' => [
        'council_duplicate' => 'Já existe outro profissional com este conselho.',
        'email_in_other_tenant' => 'Este email já está cadastrado em outra clínica.',
        'email_already_user_requires_confirmation' => 'Este email já pertence a um usuário existente.',
        'user_belongs_to_other_tenant' => 'Este usuário pertence a outra clínica.',
        'user_is_super_admin' => 'Não é possível vincular um super administrador a um profissional.',
        'council_type_other_required' => 'Informe o nome do conselho quando "Outro" estiver selecionado.',
    ],
    'events' => [
        'created' => 'Profissional :name cadastrado',
        'updated' => 'Profissional :name atualizado',
        'deactivated' => 'Profissional :name desativado',
        'activated_by_invitation' => 'Profissional :name ativado via aceite de convite',
    ],
];
