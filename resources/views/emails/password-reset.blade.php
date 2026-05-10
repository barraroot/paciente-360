<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de senha</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px; }
        .btn { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 24px 0; }
        .footer { margin-top: 32px; font-size: 13px; color: #888; border-top: 1px solid #eee; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Recuperação de senha</h2>
        <p>Olá, {{ $userName }}!</p>
        <p>Recebemos uma solicitação para redefinir a senha da sua conta em <strong>{{ $tenantName }}</strong>.</p>
        <p>Clique no botão abaixo para criar uma nova senha:</p>
        <a href="{{ $resetUrl }}" class="btn">Redefinir senha</a>
        <p>Este link é válido por <strong>60 minutos</strong>.</p>
        <p>Se você não solicitou a recuperação de senha, ignore este e-mail — sua senha permanece inalterada.</p>
        <div class="footer">
            <p>{{ $tenantName }} — Paciente360</p>
        </div>
    </div>
</body>
</html>
