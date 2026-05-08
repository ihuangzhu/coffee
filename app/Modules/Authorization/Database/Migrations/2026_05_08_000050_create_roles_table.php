<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->string('name', 120);
            $table->string('code', 60);
            $table->enum('scope', ['tenant', 'store']);
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('code');
            // 不加 UNIQUE(tenant_id, code) —— MySQL InnoDB 中 UNIQUE 视 NULL ≠ NULL，
            // 多份 (NULL, 'TenantAdmin') 不被拒；改由 Service 层 + seeder firstOrCreate 保证唯一
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
