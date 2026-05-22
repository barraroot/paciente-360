<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Domain\Messaging\Message\Models\Message;
use App\Http\Controllers\Api\V1\Public\Concerns\ResolvesApiPublicTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\MessagePublicResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * **T225 (Fase 8 — Lote D US-11.2)** — Mensagens via API pública (read-only).
 */
class MessagesController extends Controller
{
    use ResolvesApiPublicTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenantId($request);

        return MessagePublicResource::collection(
            Message::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->paginate((int) $request->integer('per_page', 50)),
        );
    }

    public function show(Request $request, Message $message): MessagePublicResource
    {
        if ((int) $message->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }

        return new MessagePublicResource($message);
    }
}
