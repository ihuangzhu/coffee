<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_txns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 26);
            $table->enum('biz_type', [
                'PURCHASE_IN', 'SALE_OUT', 'RETURN_IN', 'RETURN_OUT',
                'TRANSFER_OUT', 'TRANSFER_IN', 'STOCKTAKE_PROFIT', 'STOCKTAKE_LOSS',
                'PRODUCTION_CONSUME', 'PRODUCTION_OUTPUT', 'ADJUSTMENT', 'DAMAGE_OUT',
            ]);
            $table->string('biz_order_type', 40)->nullable();
            $table->char('biz_order_id', 26)->nullable();
            $table->char('stock_owner_id', 26);
            $table->char('location_id', 26);
            $table->char('sku_id', 26);
            $table->decimal('qty_change', 18, 4);
            $table->unsignedInteger('unit_cost_cents')->nullable();
            $table->integer('amount_cents')->nullable();
            $table->enum('direction', ['IN', 'OUT', 'FREEZE', 'RELEASE']);
            $table->timestamp('occurred_at');
            $table->char('operator_id', 26);
            $table->json('meta_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('stock_owner_id')->references('id')->on('stock_owners')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('stock_locations')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->index(['tenant_id', 'stock_owner_id', 'sku_id', 'occurred_at'], 'stock_txns_locator');
            $table->index(['tenant_id', 'biz_type', 'occurred_at'], 'stock_txns_biz');
            $table->index(['tenant_id', 'biz_order_type', 'biz_order_id'], 'stock_txns_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_txns');
    }
};
