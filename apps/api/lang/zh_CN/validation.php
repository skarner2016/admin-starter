<?php

/*
|--------------------------------------------------------------------------
| 校验消息（简体中文）
|--------------------------------------------------------------------------
|
| 只覆盖本 API 实际会产出的规则；未覆盖的规则会回退到 fallback_locale（en）。
| 字段名由各 FormRequest 的 attributes() 提供，因此 :attribute 会显示中文。
|
*/

return [
    'accepted' => ':attribute 必须接受。',
    'after' => ':attribute 必须晚于 :date。',
    'alpha_dash' => ':attribute 只能包含字母、数字、下划线和短横线。',
    'array' => ':attribute 必须是数组。',
    'before' => ':attribute 必须早于 :date。',
    'between' => [
        'array' => ':attribute 只能有 :min - :max 项。',
        'file' => ':attribute 必须介于 :min - :max KB 之间。',
        'numeric' => ':attribute 必须介于 :min - :max 之间。',
        'string' => ':attribute 必须介于 :min - :max 个字符之间。',
    ],
    'boolean' => ':attribute 必须为布尔值。',
    'confirmed' => ':attribute 两次输入不一致。',
    'date' => ':attribute 不是有效的日期。',
    'date_format' => ':attribute 的格式必须为 :format。',
    'different' => ':attribute 和 :other 必须不同。',
    'digits' => ':attribute 必须是 :digits 位数字。',
    'digits_between' => ':attribute 必须是 :min - :max 位数字。',
    'email' => ':attribute 不是有效的邮箱地址。',
    'exists' => '所选 :attribute 无效。',
    'file' => ':attribute 必须是文件。',
    'filled' => ':attribute 不能为空。',
    'image' => ':attribute 必须是图片。',
    'in' => '所选 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'ip' => ':attribute 必须是有效的 IP 地址。',
    'json' => ':attribute 必须是有效的 JSON 字符串。',
    'max' => [
        'array' => ':attribute 最多 :max 项。',
        'file' => ':attribute 不能大于 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'mimes' => ':attribute 必须是 :values 格式的文件。',
    'min' => [
        'array' => ':attribute 至少 :min 项。',
        'file' => ':attribute 不能小于 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 至少 :min 个字符。',
    ],
    'not_in' => '所选 :attribute 无效。',
    'numeric' => ':attribute 必须是数字。',
    'regex' => ':attribute 格式不正确。',
    'required' => ':attribute 不能为空。',
    'required_if' => '当 :other 为 :value 时，:attribute 不能为空。',
    'required_unless' => '除非 :other 为 :values，否则 :attribute 不能为空。',
    'required_with' => '当 :values 存在时，:attribute 不能为空。',
    'same' => ':attribute 和 :other 必须相同。',
    'size' => [
        'array' => ':attribute 只能有 :size 项。',
        'file' => ':attribute 必须为 :size KB。',
        'numeric' => ':attribute 必须等于 :size。',
        'string' => ':attribute 必须为 :size 个字符。',
    ],
    'string' => ':attribute 必须是字符串。',
    'timezone' => ':attribute 必须是有效的时区。',
    'unique' => ':attribute 已存在。',
    'uploaded' => ':attribute 上传失败，请重试。',
    'url' => ':attribute 不是有效的链接。',
    'uuid' => ':attribute 必须是有效的 UUID。',

    'custom' => [],

    // 各 FormRequest 的 attributes() 会按请求覆盖这里的通用映射
    'attributes' => [
        'name' => '名称',
        'password' => '密码',
        'status' => '状态',
    ],
];
