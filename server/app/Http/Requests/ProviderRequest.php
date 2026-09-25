<?php

namespace App\Http\Requests;

use App\Enums\ApiFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderRequest extends FormRequest
{
    /**
     * Mỗi biểu mẫu sửa có túi lỗi riêng, để lỗi của một nhà cung cấp hiện đúng
     * dưới nhà cung cấp đó chứ không lẫn sang biểu mẫu thêm mới.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('provider') !== null) {
            $this->errorBag = 'provider'.$this->route('provider')->id;
        }
    }

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
            // Khi sửa, bỏ trống nghĩa là giữ khoá cũ: khoá không bao giờ được đổ
            // ngược ra biểu mẫu, nên không thể bắt khách gõ lại mỗi lần sửa tên.
            'api_key' => [$this->route('provider') === null ? 'required' : 'nullable', 'string', 'max:2048'],
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
            $this->validator->errors()->add('models', __('Declare at least one model id.'));
            $this->failedValidation($this->validator);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => __('You already have a provider with this name.'),
            'base_url.url' => __('The address must be a valid https URL.'),
            'api_key.required' => __('Enter the API key.'),
            'models.required' => __('Declare at least one model id.'),
        ];
    }
}
