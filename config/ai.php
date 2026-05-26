<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'anthropic'),
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => env('AI_EMBEDDINGS_PROVIDER', 'openai'),
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IA Matricial (feature 015)
    |--------------------------------------------------------------------------
    |
    | Configurações da camada de IA Matricial. Credenciais dos provedores são
    | globais da plataforma (acima) — nunca input do tenant (FR-002a).
    |
    */

    'matricial' => [
        // Modelo default de geração quando a persona não fixa um (fallback).
        'default_model' => env('AI_MATRICIAL_DEFAULT_MODEL', 'claude-haiku-4-5-20251001'),

        // Embeddings (RAG) — provider em default_for_embeddings.
        'embedding_dimension' => (int) env('AI_EMBEDDING_DIMENSION', 1536),
        'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),

        // Recuperação semântica.
        'rag' => [
            'top_k' => (int) env('AI_RAG_TOP_K', 6),
            'min_similarity' => (float) env('AI_RAG_MIN_SIMILARITY', 0.2),
            'chunk_chars' => (int) env('AI_RAG_CHUNK_CHARS', 1200),
            'chunk_overlap' => (int) env('AI_RAG_CHUNK_OVERLAP', 150),
        ],

        // Gatilho/orquestração.
        'debounce_seconds' => (int) env('AI_DEBOUNCE_SECONDS', 8),
        'confidence_threshold' => (float) env('AI_CONFIDENCE_THRESHOLD', 0.6),
        'queue' => env('AI_QUEUE', 'ai'),

        // Falha do provedor (FR-030c): retry com backoff antes de escalar.
        'retry' => [
            'attempts' => (int) env('AI_RETRY_ATTEMPTS', 3),
            'backoff' => [10, 30, 60], // segundos
        ],

        // Intenções clínicas que NUNCA são auto-enviadas (Princípio III, FR-026).
        'blocked_intents' => [
            'diagnostico',
            'prescricao',
            'posologia',
            'interpretacao_exame',
            'conduta_risco',
        ],
    ],

];
