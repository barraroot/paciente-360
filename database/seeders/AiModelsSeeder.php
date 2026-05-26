<?php

namespace Database\Seeders;

use App\Domain\Ai\Model\Models\AiModel;
use Illuminate\Database\Seeder;

/**
 * Catálogo inicial de modelos de IA da plataforma (global, sem tenant).
 * Idempotente por internal_identifier.
 */
class AiModelsSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'name' => 'Claude Haiku 4.5',
                'provider' => 'anthropic',
                'internal_identifier' => 'claude-haiku-4-5-20251001',
                'description' => 'Modelo rápido e econômico para atendimento conversacional.',
                'config_schema' => [
                    'temperature' => ['min' => 0, 'max' => 1, 'default' => 0.5],
                    'max_tokens' => ['min' => 256, 'max' => 4096, 'default' => 1024],
                ],
                'supports_embedding' => false,
                'embedding_dimension' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Claude Sonnet 4.6',
                'provider' => 'anthropic',
                'internal_identifier' => 'claude-sonnet-4-6',
                'description' => 'Modelo de maior capacidade para casos complexos.',
                'config_schema' => [
                    'temperature' => ['min' => 0, 'max' => 1, 'default' => 0.4],
                    'max_tokens' => ['min' => 256, 'max' => 8192, 'default' => 2048],
                ],
                'supports_embedding' => false,
                'embedding_dimension' => null,
                'is_active' => true,
            ],
            [
                'name' => 'GPT-5.4',
                'provider' => 'openai',
                'internal_identifier' => 'gpt-5.4',
                'description' => 'Modelo OpenAI de alta capacidade para atendimento conversacional.',
                'config_schema' => [
                    'temperature' => ['min' => 0, 'max' => 2, 'default' => 1.0],
                    'max_tokens' => ['min' => 256, 'max' => 16384, 'default' => 2048],
                ],
                'supports_embedding' => false,
                'embedding_dimension' => null,
                'is_active' => true,
            ],
            [
                'name' => 'GPT-5.4 Mini',
                'provider' => 'openai',
                'internal_identifier' => 'gpt-5.4-mini',
                'description' => 'Versão econômica e rápida do GPT-5.4 para alto volume.',
                'config_schema' => [
                    'temperature' => ['min' => 0, 'max' => 2, 'default' => 1.0],
                    'max_tokens' => ['min' => 256, 'max' => 8192, 'default' => 1024],
                ],
                'supports_embedding' => false,
                'embedding_dimension' => null,
                'is_active' => true,
            ],
            [
                'name' => 'GPT-5.5',
                'provider' => 'openai',
                'internal_identifier' => 'gpt-5.5',
                'description' => 'Modelo OpenAI mais recente, maior capacidade de raciocínio.',
                'config_schema' => [
                    'temperature' => ['min' => 0, 'max' => 2, 'default' => 1.0],
                    'max_tokens' => ['min' => 256, 'max' => 16384, 'default' => 2048],
                ],
                'supports_embedding' => false,
                'embedding_dimension' => null,
                'is_active' => true,
            ],
            [
                'name' => 'GPT-5.5 Mini',
                'provider' => 'openai',
                'internal_identifier' => 'gpt-5.5-mini',
                'description' => 'Versão econômica e rápida do GPT-5.5 para alto volume.',
                'config_schema' => [
                    'temperature' => ['min' => 0, 'max' => 2, 'default' => 1.0],
                    'max_tokens' => ['min' => 256, 'max' => 8192, 'default' => 1024],
                ],
                'supports_embedding' => false,
                'embedding_dimension' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Text Embedding 3 Small',
                'provider' => 'openai',
                'internal_identifier' => 'text-embedding-3-small',
                'description' => 'Geração de embeddings para recuperação semântica (RAG).',
                'config_schema' => [],
                'supports_embedding' => true,
                'embedding_dimension' => 1536,
                'is_active' => true,
            ],
        ];

        foreach ($models as $model) {
            AiModel::updateOrCreate(
                ['internal_identifier' => $model['internal_identifier']],
                $model,
            );
        }
    }
}
