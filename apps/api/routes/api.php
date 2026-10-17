<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\System\MenuController as SystemMenuController;
use App\Http\Controllers\Api\System\RoleController as SystemRoleController;
use App\Http\Controllers\Api\System\UserController as SystemUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('codes', [AuthController::class, 'codes'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/info', [AdminUserController::class, 'info']);
    Route::get('menu/all', [MenuController::class, 'all']);

    // 读操作只要求登录；写操作按按钮权限码校验（与前端 v-access:code 同源）
    Route::prefix('system')->group(function () {
        Route::get('menu/list', [SystemMenuController::class, 'index']);
        Route::get('menu/name-exists', [SystemMenuController::class, 'nameExists']);
        Route::get('menu/path-exists', [SystemMenuController::class, 'pathExists']);
        Route::post('menu', [SystemMenuController::class, 'store'])
            ->middleware('permission:System:Menu:Create');
        Route::put('menu/{menu}', [SystemMenuController::class, 'update'])
            ->middleware('permission:System:Menu:Edit');
        Route::delete('menu/{menu}', [SystemMenuController::class, 'destroy'])
            ->middleware('permission:System:Menu:Delete');

        Route::get('role/list', [SystemRoleController::class, 'index']);
        Route::post('role', [SystemRoleController::class, 'store'])
            ->middleware('permission:System:Role:Create');
        Route::put('role/{role}', [SystemRoleController::class, 'update'])
            ->middleware('permission:System:Role:Edit');
        Route::delete('role/{role}', [SystemRoleController::class, 'destroy'])
            ->middleware('permission:System:Role:Delete');

        Route::get('user/list', [SystemUserController::class, 'index']);
        Route::post('user', [SystemUserController::class, 'store'])
            ->middleware('permission:System:User:Create');
        Route::put('user/{user}', [SystemUserController::class, 'update'])
            ->middleware('permission:System:User:Edit');
        Route::delete('user/{user}', [SystemUserController::class, 'destroy'])
            ->middleware('permission:System:User:Delete');
        Route::put('user/{user}/password', [SystemUserController::class, 'resetPassword'])
            ->middleware('permission:System:User:Edit');
    });
});
