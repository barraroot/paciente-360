<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Privacy\Models\PseudonymizationAudit;
use App\Domain\Privacy\Models\PseudonymizationAuditMode;
use App\Domain\Privacy\Services\PseudonymizationAuditor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * **T076 (Fase 8 — Lote A US-13.3)** — Painel de auditoria de pseudonimização.
 *
 * Exibido APENAS no Super Admin panel (Filament). Lista as últimas auditorias
 * (estáticas + runtime) com status compliant/non_compliant e botão para
 * disparar nova auditoria estática ad-hoc.
 *
 * Cobertura: AC-13.3.5 — painel visualiza relatório de cobertura de
 * pseudonimização (eventos × campos × status).
 */
class PseudonymizationAuditReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Auditoria de Pseudonimização';

    protected static ?string $title = 'Auditoria de Pseudonimização (Q29)';

    protected static ?string $navigationGroup = 'Privacidade & LGPD';

    protected string $view = 'filament.pages.pseudonymization-audit-report';

    protected static ?string $slug = 'pseudonymization-audit';

    /**
     * @return list<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('run-static-audit')
                ->label('Rodar auditoria estática')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Disparar auditoria estática agora?')
                ->modalDescription('Vai aplicar reflection sobre os eventos listados em config(finalization.ai_consumed_events) e registrar o resultado.')
                ->action(function (): void {
                    /** @var PseudonymizationAuditor $auditor */
                    $auditor = app(PseudonymizationAuditor::class);
                    $audit = $auditor->runStaticReflection(auditedByUserId: auth()->id());

                    Notification::make()
                        ->title($audit->isCompliant() ? 'Auditoria estática — COMPLIANT' : 'Auditoria estática — '.$audit->non_conformant_events.' não-conformidade(s)')
                        ->body("Escaneados: {$audit->total_events_scanned} | Findings: {$audit->non_conformant_events}")
                        ->{$audit->isCompliant() ? 'success' : 'danger'}()
                        ->send();
                }),
        ];
    }

    /**
     * @return array{audits: \Illuminate\Database\Eloquent\Collection<int, PseudonymizationAudit>, latest_static: PseudonymizationAudit|null, latest_replay: PseudonymizationAudit|null}
     */
    public function getViewData(): array
    {
        $audits = PseudonymizationAudit::query()
            ->orderByDesc('audited_at')
            ->limit(50)
            ->get();

        $latestStatic = PseudonymizationAudit::query()
            ->byMode(PseudonymizationAuditMode::StaticReflection)
            ->orderByDesc('audited_at')
            ->first();

        $latestReplay = PseudonymizationAudit::query()
            ->byMode(PseudonymizationAuditMode::RuntimeReplay)
            ->orderByDesc('audited_at')
            ->first();

        return [
            'audits' => $audits,
            'latest_static' => $latestStatic,
            'latest_replay' => $latestReplay,
        ];
    }
}
