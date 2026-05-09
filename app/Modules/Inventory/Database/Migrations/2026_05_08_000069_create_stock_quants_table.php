<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_quants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('stock_owner_id', 26);
            $table->char('location_id', 26);
            $table->char('sku_id', 26);
            $table->string('batch_no', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('unit_cost_cents')->default(0);
            $table->decimal('qty', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('stock_owner_id')->references('id')->on('stock_owners')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('stock_locations')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->index(['tenant_id', 'sku_id', 'expiry_date'], 'stock_quants_sku_expiry');
            $table->index(['tenant_id', 'stock_owner_id', 'location_id', 'sku_id'], 'stock_quants_locator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_quants');
    }
};
