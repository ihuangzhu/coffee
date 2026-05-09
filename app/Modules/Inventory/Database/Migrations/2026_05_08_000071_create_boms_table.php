<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('output_sku_id', 26);
            $table->decimal('output_qty', 18, 4)->default(1);
            $table->enum('bom_type', ['STANDARD', 'STORE_CUSTOM'])->default('STANDARD');
            $table->char('store_id', 26)->nullable();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('output_sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->index(['tenant_id', 'output_sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boms');
    }
};
