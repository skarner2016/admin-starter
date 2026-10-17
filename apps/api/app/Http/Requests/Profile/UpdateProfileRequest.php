<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'realName' => ['required', 'string', 'max:64'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'realName' => '姓名',
            'avatar' => '头像',
            'description' => '个人简介',
        ];
    }
}
