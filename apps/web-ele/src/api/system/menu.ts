import type { Recordable } from '@vben/types';

import { requestClient } from '#/api/request';

export namespace SystemMenuApi {
  /** 菜单类型集合 */
  export const MenuTypes = [
    'catalog',
    'menu',
    'embedded',
    'link',
    'button',
  ] as const;

  /** 系统菜单（管理视图，含全部类型与状态） */
  export interface SystemMenu {
    [key: string]: any;
    /** 权限标识（type=button 时对应后端 permission 名） */
    authCode?: string;
    /** 子级 */
    children?: SystemMenu[];
    /** 组件路径，相对 views/ */
    component?: string;
    /** 菜单ID */
    id: string;
    /** 菜单元数据 */
    meta?: Recordable<any>;
    /** 路由名称 */
    name: string;
    /** 路由路径；按钮类为空 */
    path?: null | string;
    /** 父级ID */
    pid: string;
    /** 重定向 */
    redirect?: null | string;
    /** 状态 */
    status: 0 | 1;
    /** 菜单类型 */
    type: (typeof MenuTypes)[number];
  }
}

/**
 * 获取菜单数据列表（树）
 */
async function getMenuList() {
  return requestClient.get<Array<SystemMenuApi.SystemMenu>>(
    '/system/menu/list',
  );
}

async function isMenuNameExists(
  name: string,
  id?: SystemMenuApi.SystemMenu['id'],
) {
  return requestClient.get<boolean>('/system/menu/name-exists', {
    params: { id, name },
  });
}

async function isMenuPathExists(
  path: string,
  id?: SystemMenuApi.SystemMenu['id'],
) {
  return requestClient.get<boolean>('/system/menu/path-exists', {
    params: { id, path },
  });
}

/**
 * 创建菜单
 */
async function createMenu(
  data: Omit<SystemMenuApi.SystemMenu, 'children' | 'id'>,
) {
  return requestClient.post('/system/menu', data);
}

/**
 * 更新菜单
 */
async function updateMenu(
  id: string,
  data: Omit<SystemMenuApi.SystemMenu, 'children' | 'id'>,
) {
  return requestClient.put(`/system/menu/${id}`, data);
}

/**
 * 删除菜单
 */
async function deleteMenu(id: string) {
  return requestClient.delete(`/system/menu/${id}`);
}

export {
  createMenu,
  deleteMenu,
  getMenuList,
  isMenuNameExists,
  isMenuPathExists,
  updateMenu,
};
