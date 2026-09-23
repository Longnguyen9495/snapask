<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('plan', 32)->default('free')->after('password');
            $table->unsignedInteger('monthly_ask_limit')->default(50)->after('plan');

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['plan', 'monthly_ask_limit']);
        });
    }
};
