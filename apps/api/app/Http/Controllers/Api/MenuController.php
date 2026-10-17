<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly MenuService $menus) {}

    public function all(Request $request): JsonResponse
    {
        return $this->successResponse($this->menus->menusForUser($request->user()));
    }
}
