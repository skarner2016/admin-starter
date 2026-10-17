<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    use ApiResponseTrait;

    /**
     * 通用图片上传（头像等）。
     *
     * 落盘到 public 磁盘，返回站内相对地址 `/storage/...`：
     * 开发环境由前端 Vite 代理 /storage 到本服务，生产由反向代理指向 public 目录。
     * 需要先执行 `php artisan storage:link`。
     */
    public function store(UploadRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('uploads/'.date('Ymd'), 'public');

        return $this->successResponse([
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
