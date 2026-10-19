import type { SystemUserApi } from '#/api/system/user';

/**
 * 用户列表「状态」开关的更新载荷。
 *
 * 后端 `PUT /system/user/{id}` 是全量覆盖（`AdminUserManageService::update()` 里
 * `$data['avatar'] ?? null`），载荷里漏掉哪个可写字段，那个字段就会被清空，
 * 所以必须把列表行里已有的字段全部带上。
 */
function buildUserStatusTogglePayload(
  row: SystemUserApi.SystemUser,
  status: SystemUserApi.SystemUser['status'],
) {
  return {
    // 列表行里没有头像时留空（key 会被 JSON 丢弃，后端落 null），有头像则原样带上
    avatar: row.avatar || undefined,
    deptId: row.deptId ?? '',
    description: row.description ?? null,
    realName: row.realName,
    roles: row.roles ?? [],
    status,
    username: row.username,
  };
}

export { buildUserStatusTogglePayload };
