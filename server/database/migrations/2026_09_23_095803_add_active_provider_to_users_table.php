<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /*
             * Để trống nghĩa là dùng nhà cung cấp mặc định của hệ thống, và khi
             * đó hạn mức theo gói mới có hiệu lực.
             */
            $table->foreignId('active_provider_id')
                ->nullable()
                ->after('monthly_ask_limit')
                ->constrained('model_providers')
                ->nullOnDelete();

            $table->string('active_model')->nullable()->after('active_provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_provider_id');
            $table->dropColumn('active_model');
        });
    }
};
