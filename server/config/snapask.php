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

    /*
    |--------------------------------------------------------------------------
    | Ngôn ngữ
    |--------------------------------------------------------------------------
    |
    | Hai thứ tiếng, khai ở một chỗ để middleware, route công khai và ứng dụng
    | desktop cùng đọc một danh sách. Thứ tự trong mảng cũng là thứ tự hiện trên
    | nút chuyển ngôn ngữ.
    |
    | Tiếng Việt không có tiền tố URL vì đó là bản mặc định; tiếng Anh nằm dưới
    | /en để Google lập chỉ mục riêng từng bản.
    |
    */
    'locales' => [
        'vi' => ['name' => 'Tiếng Việt', 'short' => 'VI', 'prefix' => '', 'html' => 'vi'],
        'en' => ['name' => 'English', 'short' => 'EN', 'prefix' => 'en', 'html' => 'en'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bản phát hành cho trang tải về
    |--------------------------------------------------------------------------
    |
    | Trang tải về và DownloadFileController cùng đọc khối này, nên đổi bản mới
    | chỉ sửa một chỗ.
    |
    | `url` để trống thì file được lấy từ đĩa `disk` bên dưới; điền vào thì người
    | tải được chuyển thẳng sang đó, để băng thông không đi qua máy chủ này.
    |
    | `sha256` hiện trên trang tải về cho khách tự đối chiếu — bộ cài chưa ký số
    | thì đây là cách duy nhất để họ biết file không bị đổi giữa đường.
    |
    */
    'releases' => [
        'version' => env('SNAPASK_RELEASE_VERSION', '0.1.0'),
        'released_at' => env('SNAPASK_RELEASE_DATE'),
        'disk' => env('SNAPASK_RELEASE_DISK', 'releases'),

        'builds' => [
            'windows' => [
                'file' => env('SNAPASK_RELEASE_WIN_FILE'),
                'url' => env('SNAPASK_RELEASE_WIN_URL'),
                'size' => (int) env('SNAPASK_RELEASE_WIN_SIZE', 0),
                'sha256' => env('SNAPASK_RELEASE_WIN_SHA256'),
                'min_os' => env('SNAPASK_RELEASE_WIN_MIN_OS', 'Windows 10'),
            ],
            'mac' => [
                'file' => env('SNAPASK_RELEASE_MAC_FILE'),
                'url' => env('SNAPASK_RELEASE_MAC_URL'),
                'size' => (int) env('SNAPASK_RELEASE_MAC_SIZE', 0),
                'sha256' => env('SNAPASK_RELEASE_MAC_SHA256'),
                'min_os' => env('SNAPASK_RELEASE_MAC_MIN_OS', 'macOS 12'),
            ],
        ],
    ],

    'history_limit' => (int) env('SNAPASK_HISTORY_LIMIT', 12),

    'max_question_length' => (int) env('SNAPASK_MAX_QUESTION_LENGTH', 2000),
];
