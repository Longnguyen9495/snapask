<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dòng chữ khi đặt lại mật khẩu
    |--------------------------------------------------------------------------
    |
    | `sent` và `user` không dùng tới: trang quên mật khẩu trả lời như nhau cho
    | mọi email, để không ai dò được địa chỉ nào đã có tài khoản. Vẫn dịch sẵn
    | phòng khi có chỗ khác gọi tới broker.
    |
    */

    'reset' => 'Mật khẩu đã được đổi.',
    'sent' => 'Chúng tôi đã gửi link đặt lại mật khẩu vào email của bạn.',
    'throttled' => 'Bạn vừa yêu cầu một link. Đợi một phút rồi thử lại.',
    'token' => 'Link này đã hết hạn hoặc đã được dùng. Hãy yêu cầu link mới.',
    'user' => 'Không tìm thấy tài khoản nào dùng email này.',

];
