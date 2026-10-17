import { requestClient } from '#/api/request';

export namespace SystemDeptApi {
  export interface SystemDept {
    [key: string]: any;
    children?: SystemDept[];
    /** 创建时间 */
    createTime?: string;
    id: string;
    /** 部门名称，同级唯一 */
    name: string;
    /** 父部门 id，'0' 为顶级 */
    pid: string;
    remark?: string;
    /** 同级排序 */
    sort?: number;
    status: 0 | 1;
  }
}

/**
 * 获取部门列表数据（树，不分页）
 */
async function getDeptList() {
  return requestClient.get<Array<SystemDeptApi.SystemDept>>(
    '/system/dept/list',
  );
}

/**
 * 同级下部门名是否已存在
 */
async function isDeptNameExists(params: {
  id?: string;
  name: string;
  pid?: string;
}) {
  return requestClient.get<boolean>('/system/dept/name-exists', { params });
}

/**
 * 创建部门
 */
async function createDept(
  data: Omit<SystemDeptApi.SystemDept, 'children' | 'id'>,
) {
  return requestClient.post('/system/dept', data);
}

/**
 * 更新部门
 */
async function updateDept(
  id: string,
  data: Omit<SystemDeptApi.SystemDept, 'children' | 'id'>,
) {
  return requestClient.put(`/system/dept/${id}`, data);
}

/**
 * 删除部门
 */
async function deleteDept(id: string) {
  return requestClient.delete(`/system/dept/${id}`);
}

export { createDept, deleteDept, getDeptList, isDeptNameExists, updateDept };
