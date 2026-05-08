<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_bindings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('role_id', 26);
            $table->char('tenant_id', 26);
            $table->char('store_id', 26)->nullable();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->char('granted_by', 26)->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();

            $table->index(['user_id', 'tenant_id']);
            $table->index(['tenant_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_bindings');
    }
};
