<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // `confirmed` bắt phải có `password_confirmation` khớp, để một lỗi gõ
            // không khoá luôn tài khoản vừa tạo.
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Hãy nhập tên của bạn.',
            'email.unique' => 'Email này đã có tài khoản.',
            'password.confirmed' => 'Hai lần nhập mật khẩu không giống nhau.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
        ];
    }
}
