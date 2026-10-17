<?php

namespace App\Http\Controllers\Api\System;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchIdsRequest;
use App\Http\Requests\System\RoleRequest;
use App\Http\Resources\SystemRoleResource;
use App\Models\RoleModel;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly RoleService $roles) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->roles->paginate(
            $request->query(),
            $this->page($request),
            $this->pageSize($request),
        );

        $items = $paginator->getCollection()
            ->map(fn (RoleModel $role) => (new SystemRoleResource($role))->toArray($request))
            ->all();

        return $this->paginatedResponse($items, $paginator->total());
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->validated());

        return $this->successResponse($this->resource($role, $request));
    }

    public function update(RoleRequest $request, RoleModel $role): JsonResponse
    {
        $role = $this->roles->update($role, $request->validated());

        return $this->successResponse($this->resource($role, $request));
    }

    public function destroy(RoleModel $role): JsonResponse
    {
        $reason = $this->roles->deleteFailureReason($role);

        if ($reason !== null) {
            return $this->errorResponse($reason);
        }

        $this->roles->delete($role);

        return $this->successResponse('');
    }

    public function batchDestroy(BatchIdsRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->roles->batchDelete($request->validated()['ids']),
        );
    }

    private function resource(RoleModel $role, Request $request): array
    {
        $role->setAttribute('menu_ids', $this->roles->menuIds($role));

        return (new SystemRoleResource($role))->toArray($request);
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
