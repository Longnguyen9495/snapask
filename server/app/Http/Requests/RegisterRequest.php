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
            'name.required' => __('Enter your name.'),
            'email.unique' => __('That email already has an account.'),
            'password.confirmed' => __('The two passwords do not match.'),
            'password.min' => __('Password needs at least 8 characters.'),
        ];
    }
}
