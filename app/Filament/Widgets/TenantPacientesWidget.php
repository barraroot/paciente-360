<?php

namespace App\Filament\Widgets;

use App\Models\Paciente;
use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * T272a — Widget Super Admin: contagem de pacientes por tenant.
 *
 * Exibe apenas métricas agregadas (nunca PII). Dados: slug, nome do tenant,
 * status do tenant, total_ativos, total_lead, total_anonimizados.
 *
 * Acessível somente via painel /admin (Super Admin).
 */
class TenantPacientesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Clínica')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status Tenant')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'suspended' => 'danger',
                        'overdue' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_ativos')
                    ->label('Ativos')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_lead')
                    ->label('Leads')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_anonimizados')
                    ->label('Anonimizados')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('total_ativos', 'desc')
            ->paginated(false);
    }

    /**
     * Constrói a query agregada sem expor PII.
     *
     * Usa subqueries por status para evitar múltiplas roundtrips.
     * `withoutGlobalScopes()` ignora o BelongsToTenant scope do Model Paciente.
     */
    private function buildQuery(): Builder
    {
        $ativos = Paciente::withoutGlobalScopes()
            ->whereNull('anonimizado_em')
            ->where('status', 'ativo')
            ->selectRaw('tenant_id, count(*) as cnt')
            ->groupBy('tenant_id')
            ->toBase();

        $leads = Paciente::withoutGlobalScopes()
            ->whereNull('anonimizado_em')
            ->where('status', 'lead')
            ->selectRaw('tenant_id, count(*) as cnt')
            ->groupBy('tenant_id')
            ->toBase();

        $anonimizados = Paciente::withoutGlobalScopes()
            ->whereNotNull('anonimizado_em')
            ->selectRaw('tenant_id, count(*) as cnt')
            ->groupBy('tenant_id')
            ->toBase();

        return Tenant::query()
            ->select([
                'tenants.id',
                'tenants.slug',
                'tenants.name',
                'tenants.status',
                DB::raw('COALESCE(a.cnt, 0) AS total_ativos'),
                DB::raw('COALESCE(l.cnt, 0) AS total_lead'),
                DB::raw('COALESCE(anon.cnt, 0) AS total_anonimizados'),
            ])
            ->leftJoinSub($ativos, 'a', 'a.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($leads, 'l', 'l.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($anonimizados, 'anon', 'anon.tenant_id', '=', 'tenants.id');
    }
}
