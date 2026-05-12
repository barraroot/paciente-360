<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ChannelTemplateResource;
use App\Jobs\Messaging\SyncChannelTemplatesJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * T073 — Controller de templates de canal.
 *
 * Listagem e disparo de sync via Twilio Content API.
 */
class ChannelTemplatesController extends Controller
{
    public function index(Request $request, int $channelId): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $channel = Channel::where('tenant_id', $tenant->id)
            ->findOrFail($channelId);

        Gate::authorize('view', $channel);

        $query = ChannelTemplate::where('channel_id', $channel->id)
            ->where('tenant_id', $tenant->id);

        if ($request->has('status')) {
            $query->where('meta_template_status', $request->input('status'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        return ChannelTemplateResource::collection($query->get());
    }

    public function sync(Request $request, int $channelId): JsonResponse
    {
        $tenant = app('tenant');

        $channel = Channel::where('tenant_id', $tenant->id)
            ->findOrFail($channelId);

        Gate::authorize('update', $channel);

        $jobId = (string) Str::uuid();

        SyncChannelTemplatesJob::dispatch($channel);

        return response()->json([
            'job_id' => $jobId,
            'enqueued_at' => now()->toIso8601String(),
        ], 202);
    }
}
