<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_inventory_configs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('store_id', 26)->unique();
            $table->boolean('inventory_enabled')->default(true);
            $table->boolean('multi_location_enabled')->default(false);
            $table->string('default_stock_mode', 20)->default('SIMPLE');
            $table->boolean('production_enabled')->default(false);
            $table->boolean('allow_direct_stock_adjustment')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_inventory_configs');
    }
};
