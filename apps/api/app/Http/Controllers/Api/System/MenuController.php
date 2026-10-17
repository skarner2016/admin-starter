<?php

namespace App\Http\Controllers\Api\System;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\MenuRequest;
use App\Http\Resources\SystemMenuResource;
use App\Models\MenuModel;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly MenuService $menus) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->menus->tree());
    }

    public function nameExists(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->menus->nameExists(
                (string) $request->query('name', ''),
                $this->queryId($request),
            ),
        );
    }

    public function pathExists(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->menus->pathExists(
                (string) $request->query('path', ''),
                $this->queryId($request),
            ),
        );
    }

    public function store(MenuRequest $request): JsonResponse
    {
        $menu = $this->menus->create($this->payload($request));

        return $this->successResponse((new SystemMenuResource($menu))->toArray($request));
    }

    public function update(MenuRequest $request, MenuModel $menu): JsonResponse
    {
        $menu = $this->menus->update($menu, $this->payload($request));

        return $this->successResponse((new SystemMenuResource($menu))->toArray($request));
    }

    public function destroy(MenuModel $menu): JsonResponse
    {
        if ($this->menus->hasChildren($menu)) {
            return $this->errorResponse('存在子菜单，请先删除子菜单。');
        }

        $this->menus->delete($menu);

        return $this->successResponse('');
    }

    private function payload(MenuRequest $request): array
    {
        $data = $request->validated();

        return [
            'pid' => (int) ($data['pid'] ?? 0),
            'name' => $data['name'],
            'path' => $data['path'] ?? null,
            'component' => $data['component'] ?? null,
            'redirect' => $data['redirect'] ?? null,
            'type' => $data['type'],
            'auth_code' => $data['authCode'] ?? null,
            'status' => (int) $data['status'],
            'meta' => $data['meta'] ?? null,
        ];
    }

    private function queryId(Request $request): ?int
    {
        $value = $request->query('id');

        return is_numeric($value) ? (int) $value : null;
    }
}
