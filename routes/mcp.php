<?php

declare(strict_types=1);

use App\Domain\Ai\Mcp\Server\Auth\McpTokenGuard;
use App\Domain\Ai\Mcp\Server\PacienteMcpServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes (Feature 018 — US7, FR-045/046)
|--------------------------------------------------------------------------
|
| Rota HTTP local do servidor MCP da plataforma. Protegida por McpTokenGuard
| (Sanctum PAT com ability `mcp.invoke` + claim `tenant_id`). Em produção
| sob `AI_TOOLS_VIA_MCP=true` (Q2=B — substituição), a IA consome esta rota
| via McpToolBridge (T051) com circuit breaker auto-revert (FR-053b).
|
| Não exposta à internet por default: o serviço `mcp-server` no compose.yaml
| escuta apenas na rede interna (`http://mcp-server:8090`).
|
*/

Mcp::web('/mcp', PacienteMcpServer::class)->middleware([McpTokenGuard::class]);
