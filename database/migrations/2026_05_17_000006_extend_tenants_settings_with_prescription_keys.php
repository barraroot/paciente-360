<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'modules' => [
                'prescriptions' => [
                    'enabled' => false,
                ],
            ],
            'prescriptions' => [
                'retention_years' => 5,
                'pdf_max_size_mb' => 10,
                'common_max_duration_days' => 180,
                'alert_steps_days' => [15, 7, 1],
                'alert_debounce_hours' => 4,
                'signed_url_ttl_minutes' => 15,
                'controlled_max_items' => 1,
                'general_max_items' => 10,
            ],
        ];

        DB::table('tenants')
            ->select(['id', 'settings'])
            ->orderBy('id')
            ->chunkById(100, function ($tenants) use ($defaults): void {
                foreach ($tenants as $tenant) {
                    $settings = $tenant->settings;

                    if (is_string($settings) && $settings !== '') {
                        $settings = json_decode($settings, true);
                    }

                    if (! is_array($settings)) {
                        $settings = [];
                    }

                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update([
                            'settings' => json_encode(array_replace_recursive($defaults, $settings), JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('tenants')
            ->select(['id', 'settings'])
            ->orderBy('id')
            ->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    $settings = $tenant->settings;

                    if (is_string($settings) && $settings !== '') {
                        $settings = json_decode($settings, true);
                    }

                    if (! is_array($settings)) {
                        continue;
                    }

                    unset($settings['prescriptions']);

                    if (isset($settings['modules']) && is_array($settings['modules'])) {
                        unset($settings['modules']['prescriptions']);

                        if ($settings['modules'] === []) {
                            unset($settings['modules']);
                        }
                    }

                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update([
                            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};
