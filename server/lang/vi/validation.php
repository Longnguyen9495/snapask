<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Thông báo lỗi khi kiểm tra dữ liệu
    |--------------------------------------------------------------------------
    |
    | Chỉ dịch những quy tắc mà ứng dụng này thực sự dùng, xem
    | `app/Http/Requests/*`. Quy tắc nào chưa dịch sẽ tự lùi về tiếng Anh nhờ
    | APP_FALLBACK_LOCALE, nên thiếu một dòng thì người dùng vẫn đọc được.
    |
    | Giọng văn giống phần còn lại của ứng dụng: câu ngắn, bảo thẳng người dùng
    | cần làm gì, không dùng dạng bị động.
    |
    */

    'accepted' => 'Cần đồng ý :attribute.',
    'array' => ':attribute phải là một danh sách.',
    'boolean' => ':attribute chỉ nhận đúng hoặc sai.',
    'confirmed' => 'Hai lần nhập :attribute không giống nhau.',
    'email' => ':attribute không đúng định dạng email.',
    'exists' => ':attribute đã chọn không tồn tại.',
    'file' => ':attribute phải là một tệp.',
    'image' => ':attribute phải là một ảnh.',
    'in' => ':attribute đã chọn không hợp lệ.',
    'integer' => ':attribute phải là số nguyên.',
    'max' => [
        'array' => ':attribute không được quá :max mục.',
        'file' => ':attribute không được nặng quá :max KB.',
        'numeric' => ':attribute không được lớn hơn :max.',
        'string' => ':attribute không được dài quá :max ký tự.',
    ],
    'mimes' => ':attribute phải là tệp định dạng :values.',
    'mimetypes' => ':attribute phải là tệp định dạng :values.',
    'min' => [
        'array' => ':attribute cần ít nhất :min mục.',
        'file' => ':attribute cần nặng ít nhất :min KB.',
        'numeric' => ':attribute cần ít nhất là :min.',
        'string' => ':attribute cần ít nhất :min ký tự.',
    ],
    'numeric' => ':attribute phải là một số.',
    'regex' => ':attribute không đúng định dạng.',
    'required' => 'Hãy nhập :attribute.',
    'starts_with' => ':attribute phải bắt đầu bằng :values.',
    'string' => ':attribute phải là chữ.',
    'unique' => ':attribute này đã có người dùng.',
    'url' => ':attribute phải là một địa chỉ hợp lệ.',

    'password' => [
        'letters' => 'Mật khẩu cần ít nhất một chữ cái.',
        'mixed' => 'Mật khẩu cần cả chữ hoa lẫn chữ thường.',
        'numbers' => 'Mật khẩu cần ít nhất một chữ số.',
        'symbols' => 'Mật khẩu cần ít nhất một ký tự đặc biệt.',
        'uncompromised' => 'Mật khẩu này đã từng lộ trong một vụ rò rỉ dữ liệu. Hãy chọn mật khẩu khác.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tên trường
    |--------------------------------------------------------------------------
    |
    | Thay tên cột bằng chữ người đọc hiểu được, để "Hãy nhập base_url" thành
    | "Hãy nhập Base URL".
    |
    */

    'attributes' => [
        'api_format' => 'định dạng API',
        'api_key' => 'khoá API',
        'auth_token' => 'token truy cập',
        'base_url' => 'Base URL',
        'email' => 'email',
        'image' => 'ảnh',
        'models' => 'danh sách mô hình',
        'name' => 'tên hiển thị',
        'password' => 'mật khẩu',
        'question' => 'câu hỏi',
        'slug' => 'mã rút gọn',
        'url' => 'địa chỉ MCP',
    ],

];
