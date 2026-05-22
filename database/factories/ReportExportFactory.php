<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reports\Models\ReportExport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'tipo' => 'executive_dashboard',
            'formato' => 'csv',
            'filters_applied' => ['period' => '30d'],
            'exported_by_user_id' => User::factory()->state(['tenant_id' => $tenant->id]),
            'exported_at' => Carbon::now(),
            'file_path' => null,
            'file_size_bytes' => null,
            'row_count' => null,
        ];
    }
}
