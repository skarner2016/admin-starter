import type { OnActionClickFn, VxeTableGridColumns } from '#/adapter/vxe-table';
import type { SystemMenuApi } from '#/api/system/menu';

import { useAccess } from '@vben/access';

import { $t } from '#/locales';

/** 菜单类型 → Element Plus 语义色 */
export function getMenuTypeOptions() {
  return [
    { label: $t('system.menu.typeCatalog'), type: 'primary', value: 'catalog' },
    { label: $t('system.menu.typeMenu'), type: 'info', value: 'menu' },
    { label: $t('system.menu.typeButton'), type: 'danger', value: 'button' },
    {
      label: $t('system.menu.typeEmbedded'),
      type: 'success',
      value: 'embedded',
    },
    { label: $t('system.menu.typeLink'), type: 'warning', value: 'link' },
  ];
}

export function useColumns(
  onActionClick: OnActionClickFn<SystemMenuApi.SystemMenu>,
): VxeTableGridColumns {
  const { hasAccessByCodes } = useAccess();
  // 行内操作按权限码显隐，避免「看得见按钮、点了才 403」
  const canCreate = hasAccessByCodes(['System:Menu:Create']);
  const canEdit = hasAccessByCodes(['System:Menu:Edit']);
  const canDelete = hasAccessByCodes(['System:Menu:Delete']);

  return [
    {
      align: 'left',
      field: 'meta.title',
      fixed: 'left',
      slots: { default: 'title' },
      title: $t('system.menu.menuTitle'),
      treeNode: true,
      width: 250,
    },
    {
      align: 'center',
      cellRender: { name: 'CellTag', options: getMenuTypeOptions() },
      field: 'type',
      title: $t('system.menu.type'),
      width: 100,
    },
    {
      field: 'authCode',
      title: $t('system.menu.authCode'),
      width: 200,
    },
    {
      align: 'left',
      field: 'path',
      title: $t('system.menu.path'),
      width: 180,
    },
    {
      align: 'left',
      field: 'component',
      formatter: ({ row }: { row: SystemMenuApi.SystemMenu }) => {
        switch (row.type) {
          case 'catalog':
          case 'menu': {
            return row.component ?? '';
          }
          case 'embedded': {
            return row.meta?.iframeSrc ?? '';
          }
          case 'link': {
            return row.meta?.link ?? '';
          }
          default: {
            return '';
          }
        }
      },
      minWidth: 200,
      title: $t('system.menu.component'),
    },
    {
      cellRender: { name: 'CellTag' },
      field: 'status',
      title: $t('system.menu.status'),
      width: 100,
    },
    {
      align: 'right',
      cellRender: {
        attrs: {
          nameField: 'name',
          onClick: onActionClick,
        },
        name: 'CellOperation',
        options: [
          {
            code: 'append',
            show: canCreate,
            text: $t('system.menu.appendChild'),
          },
          { code: 'edit', show: canEdit },
          { code: 'delete', show: canDelete },
        ],
      },
      field: 'operation',
      fixed: 'right',
      headerAlign: 'center',
      showOverflow: false,
      title: $t('system.menu.operation'),
      width: 200,
    },
  ];
}
