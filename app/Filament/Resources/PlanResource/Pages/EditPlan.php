<?php

namespace App\Filament\Resources\PlanResource\Pages;

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
        return app(PlanService::class)->update($record, $data);
    }
}
