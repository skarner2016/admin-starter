import type { VbenFormSchema } from '#/adapter/form';
import type { OnActionClickFn, VxeTableGridColumns } from '#/adapter/vxe-table';
import type { SystemUserApi } from '#/api/system/user';

import { useAccess } from '@vben/access';

import { uploadFile } from '#/api';
import { getDeptList } from '#/api/system/dept';
import { $t } from '#/locales';

/** 部门树选择器的公共配置：部门字段是 name / id，需显式指定映射 */
function deptSelectProps() {
  return {
    allowClear: true,
    api: getDeptList,
    childrenField: 'children',
    class: 'w-full',
    labelField: 'name',
    valueField: 'id',
  };
}

export function useFormSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'username',
      label: $t('system.user.username'),
      rules: 'required',
    },
    {
      component: 'Input',
      fieldName: 'realName',
      label: $t('system.user.realName'),
      rules: 'required',
    },
    {
      component: 'Upload',
      componentProps: {
        accept: 'image/*',
        // 走 requestClient 上传，自动带认证头；返回值 { url } 由 form.vue 解出
        httpRequest: uploadFile,
        limit: 1,
        listType: 'picture-card',
      },
      fieldName: 'avatar',
      help: '支持 jpg / png / gif / webp，不超过 2MB',
      label: $t('system.user.avatar'),
    },
    {
      // 非必填：super / 系统账号可能不属于任何部门
      component: 'ApiTreeSelect',
      componentProps: deptSelectProps(),
      fieldName: 'deptId',
      label: $t('system.user.dept'),
    },
    {
      component: 'Input',
      componentProps: {
        placeholder: '留空表示不修改密码',
        showPassword: true,
        type: 'password',
      },
      fieldName: 'password',
      help: '至少 8 位，需包含大小写字母和数字',
      label: $t('system.user.password'),
    },
    {
      component: 'ApiSelect',
      componentProps: {
        class: 'w-full',
        multiple: true,
      },
      fieldName: 'roles',
      label: $t('system.user.roles'),
    },
    {
      component: 'RadioGroup',
      componentProps: {
        isButton: true,
        options: [
          { label: $t('common.enabled'), value: 1 },
          { label: $t('common.disabled'), value: 0 },
        ],
      },
      defaultValue: 1,
      fieldName: 'status',
      label: $t('system.user.status'),
    },
    {
      component: 'Input',
      componentProps: { rows: 3, type: 'textarea' },
      fieldName: 'description',
      label: $t('system.user.remark'),
    },
  ];
}

export function useGridFormSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'username',
      label: $t('system.user.username'),
    },
    {
      component: 'Input',
      fieldName: 'realName',
      label: $t('system.user.realName'),
    },
    {
      // 传部门 id 时后端按「该部门及其所有子孙部门」过滤
      component: 'ApiTreeSelect',
      componentProps: deptSelectProps(),
      fieldName: 'deptId',
      label: $t('system.user.dept'),
    },
    {
      component: 'Select',
      componentProps: {
        allowClear: true,
        options: [
          { label: $t('common.enabled'), value: 1 },
          { label: $t('common.disabled'), value: 0 },
        ],
      },
      fieldName: 'status',
      label: $t('system.user.status'),
    },
  ];
}

export function useColumns<T = SystemUserApi.SystemUser>(
  onActionClick: OnActionClickFn<T>,
  onStatusChange?: (newStatus: any, row: T) => PromiseLike<boolean | undefined>,
): VxeTableGridColumns {
  const { hasAccessByCodes } = useAccess();
  // 无编辑权限时状态列降级为只读标签，避免「点了才 403」
  const canEdit = hasAccessByCodes(['System:User:Edit']);
  const canDelete = hasAccessByCodes(['System:User:Delete']);

  return [
    {
      field: 'username',
      title: $t('system.user.username'),
      width: 160,
    },
    {
      field: 'realName',
      title: $t('system.user.realName'),
      width: 160,
    },
    {
      field: 'deptName',
      title: $t('system.user.dept'),
      width: 160,
    },
    {
      field: 'roles',
      formatter: ({ row }: { row: SystemUserApi.SystemUser }) =>
        (row.roles ?? []).join(', '),
      title: $t('system.user.roles'),
      width: 200,
    },
    {
      cellRender: {
        attrs: { beforeChange: canEdit ? onStatusChange : undefined },
        name: canEdit && onStatusChange ? 'CellSwitch' : 'CellTag',
      },
      field: 'status',
      title: $t('system.user.status'),
      width: 100,
    },
    {
      field: 'description',
      minWidth: 120,
      title: $t('system.user.remark'),
    },
    {
      field: 'createTime',
      title: $t('system.user.createTime'),
      width: 180,
    },
    {
      align: 'center',
      cellRender: {
        attrs: {
          nameField: 'username',
          nameTitle: $t('system.user.name'),
          onClick: onActionClick,
        },
        name: 'CellOperation',
        options: [
          { code: 'edit', show: canEdit },
          {
            code: 'reset',
            show: canEdit,
            text: $t('system.user.resetPassword'),
          },
          { code: 'delete', show: canDelete },
        ],
      },
      field: 'operation',
      fixed: 'right',
      showOverflow: false,
      title: $t('system.user.operation'),
      width: 220,
    },
  ];
}
