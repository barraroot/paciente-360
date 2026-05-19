<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prescription_id');
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('cascade');
            $table->string('medication_name');
            $table->string('concentration', 100)->nullable();
            $table->string('pharmaceutical_form', 100)->nullable();
            $table->text('posology');
            $table->string('quantity', 100)->nullable();
            $table->string('treatment_duration', 100)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['prescription_id', 'sort_order'], 'idx_prescription_items_prescription');
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_controlled_single_item()
            RETURNS TRIGGER AS $$
            BEGIN
              IF EXISTS (
                SELECT 1
                FROM prescriptions p
                WHERE p.id = NEW.prescription_id
                  AND p.type = 'controlled'
              ) THEN
                IF (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = NEW.prescription_id) > 1 THEN
                  RAISE EXCEPTION 'Controlled prescriptions must have exactly 1 item (Portaria 344/98)';
                END IF;
              END IF;

              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_prescription_items_controlled_singleton
            AFTER INSERT OR UPDATE OF prescription_id ON prescription_items
            FOR EACH ROW EXECUTE FUNCTION enforce_controlled_single_item()
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescription_items_medication_name_trgm
            ON prescription_items USING gin (medication_name gin_trgm_ops)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        DB::statement('DROP FUNCTION IF EXISTS enforce_controlled_single_item() CASCADE');
    }
};
