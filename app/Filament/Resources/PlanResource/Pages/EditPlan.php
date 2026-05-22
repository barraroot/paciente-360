<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Domain\SuperAdmin\Services\PlanVersioningService;
use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use App\Services\Billing\PlanService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Plan $record */
        $updated = app(PlanService::class)->update($record, $data);

        // **T122 (Fase 8 — Lote B US-12.2)** — Edição de plano cria nova versão
        // via snapshot versioning. Tenants existentes ficam na versão anterior
        // (Q12.2.2); apenas novos tenants veem o snapshot novo.
        $snapshot = [
            'code' => $updated->code,
            'name' => $updated->name,
            'description' => $updated->description,
            'base_price_cents' => $updated->base_price_cents,
            'included_professionals' => $updated->included_professionals,
            'included_ai_messages' => $updated->included_ai_messages,
            'overage_price_cents' => $updated->overage_price_cents,
            'max_users' => $updated->max_users,
            'max_channels' => $updated->max_channels,
            'daily_campaign_limit' => $updated->daily_campaign_limit,
            'api_rate_limit_per_minute' => $updated->api_rate_limit_per_minute,
            'webhook_max_endpoints' => $updated->webhook_max_endpoints,
            'stripe_price_id_base' => $updated->stripe_price_id_base,
            'stripe_price_id_overage' => $updated->stripe_price_id_overage,
            'features_enabled' => [],
        ];

        app(PlanVersioningService::class)->createVersion(
            plan: $updated,
            snapshot: $snapshot,
            createdBy: auth()->user(),
        );

        return $updated;
    }
}
