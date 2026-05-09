<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('stock_owner_id', 26);
            $table->string('location_code', 40);
            $table->string('location_name', 80);
            $table->enum('location_type', ['SHELF', 'FREEZER', 'DISPLAY', 'BACKROOM'])
                ->default('SHELF');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('stock_owner_id')->references('id')->on('stock_owners')->cascadeOnDelete();
            $table->unique(['stock_owner_id', 'location_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
