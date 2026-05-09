<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_inventory_configs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->unique();
            $table->boolean('inventory_enabled')->default(true);
            $table->boolean('multi_location_enabled')->default(false);
            $table->boolean('production_enabled')->default(false);
            $table->boolean('purchase_enabled')->default(false);
            $table->boolean('transfer_enabled')->default(false);
            $table->boolean('stocktaking_enabled')->default(true);
            $table->boolean('negative_stock_allowed')->default(false);
            $table->enum('inventory_cost_method', ['FIFO', 'MOVING_AVG', 'STANDARD'])
                ->default('MOVING_AVG');
            $table->boolean('expiry_management_enabled')->default(false);
            $table->boolean('batch_management_enabled')->default(false);
            $table->boolean('auto_deduct_raw_material_enabled')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_inventory_configs');
    }
};
