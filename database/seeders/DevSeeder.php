<?php

namespace Database\Seeders;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Paciente;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Cpf\CpfValidator;
use App\Support\Telefone\TelefoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T065** — Seeder de desenvolvimento. Cria 2 tenants navegáveis em
 *   - http://clinica-alfa.lvh.me  (active, plano starter "contratado")
 *   - http://clinica-beta.lvh.me  (trial, sem assinatura)
 *
 * Senha padrão para todos os usuários: `password123`.
 *
 * Pré-requisitos: `RolesSeeder`, `PlansSeeder`, `SuperAdminSeeder` rodados
 * antes (chamados aqui caso ainda não tenham rodado para garantir
 * idempotência ponta-a-ponta).
 *
 * **Bloqueado em produção**: `RuntimeException` se `APP_ENV=production`
 * (Princípio VII).
 *
 * Ao criar cada tenant, clona as roles template (tenant_id NULL) para
 * `tenant_id = X` e copia as permissions atribuídas a cada role template.
 */
class DevSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevSeeder não pode rodar em produção.');
        }

        // Garante baseline: roles, planos, super admin.
        $this->call([
            RolesSeeder::class,
            PlansSeeder::class,
            SuperAdminSeeder::class,
        ]);

        $this->seedTenantAlfa();
        $this->seedTenantBeta();

        // T039 — popular pacientes apenas em clinica-alfa (tenant ativo).
        $this->seedPacientesClinicaAlfa();

        // T279 — criar canais sandbox para clinica-alfa (WhatsApp + Widget).
        $alfaTenant = Tenant::where('slug', 'clinica-alfa')->first();
        if ($alfaTenant !== null) {
            $this->seedMessagingChannelsClinicaAlfa($alfaTenant);
        }

        if (isset($this->command)) {
            $this->command->info('DevSeeder: tenants navegáveis em http://clinica-alfa.lvh.me e http://clinica-beta.lvh.me');
            $this->command->info('Senha padrão de todos os usuários de dev: '.self::PASSWORD);
            $this->command->info('Canais de mensageria (WhatsApp + Widget) criados em clinica-alfa');
        }
    }

    /**
     * Clínica Alfa — tenant ativo com plano starter contratado em modo
     * dev (subscription Stripe fictícia). 5 usuários (um por papel) +
     * 1 profissional vinculado ao usuário `medico`.
     */
    private function seedTenantAlfa(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'clinica-alfa'],
            [
                'name' => 'Clínica Alfa',
                'cnpj' => '12345678000100',
                'responsible_name' => 'Alfa Responsável',
                'responsible_email' => 'admin@clinica-alfa.test',
                'responsible_phone' => '+5511999990001',
                'status' => 'active',
                'trial_ends_at' => null,
                'overdue_since' => null,
                'restrictions_applied_at' => null,
                'plan_id' => null,
                'stripe_customer_id' => null,
                'subdomain_custom' => null,
                'terms_accepted_at' => now(),
                'terms_version' => '2026-05-01',
                'onboarding_state' => json_encode((object) []),
            ]
        );

        $this->cloneTemplateRolesForTenant($tenant->id);

        $starter = DB::table('plans')->where('code', 'starter')->first();

        if ($starter !== null) {
            // Vincula o plano ao tenant para refletir "contratado".
            $tenant->forceFill(['plan_id' => $starter->id])->save();

            DB::table('subscriptions')->updateOrInsert(
                ['stripe_id' => 'sub_dev_alfa'],
                [
                    'tenant_id' => $tenant->id,
                    'type' => 'default',
                    'stripe_status' => 'active',
                    'stripe_price' => $starter->stripe_price_id_base,
                    'quantity' => 1,
                    'plan_id' => $starter->id,
                    'plan_snapshot' => json_encode([
                        'code' => $starter->code,
                        'base_price_cents' => $starter->base_price_cents,
                        'included_professionals' => $starter->included_professionals,
                        'included_ai_messages' => $starter->included_ai_messages,
                        'overage_price_cents' => $starter->overage_price_cents,
                        'max_users' => $starter->max_users,
                        'max_channels' => $starter->max_channels,
                    ]),
                    'professionals_quantity' => 1,
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->startOfMonth()->addMonth(),
                    'trial_ends_at' => null,
                    'ends_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $usersByRole = [
            'admin-clinica' => ['Alfa Admin', 'admin@clinica-alfa.test'],
            'medico' => ['Alfa Médico', 'medico@clinica-alfa.test'],
            'atendente' => ['Alfa Atendente', 'atendente@clinica-alfa.test'],
            'recepcionista' => ['Alfa Recepcionista', 'recepcionista@clinica-alfa.test'],
            'financeiro' => ['Alfa Financeiro', 'financeiro@clinica-alfa.test'],
        ];

        $createdUsers = [];

        foreach ($usersByRole as $roleName => [$name, $email]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );

            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }

            $createdUsers[$roleName] = $user;
        }

        // Profissional vinculado ao médico (esqueleto — fase 4 traz o
        // domínio completo de agenda).
        $medico = $createdUsers['medico'];

        DB::table('professionals')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'user_id' => $medico->id],
            [
                'name' => $medico->name,
                'council_type' => 'CRM',
                'council_number' => '12345',
                'council_state' => 'SP',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]
        );

        $registrar->setPermissionsTeamId(null);
    }

    /**
     * Clínica Beta — tenant em trial, sem assinatura, com 1 admin user.
     */
    private function seedTenantBeta(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'clinica-beta'],
            [
                'name' => 'Clínica Beta',
                'cnpj' => '98765432000111',
                'responsible_name' => 'Beta Responsável',
                'responsible_email' => 'admin@clinica-beta.test',
                'responsible_phone' => '+5511999990002',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'overdue_since' => null,
                'restrictions_applied_at' => null,
                'plan_id' => null,
                'stripe_customer_id' => null,
                'subdomain_custom' => null,
                'terms_accepted_at' => now(),
                'terms_version' => '2026-05-01',
                'onboarding_state' => json_encode((object) []),
            ]
        );

        $this->cloneTemplateRolesForTenant($tenant->id);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $admin = User::firstOrCreate(
            ['email' => 'admin@clinica-beta.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Beta Admin',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $admin->hasRole('admin-clinica')) {
            $admin->assignRole('admin-clinica');
        }

        $registrar->setPermissionsTeamId(null);
    }

    /**
     * Clona as roles e permissions template (tenant_id NULL, exceto
     * `super-admin`) para o tenant indicado, preservando a relação
     * role→permissions.
     *
     * Idempotente: `firstOrCreate` em roles/permissions; `syncPermissions`
     * para o set de permissions.
     */
    private function cloneTemplateRolesForTenant(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $templates = Role::query()
            ->whereNull('tenant_id')
            ->where('name', '!=', 'super-admin')
            ->with('permissions')
            ->get();

        foreach ($templates as $template) {
            $registrar->setPermissionsTeamId($tenantId);

            $tenantRole = Role::firstOrCreate([
                'name' => $template->name,
                'guard_name' => $template->guard_name,
                'tenant_id' => $tenantId,
            ]);

            // Permissions ficam globais (tenant_id NULL) nesta fase —
            // basta vincular as mesmas instâncias do template.
            $permissionIds = $template->permissions->pluck('id')->all();

            if ($permissionIds === []) {
                continue;
            }

            $permissions = Permission::query()
                ->whereIn('id', $permissionIds)
                ->get();

            $tenantRole->syncPermissions($permissions);
        }

        $registrar->setPermissionsTeamId(null);
    }

    /**
     * T039 — Popula 30 pacientes em `clinica-alfa` para navegação manual em dev.
     *
     * Distribuição (atende SC-005 do spec — variedade representativa):
     *   - 10 `lead`
     *   - 15 `ativo`
     *   - 3  `inativo`
     *   - 2  `bloqueado`
     *
     * 5 pacientes recebem `profissional_responsavel_id` setado (médico do tenant).
     *
     * Idempotente: usa `Paciente::firstOrCreate(['cpf', 'tenant_id'], ...)` —
     * roda múltiplas vezes sem duplicar.
     */
    private function seedPacientesClinicaAlfa(): void
    {
        $tenant = Tenant::where('slug', 'clinica-alfa')->first();
        if ($tenant === null) {
            return;
        }

        $medico = User::where('email', 'medico@clinica-alfa.test')->first();
        $profissionalId = DB::table('professionals')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $medico?->id)
            ->value('id');

        // Bind do tenant para que `BelongsToTenant::creating` auto-popule
        // `tenant_id` se necessário; também isolamos o escopo correto.
        app()->instance('tenant', $tenant);

        // Distribuição fixa (não-aleatória) para reproducibilidade do seed.
        $pacientes = self::dadosPacientesClinicaAlfa($tenant->id, $profissionalId !== null ? (int) $profissionalId : null);

        foreach ($pacientes as $dados) {
            Paciente::firstOrCreate(
                ['tenant_id' => $dados['tenant_id'], 'cpf' => $dados['cpf']],
                $dados
            );
        }
    }

    /**
     * Resolve pluralmente o dataset de 30 pacientes (determinístico).
     *
     * @return list<array<string, mixed>>
     */
    private static function dadosPacientesClinicaAlfa(int $tenantId, ?int $profissionalId): array
    {
        // Listas determinísticas (sem faker) — reproducibilidade total.
        $nomes = [
            'Ana Beatriz Souza', 'Bruno Carvalho Lima', 'Carla Mendes Ribeiro', 'Daniel Ferreira Costa',
            'Eduarda Almeida Castro', 'Felipe Rocha Pinheiro', 'Gabriela Cardoso Nunes', 'Hugo Tavares Moura',
            'Isabela Pereira Dias', 'João Vinícius Borges', 'Karina Lopes Andrade', 'Lucas Rezende Vieira',
            'Mariana Freitas Sales', 'Nicolas Barreto Cruz', 'Olívia Cunha Bittencourt', 'Pedro Henrique Galvão',
            'Quitéria Sampaio Lima', 'Rafael Teixeira Macedo', 'Sabrina Maciel Aragão', 'Tiago Pacheco Brandão',
            'Úrsula Antunes Vasconcelos', 'Vinícius Cordeiro Camargo', 'Wanda Siqueira Furtado',
            'Xavier Bittencourt Prado', 'Yasmin Resende Falcão', 'Zélia Borba Cavalcanti',
            'Aline Drummond Vieira', 'Bernardo Pádua Salles', 'Cecília Monteiro Caldas', 'Diogo Aragão Tristão',
        ];

        $statusMap = array_merge(
            array_fill(0, 10, 'lead'),
            array_fill(0, 15, 'ativo'),
            array_fill(0, 3, 'inativo'),
            array_fill(0, 2, 'bloqueado'),
        );

        $origens = ['site', 'indicacao', 'whatsapp', 'instagram', 'telefone', 'presencial', 'outro'];

        $pacientes = [];

        foreach ($nomes as $i => $nome) {
            // CPF determinístico: usa o índice como base e calcula DV.
            $cpf = self::gerarCpfDeterministico($i);

            // Telefone móvel determinístico.
            $telefone = TelefoneNormalizer::normalize(
                sprintf('31 9%04d%04d', 1000 + $i, 1000 + ($i * 7) % 9000)
            );

            $pacientes[] = [
                'tenant_id' => $tenantId,
                'nome' => $nome,
                'cpf' => CpfValidator::format($cpf),
                'data_nascimento' => sprintf('19%02d-%02d-%02d', 60 + ($i % 35), 1 + ($i % 12), 1 + ($i % 28)),
                'telefone_primario' => $telefone,
                'telefones_secundarios' => [],
                'email' => sprintf('paciente.%02d@clinica-alfa.test', $i + 1),
                'endereco' => null,
                'status' => $statusMap[$i],
                'origem' => $origens[$i % count($origens)],
                'origem_detalhe' => null,
                'origem_origem' => 'manual',
                'profissional_responsavel_id' => $i < 5 ? $profissionalId : null,
                'convenio_principal_id' => null,
                'funil_coluna_atual_id' => null,
                'funil_posicao' => null,
            ];
        }

        return $pacientes;
    }

    /**
     * Gera um CPF válido determinístico a partir de um seed inteiro.
     * Pega os 9 primeiros dígitos baseando-se no seed e calcula os DVs.
     */
    private static function gerarCpfDeterministico(int $seed): string
    {
        // Construir 9 dígitos a partir do seed (offset para fugir de "todos iguais").
        $base = sprintf('%09d', 100000000 + ($seed * 12345));

        // Calcular DV1
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $base[$i]) * (10 - $i);
        }
        $dv1 = $sum % 11;
        $dv1 = $dv1 < 2 ? 0 : 11 - $dv1;

        // Calcular DV2
        $withDv1 = $base.$dv1;
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $withDv1[$i]) * (11 - $i);
        }
        $dv2 = $sum % 11;
        $dv2 = $dv2 < 2 ? 0 : 11 - $dv2;

        $cpf = $withDv1.$dv2;

        // Em caso raro de todos iguais, regenerar com offset.
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return self::gerarCpfDeterministico($seed + 1000);
        }

        return $cpf;
    }

    /**
     * T279 — Cria 2 canais sandbox em `clinica-alfa` para testes manuais:
     *   1. WhatsApp via Twilio (sandbox)
     *   2. Widget Web embutível
     *
     * Idempotente: `->firstOrCreate()` com `tenant_id + type` para evitar duplicações.
     * Credenciais do Twilio lidas do `.env` ou preenchidas com valores dev.
     *
     * Nota: Em produção, canais são conectados via UI de admin ou API.
     * Este seeder é apenas para quickstart local e CI.
     */
    private function seedMessagingChannelsClinicaAlfa(Tenant $tenant): void
    {
        // Skip se já existem canais
        if (Channel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        // Canal WhatsApp Sandbox (Twilio)
        Channel::factory()
            ->whatsapp()
            ->forTenant($tenant)
            ->create([
                'name' => 'WhatsApp Sandbox',
                'status' => 'ativo',
                'credentials_encrypted' => encrypt([
                    'account_sid' => env('TWILIO_ACCOUNT_SID', 'AC'.str_repeat('a', 32)),
                    'auth_token' => env('TWILIO_AUTH_TOKEN', 'dev-token-sandbox'),
                    'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                    'whatsapp_sender' => env('TWILIO_WHATSAPP_FROM_DEFAULT', 'whatsapp:+14155238886'),
                ]),
                'provider_metadata' => [
                    'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                    'whatsapp_sender' => env('TWILIO_WHATSAPP_FROM_DEFAULT', 'whatsapp:+14155238886'),
                    'phone_number_id' => 'dev_pn_'.uniqid(),
                ],
            ]);

        // Canal Web Widget
        $widgetChannel = Channel::factory()
            ->web()
            ->forTenant($tenant)
            ->create([
                'name' => 'Site Principal',
                'status' => 'ativo',
                'provider_metadata' => [
                    'public_key' => bin2hex(random_bytes(32)),
                ],
            ]);

        // Configuração do widget web
        WebWidgetConfig::factory()
            ->forTenant($tenant)
            ->create([
                'channel_id' => $widgetChannel->id,
                'public_key' => $widgetChannel->provider_metadata['public_key'],
                'allowed_origins' => [
                    'http://clinica-alfa.lvh.me',
                    'http://localhost:8000',
                    'http://localhost:3000',
                ],
                'appearance' => [
                    'primary_color' => '#3B82F6',
                    'logo_url' => null,
                    'position' => 'bottom-right',
                    'button_label' => 'Fale conosco',
                ],
                'initial_message' => 'Olá! Como posso ajudar você?',
                'business_hours' => [
                    'monday' => '08:00-18:00',
                    'tuesday' => '08:00-18:00',
                    'wednesday' => '08:00-18:00',
                    'thursday' => '08:00-18:00',
                    'friday' => '08:00-18:00',
                    'saturday' => null,
                    'sunday' => null,
                    'timezone' => 'America/Sao_Paulo',
                ],
                'outside_hours_behavior' => 'fila',
                'pre_chat_form' => 'opcional',
                'outside_hours_message' => null,
            ]);
    }
}
