<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin VoiceCatalogEntry
 */
final class VoiceCatalogResource extends JsonResource
{
    /**
     * Feature 018 (US5, FR-037c) — NÃO expõe `provider_voice_id` nem `provider`
     * ao admin de clínica. Identificadores técnicos ficam no super-admin.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'gender' => $this->gender,
            'tone' => $this->tone,
            'language' => $this->language,
            'preview_url' => $this->preview_audio_path !== null
                ? Storage::disk('public')->url($this->preview_audio_path)
                : null,
            'is_system_default' => (bool) $this->is_system_default,
        ];
    }
}
