<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\ImportPacientesRequest;
use App\Http\Resources\V1\ImportacaoResource;
use App\Models\Importacao;
use App\Models\Paciente;
use App\Services\Pacientes\ImportacaoService;
use App\Services\Pacientes\TemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T187 — Controller de importação de pacientes (US-3.3).
 */
final class ImportacaoController extends Controller
{
    public function __construct(
        private readonly ImportacaoService $importacaoService,
        private readonly TemplateGenerator $templateGenerator,
    ) {}

    /**
     * Baixa o template de importação em CSV ou XLSX.
     * GET /pacientes/importacao/template?formato=csv|xlsx
     */
    public function template(Request $request): StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('import', Paciente::class);

        $formato = strtolower($request->query('formato', 'csv'));

        if ($formato === 'xlsx') {
            return $this->templateGenerator->streamXlsx();
        }

        return $this->templateGenerator->streamCsv();
    }

    /**
     * Inicia uma importação assíncrona de pacientes.
     * POST /pacientes/importacao
     */
    public function store(ImportPacientesRequest $request): JsonResponse
    {
        try {
            $importacao = $this->importacaoService->create(
                file: $request->file('arquivo'),
                statusInicial: $request->input('status_inicial'),
                executor: $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            $code = $e->getMessage();

            // Mapeia erros internos para mensagens i18n-friendly
            $mensagens = [
                'arquivo_muito_grande' => __('import.limite_excedido'),
                'formato_nao_suportado' => 'Formato de arquivo não suportado. Use CSV ou XLSX.',
                'cabecalho_sem_coluna_nome' => 'O arquivo não contém a coluna "nome" (obrigatória).',
                'arquivo_muitas_linhas' => 'O arquivo excede o limite de '.config('paciente.import.max_rows', 10000).' linhas.',
                'arquivo_ilegivel' => 'Não foi possível ler o arquivo enviado.',
            ];

            $mensagem = $mensagens[$code] ?? 'Erro ao processar arquivo.';

            return response()->json([
                'errors' => ['arquivo' => [$mensagem]],
            ], 422);
        }

        return (new ImportacaoResource($importacao))
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Consulta o status de uma importação.
     * GET /pacientes/importacao/{id}
     */
    public function show(int $id, Request $request): ImportacaoResource
    {
        Gate::authorize('import', Paciente::class);

        $importacao = Importacao::where('tenant_id', app('tenant')->id)
            ->findOrFail($id);

        return new ImportacaoResource($importacao);
    }
}
