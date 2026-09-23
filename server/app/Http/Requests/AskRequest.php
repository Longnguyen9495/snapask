<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'conversation_id' => [
                'nullable',
                'integer',
                // Chỉ nối tiếp được hội thoại của chính mình. Không ràng buộc
                // user_id ở đây thì đoán id là đọc được ảnh của người khác.
                Rule::exists('conversations', 'id')->where('user_id', $this->user()->id),
            ],
            'question' => ['required', 'string', 'max:'.config('snapask.max_question_length')],
            'image' => ['nullable', 'string', 'starts_with:data:image/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'conversation_id.exists' => 'Không tìm thấy hội thoại này.',
            'question.required' => 'Hãy nhập câu hỏi.',
            'question.max' => 'Câu hỏi quá dài.',
            'image.starts_with' => 'Ảnh gửi lên không đúng định dạng.',
        ];
    }
}
