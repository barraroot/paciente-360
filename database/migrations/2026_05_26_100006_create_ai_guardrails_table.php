<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_guardrails', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 40)->nullable();
            $table->text('markdown_content');
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_guardrails');
    }
};
