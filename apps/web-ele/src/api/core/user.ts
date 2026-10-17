import type { UserInfo } from '@vben/types';

import { requestClient } from '#/api/request';

/**
 * 获取用户信息
 */
export async function getUserInfoApi() {
  return requestClient.get<UserInfo>('/user/info');
}

/**
 * 个人中心：更新自己的资料（昵称 / 头像 / 简介）
 */
export async function updateProfileApi(data: {
  avatar?: string;
  description?: string;
  realName: string;
}) {
  return requestClient.put<UserInfo>('/user/profile', data);
}

/**
 * 个人中心：修改自己的密码（当前会话保留，其它会话失效）
 */
export async function changePasswordApi(data: {
  oldPassword: string;
  password: string;
}) {
  return requestClient.put('/user/password', data);
}
