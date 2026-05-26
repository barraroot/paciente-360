<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_personas', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('ai_model_id');
            $table->foreign('ai_model_id')->references('id')->on('ai_models')->onDelete('restrict');

            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->text('markdown_content');
            $table->string('tone', 120)->nullable();
            $table->text('objective')->nullable();
            $table->text('limitations')->nullable();
            $table->text('initial_message')->nullable();
            $table->text('fallback_message')->nullable();
            $table->text('handoff_rules')->nullable();
            $table->jsonb('model_settings')->default('{}');
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE INDEX ai_personas_tenant_active_idx
            ON ai_personas (tenant_id, is_active)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_personas');
    }
};
