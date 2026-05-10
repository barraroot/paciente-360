<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable de recuperação de senha (US-2.3 — FR-030, FR-033).
 *
 * Enviado pelo `SendPasswordResetEmailJob` quando o usuário solicita
 * a recuperação. Contém o token em claro (único momento em que trafega
 * fora do sistema) embutido na URL de reset direcionada ao subdomínio
 * correto do tenant.
 */
final class ResetPasswordMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.password_reset.email_subject', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        $resetUrl = sprintf(
            'https://%s.%s/reset-password/%s?email=%s',
            $this->tenant->slug,
            config('tenancy.subdomain_suffix'),
            $this->token,
            urlencode($this->user->email),
        );

        return new Content(
            view: 'emails.password-reset',
            with: [
                'userName' => $this->user->name,
                'tenantName' => $this->tenant->name,
                'resetUrl' => $resetUrl,
            ],
        );
    }
}
