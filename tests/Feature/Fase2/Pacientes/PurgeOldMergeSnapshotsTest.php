<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\MesclagemPaciente;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurgeOldMergeSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function purge_command_zeros_old_merge_snapshots(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $paciente1 = Paciente::factory()->forTenant($tenant)->create();
        $paciente2 = Paciente::factory()->forTenant($tenant)->create();

        // Mesclagem antiga (35 dias atrás)
        $oldMesclagem = MesclagemPaciente::factory()
            ->forTenant($tenant)
            ->state([
                'paciente_alvo_id' => $paciente1->id,
                'pacientes_origem_ids' => [$paciente2->id],
                'executor_id' => $user->id,
                'reversivel_ate' => now()->subDays(35),
                'snapshot_pre_merge' => [
                    'telefone_primario' => '31999999999',
                    'email' => 'old@example.com',
                ],
            ])
            ->create();

        // Mesclagem recente (15 dias atrás)
        $recentMesclagem = MesclagemPaciente::factory()
            ->forTenant($tenant)
            ->state([
                'paciente_alvo_id' => $paciente1->id,
                'pacientes_origem_ids' => [$paciente2->id],
                'executor_id' => $user->id,
                'reversivel_ate' => now()->subDays(15),
                'snapshot_pre_merge' => [
                    'telefone_primario' => '31988888888',
                    'email' => 'recent@example.com',
                ],
            ])
            ->create();

        // Sanity: ambas têm snapshot
        $this->assertNotEmpty($oldMesclagem->refresh()->snapshot_pre_merge);
        $this->assertNotEmpty($recentMesclagem->refresh()->snapshot_pre_merge);

        // Act: roda o comando de purge
        $this->artisan('app:purge-old-merge-snapshots')->assertSuccessful();

        // Assert: snapshot antigo foi zerado (empty array in JSONB)
        $oldMesclagem->refresh();
        $this->assertEmpty($oldMesclagem->snapshot_pre_merge);

        // Assert: snapshot recente preservado
        $recentMesclagem->refresh();
        $this->assertNotEmpty($recentMesclagem->snapshot_pre_merge);
    }

    #[Test]
    public function purge_command_preserves_metadata(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $paciente1 = Paciente::factory()->forTenant($tenant)->create();
        $paciente2 = Paciente::factory()->forTenant($tenant)->create();

        $mesclagem = MesclagemPaciente::factory()
            ->forTenant($tenant)
            ->state([
                'paciente_alvo_id' => $paciente1->id,
                'pacientes_origem_ids' => [$paciente2->id],
                'executor_id' => $user->id,
                'reversivel_ate' => now()->subDays(35),
                'snapshot_pre_merge' => ['data' => 'value'],
            ])
            ->create();

        // Act
        $this->artisan('app:purge-old-merge-snapshots')->assertSuccessful();

        // Assert: metadata intacta, snapshot zerado
        $mesclagem->refresh();
        $this->assertEmpty($mesclagem->snapshot_pre_merge);
    }
}
