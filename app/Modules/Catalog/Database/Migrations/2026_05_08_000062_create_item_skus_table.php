<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_skus', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('item_id', 26);
            $table->json('spec_json')->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('sale_price_cents')->default(0);
            $table->unsignedInteger('cost_price_cents')->default(0);
            $table->boolean('inventory_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->index(['item_id']);
            // barcode 在租户内唯一（NULL 不参与）
            $table->unique(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_skus');
    }
};
