<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Channel\Adapters\QrPayload;
use App\Domain\Messaging\Channel\Exceptions\ChannelAlreadyConnectedException;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Services\ChannelService;
use App\Domain\Messaging\Channel\Services\EvolutionInstanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\ConnectEvolutionChannelRequest;
use App\Http\Resources\V1\ChannelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Feature 014 — Conexão de WhatsApp NÃO oficial (Evolution) por QR Code (US2).
 *
 * `connect` cria o canal + a instância no servidor Evolution e devolve o QR.
 * `qr` regenera o código. `connectionState` reconcilia/retorna o estado atual
 * (usado pelo polling do front e pela US3).
 */
class EvolutionConnectionController extends Controller
{
    public function __construct(
        private readonly ChannelService $channels,
        private readonly EvolutionInstanceService $instances,
    ) {}

    public function connect(ConnectEvolutionChannelRequest $request): JsonResponse
    {
        $tenant = app('tenant');

        try {
            $channel = $this->channels->connect(
                tenantId: $tenant->id,
                type: 'whatsapp',
                name: $request->input('name'),
                credentials: [],
                executorId: $request->user()?->id,
                provider: 'evolution',
            );
        } catch (ChannelAlreadyConnectedException $e) {
            return response()->json([
                'error' => 'channel.already_connected',
                'message' => 'Já existe um canal WhatsApp ativo/conectando. Desconecte o atual antes de conectar outro provedor.',
            ], 409);
        }

        $qr = $this->instances->createForChannel($channel);

        Log::info('channel.evolution.connect', [
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
            'channel_id' => $channel->id,
        ]);

        return response()->json([
            'data' => (new ChannelResource($channel->fresh() ?? $channel))->resolve($request),
            'qr' => $this->qrToArray($qr),
        ], 201);
    }

    public function qr(Request $request, int $id): JsonResponse
    {
        $channel = $this->resolveChannel($id);
        Gate::authorize('reconnect', $channel);

        // Regenerar o QR significa retomar o pareamento → status volta a "conectando".
        if ($channel->status !== 'conectando') {
            $channel->update(['status' => 'conectando']);
        }

        return response()->json(['qr' => $this->qrToArray($this->instances->regenerateQr($channel))]);
    }

    public function connectionState(Request $request, int $id): JsonResponse
    {
        $channel = $this->resolveChannel($id);
        Gate::authorize('view', $channel);

        $status = $this->instances->refreshState($channel);

        return response()->json([
            'channel_id' => $channel->id,
            'status' => $status,
        ]);
    }

    private function resolveChannel(int $id): Channel
    {
        $tenant = app('tenant');

        return Channel::where('tenant_id', $tenant->id)
            ->where('provider', 'evolution')
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function qrToArray(?QrPayload $qr): ?array
    {
        if ($qr === null) {
            return null;
        }

        return [
            'base64' => $qr->base64,
            'code' => $qr->code,
            'pairing_code' => $qr->pairingCode,
        ];
    }
}
