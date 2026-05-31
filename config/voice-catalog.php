<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice Catalog (feature 018, US5 — Q-clarify-4=B)
    |--------------------------------------------------------------------------
    |
    | Catálogo global de vozes TTS curado pelo super-admin via Filament.
    | O resolvedor App\Domain\Ai\Voice\Services\PersonaVoiceResolverService
    | usa a cadeia: Persona.voice_id → tenant.default_voice_id → system default
    | (key abaixo). Esta chave aponta para `voice_catalog.id` ou um slug-like
    | resolvido em runtime; quando NULL, o resolver retorna a primeira voz
    | ativa em pt-BR marcada `is_system_default=true`.
    |
    */

    'system_default_voice_id' => env('VOICE_CATALOG_SYSTEM_DEFAULT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Provider técnico → display vocab (UI helper)
    |--------------------------------------------------------------------------
    |
    | Mapeia o provedor técnico (ElevenLabs etc.) para uma descrição amigável
    | em PT-BR. Usado pelo super-admin no Filament; nunca exposto ao admin
    | de clínica (FR-037c — sem identificador técnico no painel tenant).
    |
    */

    'providers' => [
        'elevenlabs' => 'ElevenLabs',
        'openai_tts' => 'OpenAI TTS',
        'azure_tts' => 'Azure Neural TTS',
        'google_tts' => 'Google Cloud TTS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Atributos visíveis no painel tenant
    |--------------------------------------------------------------------------
    |
    | Enums declarados para o admin escolher coerentemente com a Persona
    | (FR-037c). NÃO inferir voz automaticamente — admin escolhe explícito.
    |
    */

    'genders' => ['f' => 'Feminina', 'm' => 'Masculina', 'neutral' => 'Neutra'],
    'tones' => [
        'acolhedor' => 'Acolhedor',
        'profissional' => 'Profissional',
        'energico' => 'Enérgico',
        'calmo' => 'Calmo',
    ],
    'languages' => [
        'pt-BR' => 'Português (Brasil)',
    ],

];
