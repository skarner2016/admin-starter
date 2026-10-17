<?php

namespace App\Http\Requests\System;

use App\Services\AdminUserService;
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
            // 与创建用户同一套密码策略，避免「创建要强密码、重置却能用弱密码」
            'password' => [
                'required',
                'string',
                'min:'.AdminUserService::PASSWORD_MIN_LENGTH,
                'max:64',
                'regex:'.AdminUserService::PASSWORD_PATTERN,
            ],
        ];
    }

    public function attributes(): array
    {
        return ['password' => '新密码'];
    }
}
