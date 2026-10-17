<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:6', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return ['password' => '新密码'];
    }
}
