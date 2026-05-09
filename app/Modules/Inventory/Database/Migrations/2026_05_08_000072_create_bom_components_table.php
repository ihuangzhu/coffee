<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_components', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('bom_id', 26);
            $table->char('component_sku_id', 26);
            $table->decimal('consume_qty', 18, 4);
            $table->decimal('loss_rate', 6, 4)->default(0);
            $table->unsignedSmallInteger('sequence_no')->default(0);
            $table->timestamps();

            $table->foreign('bom_id')->references('id')->on('boms')->cascadeOnDelete();
            $table->foreign('component_sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->index(['bom_id', 'sequence_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_components');
    }
};
