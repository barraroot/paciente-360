<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Channel\Exceptions\ChannelAlreadyConnectedException;
use App\Domain\Messaging\Channel\Exceptions\ChannelHasActiveConversationsException;
use App\Domain\Messaging\Channel\Exceptions\InvalidCredentialsException;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Services\ChannelService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\ConnectChannelRequest;
use App\Http\Requests\Inbox\ReconnectChannelRequest;
use App\Http\Requests\Inbox\UpdateChannelRequest;
use App\Http\Resources\V1\ChannelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * T072 — Controller CRUD de canais de mensageria.
 *
 * Pipeline: FormRequest → Controller → ChannelService → ChannelResource.
 * Controllers finos — toda lógica no ChannelService.
 *
 * Princípio V — log estruturado: todos os métodos logam tenant_id, user_id, channel_id.
 */
class ChannelsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Channel::class);

        $tenant = app('tenant');

        $channels = Channel::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return ChannelResource::collection($channels);
    }

    public function show(Request $request, int $id): ChannelResource
    {
        $tenant = app('tenant');

        $channel = Channel::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        Gate::authorize('view', $channel);

        return new ChannelResource($channel);
    }

    public function store(ConnectChannelRequest $request, ChannelService $service): JsonResponse
    {
        $tenant = app('tenant');

        try {
            $channel = $service->connect(
                tenantId: $tenant->id,
                type: $request->input('type'),
                name: $request->input('name'),
                credentials: $request->input('credentials'),
                executorId: $request->user()?->id,
            );

            Log::info('channel.connected', [
                'tenant_id' => $tenant->id,
                'user_id' => $request->user()?->id,
                'channel_id' => $channel->id,
                'type' => $channel->type,
            ]);

            return (new ChannelResource($channel))
                ->response()
                ->setStatusCode(201);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => $e->getMessage(),
            ], 422);
        } catch (ChannelAlreadyConnectedException $e) {
            return response()->json([
                'error' => 'channel.already_connected',
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function update(UpdateChannelRequest $request, int $id): ChannelResource
    {
        $tenant = app('tenant');

        $channel = Channel::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        Gate::authorize('update', $channel);

        $channel->update($request->only(['name', 'auto_send_disabled']));

        return new ChannelResource($channel->fresh() ?? $channel);
    }

    public function destroy(Request $request, ChannelService $service, int $id): JsonResponse
    {
        $tenant = app('tenant');

        $channel = Channel::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        Gate::authorize('delete', $channel);

        $force = $request->boolean('force', false);

        try {
            $service->disconnect($channel, $request->user()?->id, $force);

            Log::info('channel.disconnected', [
                'tenant_id' => $tenant->id,
                'user_id' => $request->user()?->id,
                'channel_id' => $channel->id,
            ]);

            return response()->json(null, 204);
        } catch (ChannelHasActiveConversationsException $e) {
            return response()->json([
                'error' => 'channel.has_active_conversations',
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function reconnect(ReconnectChannelRequest $request, ChannelService $service, int $id): ChannelResource
    {
        $tenant = app('tenant');

        $channel = Channel::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        Gate::authorize('reconnect', $channel);

        $channel->update(['status' => 'ativo']);

        return new ChannelResource($channel->fresh() ?? $channel);
    }
}
