<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // image 允许 svg，mimes 再收窄到栅格图，避免上传可执行脚本
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => '文件'];
    }
}
