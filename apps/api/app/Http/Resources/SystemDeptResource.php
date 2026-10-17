<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemDeptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'pid' => (string) $this->pid,
            'name' => $this->name,
            'sort' => (int) $this->sort,
            'status' => (int) $this->status,
            'remark' => $this->remark,
            'createTime' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
