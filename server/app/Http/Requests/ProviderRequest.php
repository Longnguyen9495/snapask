<?php

namespace App\Http\Requests;

use App\Enums\ApiFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('model_providers', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('provider')?->id),
            ],
            // Bắt buộc HTTPS: khoá của khách đi kèm mọi lời gọi tới địa chỉ này.
            'base_url' => ['required', 'url:https', 'max:2048'],
            'api_key' => ['required', 'string', 'max:2048'],
            'api_format' => ['required', Rule::enum(ApiFormat::class)],

            // Danh sách mã mô hình, mỗi dòng một mã.
            'models' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Tách danh sách mô hình từ ô nhập nhiều dòng.
     *
     * @return array<int, string>
     */
    public function modelList(): array
    {
        return collect(preg_split('/\r?\n/', (string) $this->string('models')))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();
    }

    protected function passedValidation(): void
    {
        if ($this->modelList() === []) {
            $this->validator->errors()->add('models', 'Hãy khai ít nhất một mã mô hình.');
            $this->failedValidation($this->validator);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Bạn đã có một nhà cung cấp trùng tên.',
            'base_url.url' => 'Địa chỉ phải là một URL https hợp lệ.',
            'api_key.required' => 'Hãy nhập khoá API.',
            'models.required' => 'Hãy khai ít nhất một mã mô hình.',
        ];
    }
}
