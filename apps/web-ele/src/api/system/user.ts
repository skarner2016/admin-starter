import type { Recordable } from '@vben/types';

import { requestClient } from '#/api/request';

export namespace SystemUserApi {
  export interface SystemUser {
    [key: string]: any;
    avatar?: string;
    createTime?: string;
    /** 所属部门 id，'' 表示未分配 */
    deptId?: string;
    /** 所属部门名称，列表展示用 */
    deptName?: string;
    /** 备注 */
    description?: string;
    id: string;
    /** 姓名 */
    realName: string;
    /** 角色名数组 */
    roles: string[];
    status: 0 | 1;
    /** 登录凭据 */
    username: string;
  }
}

/**
 * 获取用户列表数据（分页）
 */
async function getUserList(params: Recordable<any>) {
  return requestClient.get<{
    items: SystemUserApi.SystemUser[];
    total: number;
  }>('/system/user/list', { params });
}

/**
 * 创建用户
 */
async function createUser(data: Omit<SystemUserApi.SystemUser, 'id'>) {
  return requestClient.post('/system/user', data);
}

/**
 * 更新用户
 */
async function updateUser(
  id: string,
  data: Omit<SystemUserApi.SystemUser, 'id'>,
) {
  return requestClient.put(`/system/user/${id}`, data);
}

/**
 * 删除用户
 */
async function deleteUser(id: string) {
  return requestClient.delete(`/system/user/${id}`);
}

/**
 * 重置密码
 */
async function resetUserPassword(id: string, password: string) {
  return requestClient.put(`/system/user/${id}/password`, { password });
}

export { createUser, deleteUser, getUserList, resetUserPassword, updateUser };
