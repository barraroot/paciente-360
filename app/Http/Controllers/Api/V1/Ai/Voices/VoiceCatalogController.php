<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai\Voices;

use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ai\VoiceCatalogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * **T155 (Fase 18 — US5, FR-037a/c)** — catálogo de vozes TTS para o admin
 * de clínica escolher na Persona.
 *
 * @group IA — Voice Catalog
 */
final class VoiceCatalogController extends Controller
{
    /**
     * Lista vozes ativas disponíveis para o tenant escolher.
     *
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('ai.persona.manage');

        $language = (string) $request->input('language', 'pt-BR');

        $voices = VoiceCatalogEntry::query()
            ->where('language', $language)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return VoiceCatalogResource::collection($voices);
    }
}
