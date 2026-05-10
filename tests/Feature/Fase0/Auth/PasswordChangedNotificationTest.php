<?php

namespace Tests\Feature\Fase0\Auth;

use App\Models\AuditLog;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests para US-2.3 — Notificação de senha alterada e auditoria (T121).
 *
 * Cobre:
 *  1. Notificação `PasswordChangedNotification` disparada após reset bem-sucedido.
 *  2. AuditLog gravado com action `user.password.reset`.
 *  3. AuditLog gravado com action `user.password.forgot.requested` quando e-mail encontrado.
 *  4. AuditLog não gravado (ou gravado com user_found=false) quando e-mail desconhecido.
 *
 * @see specs/001-fundacao-multitenant/tasks.md — T121
 */
class PasswordChangedNotificationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function forgotUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/auth/password/forgot";
    }

    private function resetUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/auth/password/reset";
    }

    /** @test */
    public function test_password_changed_notification_dispatched_after_reset(): void
    {
        Notification::fake();

        $tenant = $this->createTenant(['slug' => 'clinica-notif-a', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'notif@clinica-notif-a.com',
            'status' => 'active',
        ]);

        $tokenInClaro = bin2hex(random_bytes(32));

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'tenant_id' => $tenant->id,
            'token' => Hash::make($tokenInClaro),
            'created_at' => now(),
        ]);

        $response = $this->postJson(
            $this->resetUrl('clinica-notif-a'),
            [
                'email' => 'notif@clinica-notif-a.com',
                'token' => $tokenInClaro,
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ],
        );

        $response->assertStatus(204);

        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    /** @test */
    public function test_password_reset_emits_audit_event(): void
    {
        Notification::fake();

        $tenant = $this->createTenant(['slug' => 'clinica-notif-b', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'audit@clinica-notif-b.com',
            'status' => 'active',
        ]);

        $tokenInClaro = bin2hex(random_bytes(32));

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'tenant_id' => $tenant->id,
            'token' => Hash::make($tokenInClaro),
            'created_at' => now(),
        ]);

        $this->postJson(
            $this->resetUrl('clinica-notif-b'),
            [
                'email' => 'audit@clinica-notif-b.com',
                'token' => $tokenInClaro,
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ],
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password.reset',
            'user_id' => $user->id,
        ]);

        $log = AuditLog::where('action', 'user.password.reset')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->ip);
    }

    /** @test */
    public function test_forgot_emits_audit_event_when_email_known(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-notif-c', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'known@clinica-notif-c.com',
            'status' => 'active',
        ]);

        $this->postJson(
            $this->forgotUrl('clinica-notif-c'),
            ['email' => 'known@clinica-notif-c.com'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password.forgot.requested',
            'user_id' => $user->id,
        ]);

        $log = AuditLog::where('action', 'user.password.forgot.requested')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($log);

        $payload = $log->payload;
        $this->assertTrue($payload['user_found']);
    }

    /** @test */
    public function test_forgot_emits_audit_with_user_found_false_when_email_unknown(): void
    {
        $this->createTenant(['slug' => 'clinica-notif-d', 'status' => 'active']);

        $this->postJson(
            $this->forgotUrl('clinica-notif-d'),
            ['email' => 'nobody@clinica-notif-d.com'],
        );

        // Decisão arquitetural documentada:
        // Eventos de forgot para e-mail desconhecido SÃO gravados em audit_logs
        // com action='user.password.forgot.requested' e payload.user_found=false
        // para rastreabilidade de tentativas de enumeração (Princípio I — LGPD).
        $log = AuditLog::where('action', 'user.password.forgot.requested')
            ->whereNull('user_id')
            ->first();

        $this->assertNotNull($log, 'AuditLog deve ser gravado mesmo para e-mail desconhecido');

        $payload = $log->payload;
        $this->assertFalse($payload['user_found']);
        $this->assertSame('nobody@clinica-notif-d.com', $payload['email_attempted']);
    }
}
