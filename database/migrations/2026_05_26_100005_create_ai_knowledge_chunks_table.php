<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice semântico (RAG) das bases de conhecimento. A coluna `embedding`
 * usa o tipo `vector` (pgvector); a dimensão segue config('ai.matricial.embedding_dimension').
 */
return new class extends Migration
{
    public function up(): void
    {
        $dimension = (int) config('ai.matricial.embedding_dimension', 1536);

        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('ai_knowledge_base_id');
            $table->foreign('ai_knowledge_base_id')->references('id')->on('ai_knowledge_bases')->onDelete('cascade');

            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->unsignedInteger('token_count')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'ai_knowledge_base_id']);
        });

        // Coluna vetorial (pgvector) — Blueprint não tem helper nativo.
        DB::statement("ALTER TABLE ai_knowledge_chunks ADD COLUMN embedding vector({$dimension})");

        // Índice HNSW por similaridade de cosseno.
        DB::statement(<<<'SQL'
            CREATE INDEX ai_knowledge_chunks_embedding_hnsw_idx
            ON ai_knowledge_chunks
            USING hnsw (embedding vector_cosine_ops)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};
