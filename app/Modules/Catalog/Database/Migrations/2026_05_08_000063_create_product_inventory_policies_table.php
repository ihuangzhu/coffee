<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_inventory_policies', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('sku_id', 26);
            $table->enum('inventory_track_type',
                ['NONE', 'FINISHED_GOOD', 'RAW_MATERIAL', 'BOTH'])
                ->default('FINISHED_GOOD');
            $table->enum('stock_deduct_mode',
                ['SALE_DEDUCT', 'MANUAL_DEDUCT', 'PRODUCTION_DEDUCT'])
                ->default('MANUAL_DEDUCT');
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('batch_required')->default(false);
            $table->boolean('expiry_required')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->unique('sku_id'); // 一对一
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('product_inventory_policies');
    }
};
