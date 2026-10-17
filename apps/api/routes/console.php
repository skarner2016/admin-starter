<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 清理过期令牌，防止 admin_personal_access_tokens 膨胀
Schedule::command('sanctum:prune-expired --hours=24')->daily();
