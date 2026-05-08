<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('platform_role_id', 26)->nullable()->after('is_platform_admin');
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['platform_role_id']);
            $table->dropColumn('platform_role_id');
        });
    }
};
