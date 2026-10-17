<?php

namespace App\Http\Requests\Profile;

use App\Services\AdminUserService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'oldPassword' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Hash::check((string) $value, $this->user()->password)) {
                        $fail('当前密码不正确。');
                    }
                },
            ],
            // 与管理员重置密码共用同一套策略；同时要求新旧密码不同
            'password' => [
                'required',
                'string',
                'min:'.AdminUserService::PASSWORD_MIN_LENGTH,
                'max:64',
                'regex:'.AdminUserService::PASSWORD_PATTERN,
                'different:oldPassword',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'oldPassword' => '当前密码',
            'password' => '新密码',
        ];
    }
}
