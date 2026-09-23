<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nhà cung cấp mô hình
    |--------------------------------------------------------------------------
    |
    | Dùng giao thức OpenAI-compatible để đổi được giữa OpenAI, Qwen-VL, Gemini
    | hay bất kỳ dịch vụ tương thích nào mà không sửa mã. Model bắt buộc phải
    | đọc được ảnh, vì mọi câu hỏi ở đây đều bắt đầu từ một ảnh chụp màn hình.
    |
    | Khoá API chỉ đọc từ môi trường. Tuyệt đối không gửi khoá xuống ứng dụng
    | desktop: gói Electron giải nén được trong vài giây.
    |
    */
    'base_url' => rtrim((string) env('SNAPASK_BASE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/'),

    'api_key' => env('SNAPASK_API_KEY'),

    'model' => env('SNAPASK_MODEL', 'qwen-vl-plus'),

    // 'openai-chat' hoặc 'anthropic-messages'; xem App\Enums\ApiFormat.
    'api_format' => env('SNAPASK_API_FORMAT', 'openai-chat'),

    'timeout' => (int) env('SNAPASK_TIMEOUT', 120),

    'connect_timeout' => (int) env('SNAPASK_CONNECT_TIMEOUT', 10),

    'max_output_tokens' => (int) env('SNAPASK_MAX_OUTPUT_TOKENS', 1500),

    /*
     * Để trống thì không gửi `temperature`. Một số model từ chối cả request khi
     * thấy tham số này, nên mặc định là không gửi.
     */
    'temperature' => env('SNAPASK_TEMPERATURE') !== null ? (float) env('SNAPASK_TEMPERATURE') : null,

    /*
    |--------------------------------------------------------------------------
    | Ảnh chụp
    |--------------------------------------------------------------------------
    */
    'image' => [
        'disk' => env('SNAPASK_IMAGE_DISK', 'local'),

        // Ứng dụng desktop đã thu nhỏ trước khi gửi; trần này chỉ để chặn
        // request bất thường, không phải để cắt ảnh hợp lệ.
        'max_bytes' => (int) env('SNAPASK_IMAGE_MAX_BYTES', 4 * 1024 * 1024),

        // Ảnh màn hình hay chứa dữ liệu nhạy cảm của khách. Giữ càng ngắn càng
        // ít rủi ro; lệnh snapask:prune xoá theo mốc này.
        'retention_days' => (int) env('SNAPASK_IMAGE_RETENTION_DAYS', 14),
    ],

    /*
     * Gắn lại ảnh ở mọi lượt hỏi trong cùng một hội thoại.
     *
     * Bật (mặc định) thì câu hỏi nối tiếp vẫn nhìn được ảnh, đổi lại mỗi lượt
     * tốn thêm phần token của ảnh. Tắt để tiết kiệm, chấp nhận mô hình chỉ còn
     * nhớ ảnh qua lời nó đã tự mô tả ở lượt đầu.
     */
    'resend_image' => (bool) env('SNAPASK_RESEND_IMAGE', true),

    /*
    |--------------------------------------------------------------------------
    | Connector MCP
    |--------------------------------------------------------------------------
    |
    | Dịch vụ của khách được nối vào theo chuẩn Model Context Protocol, qua HTTP.
    | Thời gian chờ để ngắn, vì mỗi lời gọi công cụ nằm ngay trong lúc người dùng
    | đang ngồi đợi câu trả lời.
    |
    */
    'mcp' => [
        'timeout' => (int) env('SNAPASK_MCP_TIMEOUT', 20),
        'connect_timeout' => (int) env('SNAPASK_MCP_CONNECT_TIMEOUT', 5),
        'max_result_chars' => (int) env('SNAPASK_MCP_MAX_RESULT_CHARS', 6000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gói mặc định khi mở tài khoản
    |--------------------------------------------------------------------------
    |
    | Đặt ở đây chứ không để mặc định của cột: khi đổi chính sách gói dùng thử,
    | chỉ sửa một chỗ thay vì phải viết một migration mới.
    |
    */
    'plans' => [
        'default' => [
            'name' => env('SNAPASK_DEFAULT_PLAN', 'free'),
            'monthly_ask_limit' => (int) env('SNAPASK_DEFAULT_ASK_LIMIT', 50),
        ],
    ],

    'history_limit' => (int) env('SNAPASK_HISTORY_LIMIT', 12),

    'max_question_length' => (int) env('SNAPASK_MAX_QUESTION_LENGTH', 2000),
];
