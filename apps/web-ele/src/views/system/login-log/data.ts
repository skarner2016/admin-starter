import type { VbenFormSchema } from '#/adapter/form';
import type { VxeTableGridColumns } from '#/adapter/vxe-table';
import type { SystemLoginLogApi } from '#/api/system/login-log';

import { $t } from '#/locales';

export function useGridFormSchema(): VbenFormSchema[] {
  return [
    {
      component: 'Input',
      fieldName: 'username',
      label: $t('system.loginLog.username'),
    },
    {
      component: 'Input',
      fieldName: 'ip',
      label: $t('system.loginLog.ip'),
    },
    {
      component: 'Select',
      componentProps: {
        allowClear: true,
        options: [
          { label: $t('system.loginLog.successYes'), value: 1 },
          { label: $t('system.loginLog.successNo'), value: 0 },
        ],
      },
      fieldName: 'success',
      label: $t('system.loginLog.success'),
    },
    {
      component: 'DatePicker',
      componentProps: {
        class: 'w-full',
        type: 'datetimerange',
        valueFormat: 'YYYY-MM-DD HH:mm:ss',
      },
      fieldName: 'range',
      label: $t('system.loginLog.range'),
    },
  ];
}

/** 失败原因码 → 展示文案 */
function reasonLabel(row: SystemLoginLogApi.LoginLog) {
  if (row.success) {
    return '';
  }

  const labels: Record<string, string> = {
    disabled: $t('system.loginLog.reasonDisabled'),
    invalid_credentials: $t('system.loginLog.reasonInvalidCredentials'),
    locked: $t('system.loginLog.reasonLocked'),
  };

  return labels[row.reason ?? ''] ?? row.reason ?? '';
}

export function useColumns(): VxeTableGridColumns {
  return [
    {
      field: 'username',
      title: $t('system.loginLog.username'),
      width: 140,
    },
    {
      align: 'center',
      cellRender: {
        name: 'CellTag',
        options: [
          {
            label: $t('system.loginLog.successYes'),
            type: 'success',
            value: true,
          },
          {
            label: $t('system.loginLog.successNo'),
            type: 'danger',
            value: false,
          },
        ],
      },
      field: 'success',
      title: $t('system.loginLog.success'),
      width: 100,
    },
    {
      field: 'reason',
      formatter: ({ row }: { row: SystemLoginLogApi.LoginLog }) =>
        reasonLabel(row),
      title: $t('system.loginLog.reason'),
      width: 120,
    },
    {
      field: 'ip',
      title: $t('system.loginLog.ip'),
      width: 160,
    },
    {
      field: 'userAgent',
      minWidth: 200,
      showOverflow: 'tooltip',
      title: $t('system.loginLog.userAgent'),
    },
    {
      field: 'createTime',
      title: $t('system.loginLog.createTime'),
      width: 180,
    },
  ];
}
