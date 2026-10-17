import type { VbenFormSchema } from '#/adapter/form';
import type { OnActionClickFn, VxeTableGridColumns } from '#/adapter/vxe-table';
import type { SystemDeptApi } from '#/api/system/dept';

import { useAccess } from '@vben/access';

import { $t } from '#/locales';

export function useFormSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'name',
      label: $t('system.dept.deptName'),
      rules: 'required',
    },
    {
      // 上级部门选项由 form.vue 注入 api（需排除自身与子孙，避免被后端环路校验拒绝）
      component: 'ApiTreeSelect',
      componentProps: {
        allowClear: true,
        class: 'w-full',
        childrenField: 'children',
        labelField: 'name',
        valueField: 'id',
      },
      fieldName: 'pid',
      label: $t('system.dept.parentDept'),
    },
    {
      component: 'InputNumber',
      componentProps: { class: 'w-full', min: 0 },
      defaultValue: 0,
      fieldName: 'sort',
      label: $t('system.dept.sort'),
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
      label: $t('system.dept.status'),
    },
    {
      component: 'Input',
      componentProps: { rows: 3, type: 'textarea' },
      fieldName: 'remark',
      label: $t('system.dept.remark'),
    },
  ];
}

export function useColumns<T = SystemDeptApi.SystemDept>(
  onActionClick: OnActionClickFn<T>,
  onStatusChange?: (newStatus: any, row: T) => PromiseLike<boolean | undefined>,
): VxeTableGridColumns {
  const { hasAccessByCodes } = useAccess();
  // 无编辑权限时状态列降级为只读标签，避免「点了才 403」
  const canCreate = hasAccessByCodes(['System:Dept:Create']);
  const canEdit = hasAccessByCodes(['System:Dept:Edit']);
  const canDelete = hasAccessByCodes(['System:Dept:Delete']);

  return [
    {
      field: 'name',
      fixed: 'left',
      minWidth: 200,
      title: $t('system.dept.deptName'),
      treeNode: true,
    },
    {
      field: 'sort',
      title: $t('system.dept.sort'),
      width: 100,
    },
    {
      cellRender: {
        attrs: { beforeChange: canEdit ? onStatusChange : undefined },
        name: canEdit && onStatusChange ? 'CellSwitch' : 'CellTag',
      },
      field: 'status',
      title: $t('system.dept.status'),
      width: 100,
    },
    {
      field: 'remark',
      minWidth: 120,
      title: $t('system.dept.remark'),
    },
    {
      field: 'createTime',
      title: $t('system.dept.createTime'),
      width: 180,
    },
    {
      align: 'center',
      cellRender: {
        attrs: {
          nameField: 'name',
          nameTitle: $t('system.dept.name'),
          onClick: onActionClick,
        },
        name: 'CellOperation',
        options: [
          {
            code: 'append',
            show: canCreate,
            text: $t('system.dept.appendChild'),
          },
          { code: 'edit', show: canEdit },
          { code: 'delete', show: canDelete },
        ],
      },
      field: 'operation',
      fixed: 'right',
      showOverflow: false,
      title: $t('system.dept.operation'),
      width: 220,
    },
  ];
}
