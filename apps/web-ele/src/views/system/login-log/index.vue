<script lang="ts" setup>
import type { Recordable } from '@vben/types';

import type { VxeTableGridOptions } from '#/adapter/vxe-table';

import { Page } from '@vben/common-ui';
import { $t } from '@vben/locales';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import { getLoginLogList } from '#/api/system/login-log';

import { useColumns, useGridFormSchema } from './data';

const [Grid] = useVbenVxeGrid({
  formOptions: {
    schema: useGridFormSchema(),
    submitOnChange: true,
  },
  gridOptions: {
    columns: useColumns(),
    exportConfig: {},
    height: 'auto',
    keepSource: true,
    proxyConfig: {
      ajax: {
        query: async ({ page }, formValues: Recordable<any>) => {
          // 日期区间拆成后端认识的 startTime / endTime
          const { range, ...rest } = formValues ?? {};

          return await getLoginLogList({
            page: page.currentPage,
            pageSize: page.pageSize,
            ...rest,
            endTime: range?.[1],
            startTime: range?.[0],
          });
        },
      },
    },
    rowConfig: { keyField: 'id' },
    toolbarConfig: {
      custom: true,
      export: true,
      refresh: true,
      search: true,
      zoom: true,
    },
  } as VxeTableGridOptions,
});
</script>

<template>
  <Page auto-content-height>
    <!-- 只读列表：日志不允许在界面上修改或删除 -->
    <Grid :table-title="$t('system.loginLog.list')" />
  </Page>
</template>
