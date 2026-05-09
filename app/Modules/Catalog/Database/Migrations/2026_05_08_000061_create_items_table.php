<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->enum('owner_type', ['TENANT', 'STORE'])->default('TENANT');
            $table->char('owner_store_id', 26)->nullable();
            $table->enum('item_type', [
                'SALE_PRODUCT', 'RAW_MATERIAL', 'SEMI_FINISHED',
                'FINISHED_GOOD', 'SERVICE', 'PACKAGE',
            ])->default('SALE_PRODUCT');
            $table->string('item_name', 120);
            // 双分类字段（per docs/categories.md 方案 A）
            $table->char('business_category_id', 26)->nullable();
            $table->char('inventory_category_id', 26)->nullable();
            $table->string('unit', 20)->default('PCS');
            $table->boolean('sku_enabled')->default(true);
            $table->boolean('inventory_enabled')->default(true);
            $table->enum('status', ['active', 'off_shelf'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('business_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('inventory_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'item_type']);
            $table->index(['tenant_id', 'business_category_id']);
            $table->index(['tenant_id', 'inventory_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
