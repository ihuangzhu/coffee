<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('category_id', 26);
            $table->string('name', 120);
            $table->string('sku_code', 60)->nullable();
            $table->unsignedInteger('sku_base_price_cents')->default(0);
            $table->enum('status', ['active', 'off_shelf'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
            // sku_code 在租户内唯一（NULL 不参与唯一约束）
            $table->unique(['tenant_id', 'sku_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods');
    }
};
