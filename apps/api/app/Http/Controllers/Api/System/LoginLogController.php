<?php

namespace App\Http\Controllers\Api\System;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Resources\SystemLoginLogResource;
use App\Models\LoginLogModel;
use App\Services\LoginLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly LoginLogService $logs) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->logs->paginate(
            $request->query(),
            $this->page($request),
            $this->pageSize($request),
        );

        $items = $paginator->getCollection()
            ->map(fn (LoginLogModel $log) => (new SystemLoginLogResource($log))->toArray($request))
            ->all();

        return $this->paginatedResponse($items, $paginator->total());
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
