<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            /*
             * Công cụ mô hình đã gọi để dựng nên câu trả lời này. Cần cho việc
             * đối chiếu khi khách báo "AI trả lời sai số liệu": biết nó lấy dữ
             * liệu từ đâu mới truy được lỗi nằm ở mô hình hay ở dịch vụ kia.
             */
            $table->json('tools_used')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn('tools_used');
        });
    }
};
