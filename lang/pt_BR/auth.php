<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Credenciais inválidas.',
    'password' => 'A senha está incorreta.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',

    'login' => [
        'title' => 'Entrar',
        'email' => 'E-mail',
        'password' => 'Senha',
        'remember' => 'Lembrar de mim',
        'submit' => 'Entrar',
        'forgot_password' => 'Esqueceu a senha?',
        'locked' => 'Sua conta foi bloqueada por excesso de tentativas. Tente em :minutes minutos.',
    ],

    'logout' => [
        'success' => 'Você saiu da conta com sucesso.',
    ],

    'password_reset' => [
        'request_title' => 'Recuperar senha',
        'email_sent' => 'Enviamos um link de recuperação para o seu e-mail.',
        'reset_title' => 'Definir nova senha',
        'success' => 'Senha redefinida. Faça login com a nova senha.',
        'token_invalid' => 'O link de recuperação é inválido ou expirou.',
        'changed_notification' => 'Sua senha foi alterada. Se não foi você, contate o administrador.',
        'email_subject' => 'Recuperação de senha — :tenant',
        'email_greeting' => 'Olá, :name!',
        'email_intro' => 'Recebemos uma solicitação para redefinir a senha da sua conta em :tenant.',
        'email_action' => 'Redefinir senha',
        'email_expiry' => 'Este link é válido por 60 minutos.',
        'email_ignore' => 'Se você não solicitou a recuperação, ignore este e-mail — sua senha permanece inalterada.',
        'changed_subject' => 'Sua senha foi alterada — :tenant',
        'changed_greeting' => 'Olá, :name!',
        'changed_body' => 'Informamos que a senha da sua conta em :tenant foi alterada com sucesso.',
        'changed_not_you' => 'Se você não realizou esta alteração, entre em contato com o administrador imediatamente.',
    ],

];
