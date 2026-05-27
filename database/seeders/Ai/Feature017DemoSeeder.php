<?php

declare(strict_types=1);

namespace Database\Seeders\Ai;

use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Feature 017 — DEMO completo para teste: cria a "Clínica Alfa" do zero
 * (tenant + roles clonadas + admin), persona apontando para modelo OpenAI, matriz
 * persona×canal, e delega ao Feature017TestClinicSeeder (work context, agenda,
 * base de conhecimento + embeddings).
 *
 * NÃO provisiona o canal WhatsApp — o pareamento (QR Evolution) é manual no painel.
 * A matriz é por TIPO de canal, então a IA resolve a persona assim que conectar.
 *
 *   vendor/bin/sail artisan db:seed --class="Database\\Seeders\\Ai\\Feature017DemoSeeder"
 */
class Feature017DemoSeeder extends Seeder
{
    private const SLUG = 'clinica-alfa';

    private const ADMIN_EMAIL = 'admin@clinica-alfa.test';

    private const ADMIN_PASSWORD = 'password';

    public function run(): void
    {
        $tenant = $this->ensureTenant();
        $admin = $this->ensureAdmin($tenant);
        $persona = $this->ensurePersona($tenant, $admin);
        $this->ensureMatrix($tenant, $persona);

        // Reaproveita o provisionamento de work context + agenda + KB/embeddings
        // (resolve o tenant clinica-alfa por ser o único na base recém-criada).
        app()->instance('tenant', $tenant);
        $this->callOnce(Feature017TestClinicSeeder::class);

        $this->command?->info('==> Clínica Alfa pronta. Login: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD.' (slug '.self::SLUG.').');
        $this->command?->warn('==> Conecte o WhatsApp (Evolution) por QR no painel — o canal não é semeado.');
    }

    private function ensureTenant(): Tenant
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => self::SLUG],
            [
                'name' => 'Clínica Alfa',
                'cnpj' => '11222333000181',
                'responsible_name' => 'Admin Clínica Alfa',
                'responsible_email' => self::ADMIN_EMAIL,
                'responsible_phone' => '+557999990000',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'plan_id' => null,
                'terms_accepted_at' => now(),
                'terms_version' => '1.0',
                'onboarding_state' => '{}',
            ],
        );

        $this->cloneRoles($tenant);
        $this->command?->info('  ✓ Tenant '.self::SLUG.' (#'.$tenant->id.').');

        return $tenant;
    }

    private function cloneRoles(Tenant $tenant): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $registrar->forgetCachedPermissions();

        $templates = Role::query()->whereNull('tenant_id')->where('name', '!=', 'super-admin')->with('permissions')->get();

        foreach ($templates as $template) {
            $role = Role::query()->firstOrCreate([
                'name' => $template->name,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $role->syncPermissions($template->permissions);
        }

        $registrar->forgetCachedPermissions();
    }

    private function ensureAdmin(Tenant $tenant): User
    {
        $admin = User::query()->firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin Clínica Alfa',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'status' => 'active',
                'email_verified_at' => now(),
                'failed_login_attempts' => 0,
            ],
        );

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $registrar->forgetCachedPermissions();

        if (! $admin->hasRole('admin-clinica')) {
            $admin->assignRole('admin-clinica');
        }

        $this->command?->info('  ✓ Admin '.self::ADMIN_EMAIL.'.');

        return $admin;
    }

    private function ensurePersona(Tenant $tenant, User $admin): AiPersona
    {
        $model = AiModel::query()->where('internal_identifier', 'gpt-5.4')->where('is_active', true)->first()
            ?? AiModel::query()->where('provider', 'openai')->where('is_active', true)->first();

        if ($model === null) {
            $this->command?->warn('  ! Nenhum modelo OpenAI ativo — rode AiModelsSeeder.');
        }

        $persona = AiPersona::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Duda'],
            [
                'ai_model_id' => $model?->id,
                'description' => 'Atendente da Clínica Alfa (feature 017)',
                'markdown_content' => 'Você é a Duda, atendente da Clínica Alfa. Acolha com empatia, qualifique uma pergunta por vez, construa valor antes de falar preço, e use as ferramentas para dados reais (serviços, agenda). Nunca confirme pagamento — encaminhe para um atendente.',
                'tone' => 'acolhedor, caloroso, com emojis (💛 ✨ 😊), frases curtas',
                'objective' => 'Acolher, qualificar e conduzir ao agendamento.',
                'initial_message' => 'Olá, boa tarde 💛 Posso saber qual a sua queixa principal? Assim consigo te ajudar melhor. 😊',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        if (! $persona->is_active) {
            $persona->forceFill(['is_active' => true])->save();
        }

        $this->command?->info('  ✓ Persona "Duda" (modelo '.($model?->provider).'/'.($model?->internal_identifier).').');

        return $persona;
    }

    private function ensureMatrix(Tenant $tenant, AiPersona $persona): void
    {
        foreach (['whatsapp', 'web', 'instagram'] as $type) {
            AiPersonaChannel::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'ai_persona_id' => $persona->id, 'channel_type' => $type],
                ['is_active' => true],
            );
        }

        $this->command?->info('  ✓ Matriz persona×canal (whatsapp/web/instagram ativos).');
    }
}
