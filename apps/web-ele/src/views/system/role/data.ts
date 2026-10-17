import type { VbenFormSchema } from '#/adapter/form';
import type { OnActionClickFn, VxeTableGridColumns } from '#/adapter/vxe-table';
import type { SystemRoleApi } from '#/api/system/role';

import { useAccess } from '@vben/access';

import { $t } from '#/locales';

export function useFormSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'name',
      label: $t('system.role.roleName'),
      rules: 'required',
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
      label: $t('system.role.status'),
    },
    {
      component: 'Input',
      componentProps: { rows: 3, type: 'textarea' },
      fieldName: 'remark',
      label: $t('system.role.remark'),
    },
    {
      // 权限树由 form.vue 通过同名插槽注入
      component: 'Input',
      fieldName: 'permissions',
      formItemClass: 'items-start',
      label: $t('system.role.setPermissions'),
      modelPropName: 'modelValue',
    },
  ];
}

export function useGridFormSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'name',
      label: $t('system.role.roleName'),
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
      label: $t('system.role.status'),
    },
  ];
}

export function useColumns<T = SystemRoleApi.SystemRole>(
  onActionClick: OnActionClickFn<T>,
  onStatusChange?: (newStatus: any, row: T) => PromiseLike<boolean | undefined>,
): VxeTableGridColumns {
  const { hasAccessByCodes } = useAccess();
  // 无编辑权限时状态列降级为只读标签，避免「点了才 403」
  const canEdit = hasAccessByCodes(['System:Role:Edit']);
  const canDelete = hasAccessByCodes(['System:Role:Delete']);

  return [
    {
      field: 'name',
      title: $t('system.role.roleName'),
      width: 200,
    },
    {
      cellRender: {
        attrs: { beforeChange: canEdit ? onStatusChange : undefined },
        name: canEdit && onStatusChange ? 'CellSwitch' : 'CellTag',
      },
      field: 'status',
      title: $t('system.role.status'),
      width: 100,
    },
    {
      field: 'remark',
      minWidth: 120,
      title: $t('system.role.remark'),
    },
    {
      field: 'createTime',
      title: $t('system.role.createTime'),
      width: 180,
    },
    {
      align: 'center',
      cellRender: {
        attrs: {
          nameField: 'name',
          nameTitle: $t('system.role.name'),
          onClick: onActionClick,
        },
        name: 'CellOperation',
        // 显式声明，避免默认渲染出无处理逻辑的「详情」按钮
        options: [
          { code: 'edit', show: canEdit },
          { code: 'delete', show: canDelete },
        ],
      },
      field: 'operation',
      fixed: 'right',
      title: $t('system.role.operation'),
      width: 130,
    },
  ];
}
