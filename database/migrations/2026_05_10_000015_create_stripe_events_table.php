<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `stripe_events` — idempotência de webhooks Stripe (R3).
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 11.
 *
 * Notas:
 *  - PK é o próprio `stripe_event_id` (VARCHAR 255, ex.: `evt_...`).
 *    Insert idempotente via `INSERT ... ON CONFLICT (id) DO NOTHING` garante
 *    que replay de webhook não processa o mesmo evento duas vezes.
 *  - Sem `tenant_id`: eventos Stripe chegam antes de o customer estar
 *    necessariamente associado ao tenant (ex.: `account.updated`, ping).
 *    Correlação ao tenant ocorre durante o processamento via
 *    `stripe_customer_id → tenants.stripe_customer_id`.
 *  - `processed_at` NULL = evento ainda não processado (job pendente ou
 *    falhou após retries). Usado pelo worker de reconciliação.
 *  - `failure_reason` captura a mensagem de exceção do processador para
 *    diagnóstico sem precisar varrer os logs de aplicação.
 *  - Sem `updated_at`: após `processed_at` ser preenchido, o registro não
 *    muda (imutável na prática, mas sem trigger pois pode precisar de
 *    correção manual em last resort).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table): void {
            // PK = stripe_event_id (não BIGSERIAL): identidade vem do Stripe.
            $table->string('id', 255)->primary();

            $table->string('type', 100);
            $table->jsonb('payload');

            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
        });

        // Índice para worker de reconciliação: busca eventos não processados.
        DB::statement(<<<'SQL'
            CREATE INDEX stripe_events_unprocessed_idx
            ON stripe_events (received_at)
            WHERE processed_at IS NULL
        SQL);

        // Índice por tipo de evento para monitoramento e filtros de painel.
        DB::statement(<<<'SQL'
            CREATE INDEX stripe_events_type_idx ON stripe_events (type)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
    }
};
