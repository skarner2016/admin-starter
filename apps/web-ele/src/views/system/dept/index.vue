<script lang="ts" setup>
import type {
  OnActionClickParams,
  VxeTableGridOptions,
} from '#/adapter/vxe-table';
import type { SystemDeptApi } from '#/api/system/dept';

import { Page, useVbenDrawer } from '@vben/common-ui';
import { Plus } from '@vben/icons';
import { $t } from '@vben/locales';

import { ElButton, ElMessage, ElMessageBox } from 'element-plus';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import { deleteDept, getDeptList, updateDept } from '#/api/system/dept';

import { useColumns } from './data';
import Form from './modules/form.vue';

const [FormDrawer, formDrawerApi] = useVbenDrawer<
  Partial<SystemDeptApi.SystemDept>
>({
  connectedComponent: Form,
  destroyOnClose: true,
});

const [Grid, gridApi] = useVbenVxeGrid({
  gridOptions: {
    columns: useColumns(onActionClick, onStatusChange),
    height: 'auto',
    keepSource: true,
    pagerConfig: { enabled: false },
    proxyConfig: {
      ajax: {
        query: async () => {
          return await getDeptList();
        },
      },
    },
    rowConfig: { keyField: 'id' },
    toolbarConfig: {
      custom: true,
      export: false,
      refresh: true,
      zoom: true,
    },
    treeConfig: {
      parentField: 'pid',
      rowField: 'id',
      transform: false,
    },
  } as VxeTableGridOptions<SystemDeptApi.SystemDept>,
});

function onActionClick({
  code,
  row,
}: OnActionClickParams<SystemDeptApi.SystemDept>) {
  switch (code) {
    case 'append': {
      // 只带 pid，打开后即为「新增下级」
      formDrawerApi.setData({ pid: row.id }).open();
      break;
    }
    case 'delete': {
      onDelete(row);
      break;
    }
    case 'edit': {
      formDrawerApi.setData(row).open();
      break;
    }
    default: {
      break;
    }
  }
}

async function onStatusChange(
  newStatus: number,
  row: SystemDeptApi.SystemDept,
) {
  const label = newStatus === 1 ? $t('common.enabled') : $t('common.disabled');

  try {
    await ElMessageBox.confirm(
      `将「${row.name}」状态切换为「${label}」？`,
      $t('system.dept.status'),
      { type: 'warning' },
    );
    await updateDept(row.id, {
      name: row.name,
      pid: row.pid,
      remark: row.remark,
      sort: row.sort,
      status: newStatus,
    });
    return true;
  } catch {
    return false;
  }
}

function onDelete(row: SystemDeptApi.SystemDept) {
  ElMessageBox.confirm(
    $t('ui.actionMessage.deleteConfirm', [row.name]),
    $t('ui.actionTitle.delete', [$t('system.dept.name')]),
    { type: 'warning' },
  )
    .then(async () => {
      await deleteDept(row.id);
      ElMessage.success($t('ui.actionMessage.deleteSuccess', [row.name]));
      onRefresh();
    })
    .catch(() => {});
}

function onRefresh() {
  gridApi.query();
}

function onCreate() {
  formDrawerApi.setData({}).open();
}
</script>

<template>
  <Page auto-content-height>
    <FormDrawer @success="onRefresh" />
    <Grid :table-title="$t('system.dept.list')">
      <template #toolbar-tools>
        <ElButton type="primary" @click="onCreate">
          <Plus class="size-5" />
          {{ $t('ui.actionTitle.create', [$t('system.dept.name')]) }}
        </ElButton>
      </template>
    </Grid>
  </Page>
</template>
