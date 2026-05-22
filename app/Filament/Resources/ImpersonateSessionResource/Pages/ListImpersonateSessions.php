<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImpersonateSessionResource\Pages;

use App\Filament\Resources\ImpersonateSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListImpersonateSessions extends ListRecords
{
    protected static string $resource = ImpersonateSessionResource::class;
}
