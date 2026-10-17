<?php

namespace App\Http\Controllers\Api\System;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\DeptRequest;
use App\Http\Resources\SystemDeptResource;
use App\Models\DeptModel;
use App\Services\DeptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeptController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly DeptService $depts) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->depts->tree());
    }

    public function nameExists(Request $request): JsonResponse
    {
        $pid = $request->query('pid');

        return $this->successResponse(
            $this->depts->nameExists(
                (string) $request->query('name', ''),
                is_numeric($pid) ? (int) $pid : 0,
                $this->queryId($request),
            ),
        );
    }

    public function store(DeptRequest $request): JsonResponse
    {
        $dept = $this->depts->create($request->payload());

        return $this->successResponse((new SystemDeptResource($dept))->toArray($request));
    }

    public function update(DeptRequest $request, DeptModel $dept): JsonResponse
    {
        $dept = $this->depts->update($dept, $request->payload());

        return $this->successResponse((new SystemDeptResource($dept))->toArray($request));
    }

    public function destroy(DeptModel $dept): JsonResponse
    {
        if ($this->depts->hasChildren($dept)) {
            return $this->errorResponse('存在下级部门，请先删除下级部门。');
        }

        if ($this->depts->userCount($dept) > 0) {
            return $this->errorResponse('该部门下仍有用户，无法删除。');
        }

        $this->depts->delete($dept);

        return $this->successResponse('');
    }

    private function queryId(Request $request): ?int
    {
        $value = $request->query('id');

        return is_numeric($value) ? (int) $value : null;
    }
}
