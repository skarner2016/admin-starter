<?php

/**
 * CORS 配置（Laravel 12 slim 默认不发布此文件，走框架内置默认值：`*` + 不允许凭据）。
 *
 * 本项目的 refresh token 走 HttpOnly Cookie + `withCredentials: true`，
 * 浏览器对「带凭据 + Origin: *」的组合会直接拒绝，所以跨域部署时必须：
 *   1. `supports_credentials` = true；
 *   2. `allowed_origins` 写明确来源，**不能用 `*`**。
 *
 * 开发环境走 Vite 代理属同源，不会触发 CORS；此处默认值仅用于直连后端的场景。
 * 详见 docs/login-module.md §8.4。
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // 多个来源用英文逗号分隔，例：CORS_ALLOWED_ORIGINS=https://admin.example.com
    // 未配置时回退到前端本地开发地址（web-ele 默认端口 5777）
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5777,http://127.0.0.1:5777')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // refresh token 以 Cookie 形式跨域下发，必须允许携带凭据
    'supports_credentials' => true,
];
