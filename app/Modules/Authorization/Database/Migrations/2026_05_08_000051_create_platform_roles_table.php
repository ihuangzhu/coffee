<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name', 120);
            $table->string('code', 60)->unique();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_roles');
    }
};
