<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Chưa có nhà cung cấp mô hình nào dùng được.
 *
 * Tách riêng khỏi các lỗi khác vì đây là thứ người dùng tự sửa được: thông điệp
 * phải tới thẳng họ, không bị thay bằng một câu chung chung.
 */
class ProviderNotConfigured extends RuntimeException {}
