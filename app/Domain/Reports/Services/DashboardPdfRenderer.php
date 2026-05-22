<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * **T253 (Fase 8 — Lote E US-10.1)** — Renderer PDF do dashboard (AC-10.1.5).
 *
 * Usa `barryvdh/laravel-dompdf` (adicionado em T001 ao composer.json).
 * Template Blade em `resources/views/reports/dashboard.blade.php` produz
 * PDF com cabeçalho da clínica + sumário + cards + rodapé com filtros.
 *
 * **DEFERRED**: a integração concreta com Pdf::loadView() requer
 * `composer install` (dompdf). Por enquanto este service expõe
 * `renderHtml()` para validação isolada do template; produção precisa
 * instalar a dependência antes do primeiro export real (config plan.md §2).
 */
final class DashboardPdfRenderer
{
    /**
     * Renderiza o HTML do dashboard (intermediário antes do PDF).
     * Útil para testes sem depender de DOMPDF estar instalado.
     *
     * @param array<string, mixed> $data
     */
    public function renderHtml(Tenant $tenant, array $data): string
    {
        // View pode não existir em ambientes pre-render (slice futuro);
        // fallback simples mantém o teste em execução.
        if (! View::exists('reports.dashboard')) {
            return $this->renderInlineHtml($tenant, $data);
        }

        return View::make('reports.dashboard', [
            'tenant' => $tenant,
            'data' => $data,
            'generated_at' => now()->toIso8601String(),
        ])->render();
    }

    /**
     * Gera o PDF como string binária.
     *
     * **DEFERRED em MVP**: chamada real ao `barryvdh/laravel-dompdf` quando
     * vendor estiver instalado. Por enquanto retorna o HTML pre-renderizado
     * embrulhado em um cabeçalho minimal que sinaliza o status.
     *
     * @param array<string, mixed> $data
     */
    public function renderPdf(Tenant $tenant, array $data): string
    {
        $html = $this->renderHtml($tenant, $data);

        if (class_exists(Pdf::class)) {
            $pdf = Pdf::loadHTML($html);

            return $pdf->output();
        }

        // Fallback dev/test — retorna HTML como bytes (não é PDF real).
        // Slice de polish (T292) regenera quando vendor estiver instalado.
        return "MOCK_PDF_HTML:\n".$html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderInlineHtml(Tenant $tenant, array $data): string
    {
        $tenantName = htmlspecialchars($tenant->name ?? 'Clínica', ENT_QUOTES, 'UTF-8');
        $period = $data['period'] ?? ['start' => '?', 'end' => '?'];
        $leads = $data['leads_by_channel']['value'] ?? 0;
        $conversion = $data['conversion_rate']['value'] ?? 0;
        $noShow = $data['no_show_rate']['value'] ?? 0;
        $revenue = ($data['estimated_revenue']['value'] ?? 0) / 100;

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Executivo — {$tenantName}</title>
    <style>
        body { font-family: sans-serif; padding: 40px; }
        h1 { color: #1e40af; margin-bottom: 8px; }
        .meta { color: #64748b; font-size: 12px; margin-bottom: 24px; }
        .card { display: inline-block; padding: 16px; margin: 8px; border: 1px solid #e2e8f0; border-radius: 8px; min-width: 180px; }
        .card .label { color: #64748b; font-size: 11px; text-transform: uppercase; }
        .card .value { font-size: 24px; font-weight: bold; margin-top: 4px; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 12px; }
    </style>
</head>
<body>
    <h1>{$tenantName} — Dashboard Executivo</h1>
    <p class="meta">Período: {$period['start']} a {$period['end']}</p>

    <div class="card"><div class="label">Leads</div><div class="value">{$leads}</div></div>
    <div class="card"><div class="label">Conversão</div><div class="value">{$conversion}%</div></div>
    <div class="card"><div class="label">No-show</div><div class="value">{$noShow}%</div></div>
    <div class="card"><div class="label">Faturamento</div><div class="value">R\$ {$revenue}</div></div>

    <div class="footer">
        Gerado em: {$this->safeGeneratedAt()} • Paciente360
    </div>
</body>
</html>
HTML;
    }

    private function safeGeneratedAt(): string
    {
        return now()->toIso8601String();
    }
}
