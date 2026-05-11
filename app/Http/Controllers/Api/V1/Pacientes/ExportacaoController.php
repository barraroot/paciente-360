<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Events\Paciente\PacientesExportados;
use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Services\Pacientes\ExportacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T193 — Controller de exportação CSV de pacientes (US-3.3).
 *
 * Streaming direto para o cliente sem persistir arquivo no servidor.
 * Após conclusão, dispara `PacientesExportados` para auditoria.
 *
 * @group CRM Pacientes
 */
final class ExportacaoController extends Controller
{
    public function __construct(
        private readonly ExportacaoService $exportacaoService,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('export', Paciente::class);

        $filters = $request->only([
            'q',
            'status',
            'origem',
            'profissional_responsavel_id',
        ]);

        $executor = $request->user();
        $metadados = ['hash' => '', 'contagem' => 0, 'tamanho_bytes' => 0];

        $response = response()->streamDownload(
            function () use ($filters, &$metadados): void {
                $metadados = $this->exportacaoService->stream($filters, fn () => null);
            },
            $this->filename(),
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$this->filename().'"',
            ]
        );

        // Dispara evento de auditoria após streaming
        $response->headers->set('X-Export-Count', '0');

        // Hook pós-streaming — disparo do evento deve acontecer após o response
        // ser enviado. Usamos terminate para isso.
        register_shutdown_function(function () use ($filters, &$metadados): void {
            try {
                if ($metadados['contagem'] > 0 || $metadados['hash'] !== '') {
                    PacientesExportados::dispatch(
                        escopo: $filters,
                        contagem: $metadados['contagem'],
                        arquivoHash: $metadados['hash'],
                        tamanhoBytes: $metadados['tamanho_bytes'],
                    );
                }
            } catch (\Throwable) {
                // Auditoria não pode bloquear o download
            }
        });

        return $response;
    }

    private function filename(): string
    {
        return 'pacientes_'.now()->format('Y-m-d_H-i-s').'.csv';
    }
}
