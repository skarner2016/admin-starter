<?php

namespace App\Services;

use App\Models\LoginLogModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoginLogService
{
    public function paginate(array $filters, int $page, int $pageSize): LengthAwarePaginator
    {
        return LoginLogModel::query()
            ->when(
                $filters['username'] ?? null,
                fn ($query, $username) => $query->where('username', 'like', "%{$username}%"),
            )
            ->when(
                $filters['ip'] ?? null,
                fn ($query, $ip) => $query->where('ip', 'like', "%{$ip}%"),
            )
            ->when(
                isset($filters['success']) && $filters['success'] !== '',
                fn ($query) => $query->where('success', (bool) (int) $filters['success']),
            )
            ->when(
                $this->parseTime($filters['startTime'] ?? null),
                fn ($query, $time) => $query->where('created_at', '>=', $time),
            )
            ->when(
                $this->parseTime($filters['endTime'] ?? null),
                fn ($query, $time) => $query->where('created_at', '<=', $time),
            )
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * 非法时间串直接忽略，避免把脏参数带进 SQL 引起数据库报错。
     */
    private function parseTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
