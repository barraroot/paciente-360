<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ai\VoiceCatalogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * **T156 (Fase 18 — US5, FR-037)** — gerencia configurações de voz/TTS do
 * tenant (default_voice_id + tts_enabled).
 *
 * @group Tenant — Voice Settings
 */
final class VoiceSettingsController extends Controller
{
    /**
     * Retorna a config de voz default do tenant.
     *
     * @authenticated
     */
    public function show(): JsonResponse
    {
        Gate::authorize('ai.persona.manage');

        $tenant = app('tenant');

        $voice = $tenant->default_voice_id !== null
            ? VoiceCatalogEntry::query()->find($tenant->default_voice_id)
            : null;

        return response()->json([
            'data' => [
                'default_voice_id' => $tenant->default_voice_id,
                'tts_enabled' => (bool) ($tenant->tts_enabled ?? true),
                'default_voice' => $voice !== null ? (new VoiceCatalogResource($voice))->toArray(request()) : null,
            ],
        ]);
    }

    /**
     * Atualiza default_voice_id + tts_enabled.
     *
     * @authenticated
     */
    public function update(Request $request): JsonResponse
    {
        Gate::authorize('ai.persona.manage');

        $data = Validator::make($request->all(), [
            'default_voice_id' => ['nullable', 'integer', 'exists:voice_catalog,id'],
            'tts_enabled' => ['sometimes', 'boolean'],
        ])->validate();

        $tenant = app('tenant');
        $tenant->forceFill($data)->save();

        return $this->show();
    }
}
