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
             * Ngôn ngữ người dùng đã chọn, dùng chung cho trang quản trị lẫn ứng
             * dụng desktop: đổi ở một nơi thì nơi kia nhận theo. Để trống nghĩa
             * là chưa chọn bao giờ, khi đó cookie rồi Accept-Language quyết định.
             */
            $table->string('locale', 5)->nullable()->after('active_model');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
