<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();

            /*
             * Ảnh chụp nằm ngoài cơ sở dữ liệu, chỉ lưu đường dẫn trên disk.
             * `image_expires_at` là mốc để lệnh dọn dẹp xoá file: ảnh màn hình
             * của khách thường chứa dữ liệu nhạy cảm nên không được giữ mãi.
             */
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();
            $table->timestamp('image_expires_at')->nullable()->index();

            $table->string('model')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
