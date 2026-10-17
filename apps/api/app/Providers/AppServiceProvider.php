<?php

namespace App\Providers;

use App\Models\AdminUserModel;
use App\Models\PersonalAccessTokenModel;
use App\Services\AdminUserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum 令牌表加了 admin_ 前缀，需替换默认模型
        Sanctum::usePersonalAccessTokenModel(PersonalAccessTokenModel::class);

        // super 角色放行一切权限检查：与 /auth/codes 对 super 返回全量权限保持一致
        Gate::before(function ($user) {
            return $user instanceof AdminUserModel
                && $user->hasRole(AdminUserService::SUPER_ROLE)
                    ? true
                    : null;
        });

        // 登录限流：按 username + IP 计数（叠加在失败锁定之上）
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('username').'|'.$request->ip());
        });
    }
}
