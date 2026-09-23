<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('base_url');

            // Khoá của khách, mã hoá ở tầng model.
            $table->text('api_key');

            $table->string('api_format', 32);

            /*
             * Danh sách mã mô hình do khách tự khai. Không tự đọc từ máy chủ vì
             * mỗi nhà cung cấp trả danh sách theo một kiểu khác nhau, và nhiều
             * dịch vụ tự dựng thì không có endpoint liệt kê nào cả.
             */
            $table->json('models');

            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_providers');
    }
};
