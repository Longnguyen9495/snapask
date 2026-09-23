<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_connectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * `slug` là tiền tố đặt trước tên công cụ khi gửi lên mô hình, nên
             * phải duy nhất trong phạm vi một người dùng và chỉ gồm ký tự mà
             * giao thức gọi công cụ chấp nhận.
             */
            $table->string('slug', 32);
            $table->string('name');
            $table->string('url');

            // Token truy cập dịch vụ của khách, mã hoá ở tầng model.
            $table->text('auth_token')->nullable();

            $table->boolean('enabled')->default(true);

            /*
             * Danh sách công cụ đọc được ở lần đồng bộ gần nhất. Hỏi lại máy chủ
             * MCP ở mỗi lượt chat sẽ cộng thêm một vòng mạng vào thời gian chờ
             * của người dùng, nên danh sách được giữ lại ở đây.
             */
            $table->json('tools')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('last_error')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_connectors');
    }
};
