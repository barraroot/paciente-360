<?php

namespace App\Http\Middleware;

use App\Domain\Messaging\Widget\Services\WidgetAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * T215 — Middleware de validação de Origin para endpoints públicos do widget.
 *
 * Valida o header Origin da request contra a whitelist `allowed_origins`
 * do WebWidgetConfig. Extrai a public_key do path da rota.
 *
 * Whitelist vazia → aceita qualquer origin (com aviso UX, mas backend permite).
 * Origin ausente → aceita (requisição server-side ou mesmo domínio).
 * Origin inválida → 403.
 *
 * Bundle (.js) é excluído deste middleware (pode ser servido cross-origin livremente).
 *
 * @see WidgetAuthService::validateOrigin()
 * @see specs/003-omnichannel-inbox/spec.md AC-4.3.6 NC-10.b
 */
class ValidateWidgetOrigin
{
    public function __construct(
        private readonly WidgetAuthService $widgetAuthService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $publicKey = $request->route('publicKey');

        if ($publicKey === null) {
            return response()->json(['error' => 'bad_request'], 400);
        }

        $config = $this->widgetAuthService->resolveConfigByPublicKey((string) $publicKey);

        if ($config === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $origin = $request->header('Origin');
        $allowedOrigins = is_array($config->allowed_origins) ? $config->allowed_origins : [];

        if (! $this->widgetAuthService->validateOrigin($allowedOrigins, $origin)) {
            return response()->json([
                'error' => 'origin_not_allowed',
                'message' => 'Origin not in allowed list',
            ], 403);
        }

        // Bind resolved config to request for controller use
        $request->attributes->set('widget_config', $config);

        return $next($request);
    }
}
