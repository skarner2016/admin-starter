import type { Recordable } from '@vben/types';

import { requestClient } from '#/api/request';

export namespace SystemLoginLogApi {
  export interface LoginLog {
    [key: string]: any;
    /** 登录时间 */
    createTime?: string;
    id: string;
    /** 客户端 IP */
    ip?: string;
    /** 失败原因码（invalid_credentials / locked / disabled） */
    reason?: string;
    /** 是否登录成功 */
    success: boolean;
    /** User-Agent */
    userAgent?: string;
    username: string;
  }
}

/**
 * 获取登录日志（分页）
 */
async function getLoginLogList(params: Recordable<any>) {
  return requestClient.get<{
    items: SystemLoginLogApi.LoginLog[];
    total: number;
  }>('/system/login-log/list', { params });
}

export { getLoginLogList };
