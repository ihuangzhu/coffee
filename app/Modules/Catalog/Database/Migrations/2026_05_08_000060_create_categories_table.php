<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->enum('owner_type', ['TENANT', 'STORE'])->default('TENANT');
            $table->char('owner_store_id', 26)->nullable();
            $table->enum('category_type', ['BUSINESS', 'INVENTORY', 'BOTH']);
            $table->enum('item_type_scope', [
                'SALE_PRODUCT', 'RAW_MATERIAL', 'SEMI_FINISHED',
                'FINISHED_GOOD', 'SERVICE', 'PACKAGE', 'ALL',
            ])->default('ALL');
            $table->char('parent_id', 26)->nullable();
            $table->string('name', 100);
            $table->string('code', 64)->nullable();
            $table->unsignedSmallInteger('level')->default(1);
            $table->string('path', 500)->default('/');
            $table->unsignedInteger('sort_no')->default(0);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['tenant_id', 'category_type', 'status']);
            $table->index(['tenant_id', 'owner_type', 'owner_store_id']);
            $table->index(['parent_id']);
            $table->index(['tenant_id', 'path']);
            $table->unique(['tenant_id', 'code'], 'categories_tenant_code_unique');
            $table->unique(
                ['tenant_id', 'owner_type', 'owner_store_id', 'parent_id', 'name'],
                'categories_tenant_owner_parent_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
