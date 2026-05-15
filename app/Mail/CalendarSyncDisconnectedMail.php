<?php

namespace App\Mail;

use App\Models\Agenda\CalendarSyncAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * T161 — Email transacional de desconexão Google Calendar (R5).
 */
class CalendarSyncDisconnectedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly CalendarSyncAccount $account) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sincronização do Google Calendar foi interrompida',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.agenda.calendar-sync-disconnected',
            with: [
                'professionalName' => $this->account->professional?->name,
                'tenantName' => $this->account->tenant?->name,
                'reconnectUrl' => rtrim((string) config('app.url'), '/').'/agenda/sincronizacao',
            ],
        );
    }
}
