import type { Recordable } from '@vben/types';

import { requestClient } from '#/api/request';

export namespace SystemRoleApi {
  export interface SystemRole {
    [key: string]: any;
    /** 创建时间 */
    createTime?: string;
    id: string;
    name: string;
    /** 授权的 menu id 数组（前端菜单树勾选值） */
    permissions: string[];
    remark?: string;
    status: 0 | 1;
  }
}

/**
 * 获取角色列表数据（分页）
 */
async function getRoleList(params: Recordable<any>) {
  return requestClient.get<{ items: SystemRoleApi.SystemRole[]; total: number }>(
    '/system/role/list',
    { params },
  );
}

/**
 * 创建角色
 */
async function createRole(data: Omit<SystemRoleApi.SystemRole, 'id'>) {
  return requestClient.post('/system/role', data);
}

/**
 * 更新角色
 */
async function updateRole(
  id: string,
  data: Omit<SystemRoleApi.SystemRole, 'id'>,
) {
  return requestClient.put(`/system/role/${id}`, data);
}

/**
 * 删除角色
 */
async function deleteRole(id: string) {
  return requestClient.delete(`/system/role/${id}`);
}

export { createRole, deleteRole, getRoleList, updateRole };
