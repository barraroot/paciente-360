<?php

namespace App\Http\Controllers\Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Http\Controllers\Controller;
use App\Http\Requests\Widget\UpdateWidgetConfigRequest;
use App\Http\Resources\V1\WidgetConfigResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * T213 — Controller para configuração admin do widget web.
 *
 * Endpoints:
 *  - GET  /api/v1/inbox/widget-configs/{channel_id} → show
 *  - PUT  /api/v1/inbox/widget-configs/{channel_id} → update
 *  - GET  /api/v1/inbox/widget-configs/{channel_id}/snippet → snippet
 *
 * Acesso restrito por auth:sanctum + ability inbox.view / channel.connect.
 */
class WidgetConfigController extends Controller
{
    public function show(Request $request, int $channelId): WidgetConfigResource|JsonResponse
    {
        $tenant = app('tenant');

        $config = WebWidgetConfig::where('tenant_id', $tenant->id)
            ->where('channel_id', $channelId)
            ->first();

        if ($config === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return new WidgetConfigResource($config);
    }

    public function update(UpdateWidgetConfigRequest $request, int $channelId): WidgetConfigResource|JsonResponse
    {
        $tenant = app('tenant');

        $config = WebWidgetConfig::where('tenant_id', $tenant->id)
            ->where('channel_id', $channelId)
            ->first();

        if ($config === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $data = array_filter([
            'appearance' => $request->input('appearance'),
            'initial_message' => $request->has('initial_message') ? $request->input('initial_message') : null,
            'business_hours' => $request->input('business_hours'),
            'outside_hours_behavior' => $request->input('outside_hours_behavior'),
            'outside_hours_message' => $request->has('outside_hours_message') ? $request->input('outside_hours_message') : null,
            'pre_chat_form' => $request->input('pre_chat_form'),
            'allowed_origins' => $request->input('allowed_origins'),
        ], fn ($v) => $v !== null);

        // Merge appearance instead of replacing (partial updates)
        if (isset($data['appearance'])) {
            $current = is_array($config->appearance) ? $config->appearance : [];
            $data['appearance'] = array_merge($current, $data['appearance']);
        }

        // Merge business_hours (partial day updates)
        if (isset($data['business_hours'])) {
            $current = is_array($config->business_hours) ? $config->business_hours : [];
            $data['business_hours'] = array_merge($current, $data['business_hours']);
        }

        $config->update($data);

        return new WidgetConfigResource($config->fresh() ?? $config);
    }

    public function snippet(Request $request, int $channelId): Response|JsonResponse
    {
        $tenant = app('tenant');

        $config = WebWidgetConfig::where('tenant_id', $tenant->id)
            ->where('channel_id', $channelId)
            ->first();

        if ($config === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $domain = config('messaging.widget.public_domain', 'widget.lvh.me');
        $protocol = config('messaging.widget.public_protocol', 'https');
        $src = "{$protocol}://{$domain}/widget/v1/{$config->public_key}.js";

        $script = '<script async src="'.$src.'"></script>';

        return response($script, 200, ['Content-Type' => 'text/plain']);
    }
}
