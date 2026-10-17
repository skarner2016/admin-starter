<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponseTrait;

    public function info(Request $request): JsonResponse
    {
        return $this->successResponse(
            (new AdminUserResource($request->user()))->toArray($request),
        );
    }
}
