<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de permissões Spatie (roles, permissions e pivots),
 * estendidas com `tenant_id` para suporte multi-tenant.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 5.
 *
 * Notas:
 *  - `tenant_id NULLABLE` em `roles` e `permissions`: NULL = papel/permissão
 *    global (Super Admin); NOT NULL = escoped ao tenant.
 *  - UNIQUE composto `(name, guard_name, COALESCE(tenant_id, 0))` permite
 *    papéis homônimos em tenants distintos e resolve o problema de NULL em
 *    índices B-tree (dois NULLs seriam tratados como distintos pelo PG).
 *  - Mecanismo de "template": ao criar um tenant, TenantService clona papéis
 *    NULL para `tenant_id = X`, permitindo customização futura por tenant
 *    sem afetar outros.
 *  - As tabelas pivot (`model_has_roles`, `model_has_permissions`,
 *    `role_has_permissions`) seguem o schema padrão do Spatie. O escopo
 *    de tenant já é garantido pelo `tenant_id` no próprio `roles`.
 *  - `model_type` e `model_id` são polimórficos — permitem aplicar
 *    roles/permissions a User e a outros models futuros.
 *  - Cache do Spatie deve ser invalidado via `app(PermissionRegistrar::class)
 *    ->forgetCachedPermissions()` após operações de atribuição.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        Schema::create($tableNames['permissions'], function (Blueprint $table): void {
            $table->id();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->timestampsTz();

            // Índice principal de lookup do Spatie.
            $table->index(['name', 'guard_name']);
        });

        // UNIQUE composto com COALESCE: mesmo nome de permissão pode existir
        // em tenants distintos sem conflito.
        DB::statement(sprintf(
            'CREATE UNIQUE INDEX permissions_name_guard_tenant_uniq ON %s (name, guard_name, COALESCE(tenant_id, 0))',
            $tableNames['permissions']
        ));

        Schema::create($tableNames['roles'], function (Blueprint $table): void {
            $table->id();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->timestampsTz();

            $table->index(['name', 'guard_name']);
        });

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX roles_name_guard_tenant_uniq ON %s (name, guard_name, COALESCE(tenant_id, 0))',
            $tableNames['roles']
        ));

        // Pivots `model_has_*`: incluem `tenant_id` (team_foreign_key do
        // Spatie em modo teams). Um mesmo User pode ter o mesmo Role em
        // tenants diferentes (mesmo email, tenants distintos), e o Super
        // Admin tem `tenant_id NULL`. Como PostgreSQL não aceita NULL em
        // PRIMARY KEY, usamos UNIQUE INDEX com `COALESCE(tenant_id, 0)`
        // (mesmo padrão dos UNIQUE em `roles`/`permissions`/`users`).
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames): void {
            $table->unsignedBigInteger('permission_id');
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');

            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->index('tenant_id', 'model_has_permissions_tenant_id_index');
        });

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX model_has_permissions_permission_model_type_uniq ON %s (permission_id, model_id, model_type, COALESCE(tenant_id, 0))',
            $tableNames['model_has_permissions']
        ));

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames): void {
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');

            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->index('tenant_id', 'model_has_roles_tenant_id_index');
        });

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX model_has_roles_role_model_type_uniq ON %s (role_id, model_id, model_type, COALESCE(tenant_id, 0))',
            $tableNames['model_has_roles']
        ));

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames): void {
            $table->unsignedBigInteger('permission_id');
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');

            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
