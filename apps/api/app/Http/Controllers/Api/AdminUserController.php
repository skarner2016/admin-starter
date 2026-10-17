<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\PersonalAccessTokenModel;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly AdminUserService $users) {}

    public function info(Request $request): JsonResponse
    {
        return $this->successResponse(
            (new AdminUserResource($request->user()))->toArray($request),
        );
    }

    /**
     * 个人中心：修改自己的昵称 / 头像 / 简介。
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->users->updateProfile($request->user(), $request->validated());

        return $this->successResponse((new AdminUserResource($user))->toArray($request));
    }

    /**
     * 个人中心：修改自己的密码（当前会话保留，其它会话失效）。
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $keepTokenId = $token instanceof PersonalAccessTokenModel ? (int) $token->id : null;

        $this->users->changePassword(
            $request->user(),
            $request->validated()['password'],
            $keepTokenId,
        );

        return $this->successResponse('');
    }
}
