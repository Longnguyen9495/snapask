<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectorRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:32',
                // Tên công cụ gửi lên mô hình ghép từ slug này, mà giao thức gọi
                // công cụ chỉ nhận chữ, số, gạch dưới và gạch ngang.
                'regex:/^[a-z0-9_-]+$/',
                // Hai gạch dưới liền nhau là dấu ngăn giữa tên dịch vụ và tên công
                // cụ khi gửi lên mô hình, nên slug không được chứa chuỗi này.
                'not_regex:/__/',
                Rule::unique('mcp_connectors', 'slug')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('connector')?->id),
            ],
            // Bắt buộc HTTPS: token của khách đi kèm mọi lời gọi tới địa chỉ này.
            'url' => ['required', 'url:https', 'max:2048'],
            'auth_token' => ['nullable', 'string', 'max:2048'],
            'enabled' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => __('The short code takes lowercase letters, digits, underscores and hyphens only.'),
            'slug.not_regex' => __('The short code cannot contain two underscores in a row.'),
            'slug.unique' => __('You already have a service using this short code.'),
            'url.url' => __('The service address must be a valid https URL.'),
        ];
    }
}
