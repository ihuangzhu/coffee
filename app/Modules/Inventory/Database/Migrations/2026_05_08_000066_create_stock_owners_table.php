<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_owners', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->enum('owner_type', ['STORE', 'WAREHOUSE', 'PRODUCTION_AREA']);
            $table->char('owner_ref_id', 26);
            $table->string('name', 80);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'owner_type']);
            $table->unique(['tenant_id', 'owner_type', 'owner_ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_owners');
    }
};
