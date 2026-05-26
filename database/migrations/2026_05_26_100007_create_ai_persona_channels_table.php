<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz Persona × Canal. `channel_type` usa os mesmos identificadores do
 * sistema existente (messaging_channels.type): whatsapp | instagram | web.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_persona_channels', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('ai_persona_id');
            $table->foreign('ai_persona_id')->references('id')->on('ai_personas')->onDelete('cascade');

            $table->string('channel_type', 20);
            $table->boolean('is_active')->default(true);

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ai_persona_channels
            ADD CONSTRAINT ai_persona_channels_channel_type_check
            CHECK (channel_type IN ('whatsapp', 'instagram', 'web'))
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ai_persona_channels_unique
            ON ai_persona_channels (tenant_id, ai_persona_id, channel_type)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX ai_persona_channels_tenant_channel_active_idx
            ON ai_persona_channels (tenant_id, channel_type, is_active)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_persona_channels');
    }
};
