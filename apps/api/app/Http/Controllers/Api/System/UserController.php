<?php

namespace App\Http\Controllers\Api\System;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchIdsRequest;
use App\Http\Requests\System\ResetPasswordRequest;
use App\Http\Requests\System\UserRequest;
use App\Http\Resources\SystemUserResource;
use App\Models\AdminUserModel;
use App\Services\AdminUserManageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly AdminUserManageService $users) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->users->paginate(
            $request->query(),
            $this->page($request),
            $this->pageSize($request),
        );

        $items = $paginator->getCollection()
            ->map(fn (AdminUserModel $user) => (new SystemUserResource($user))->toArray($request))
            ->all();

        return $this->paginatedResponse($items, $paginator->total());
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return $this->successResponse($this->resource($user, $request));
    }

    public function update(UserRequest $request, AdminUserModel $user): JsonResponse
    {
        $user = $this->users->update($user, $request->validated());

        return $this->successResponse($this->resource($user, $request));
    }

    public function destroy(Request $request, AdminUserModel $user): JsonResponse
    {
        $reason = $this->users->deleteFailureReason($user, $request->user());

        if ($reason !== null) {
            return $this->errorResponse($reason);
        }

        $this->users->delete($user);

        return $this->successResponse('');
    }

    public function batchDestroy(BatchIdsRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->users->batchDelete($request->validated()['ids'], $request->user()),
        );
    }

    public function resetPassword(ResetPasswordRequest $request, AdminUserModel $user): JsonResponse
    {
        $this->users->resetPassword($user, $request->validated()['password']);

        return $this->successResponse('');
    }

    private function resource(AdminUserModel $user, Request $request): array
    {
        return (new SystemUserResource($user->loadMissing(['dept', 'roles'])))->toArray($request);
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function pageSize(Request $request): int
    {
        return min(200, max(1, (int) $request->query('pageSize', 20)));
    }
}
