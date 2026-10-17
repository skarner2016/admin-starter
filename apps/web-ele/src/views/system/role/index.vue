<script lang="ts" setup>
import type { Recordable } from '@vben/types';

import type {
  OnActionClickParams,
  VxeTableGridOptions,
} from '#/adapter/vxe-table';
import type { SystemRoleApi } from '#/api/system/role';

import { Page, useVbenDrawer } from '@vben/common-ui';
import { Plus } from '@vben/icons';
import { $t } from '@vben/locales';

import { ElButton, ElMessage, ElMessageBox } from 'element-plus';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import {
  batchDeleteRoles,
  deleteRole,
  getRoleList,
  updateRole,
} from '#/api/system/role';

import { useColumns, useGridFormSchema } from './data';
import Form from './modules/form.vue';

const [FormDrawer, formDrawerApi] = useVbenDrawer({
  connectedComponent: Form,
  destroyOnClose: true,
});

const [Grid, gridApi] = useVbenVxeGrid({
  formOptions: {
    schema: useGridFormSchema(),
    submitOnChange: true,
  },
  gridOptions: {
    checkboxConfig: { highlight: true },
    columns: useColumns(onActionClick, onStatusChange),
    exportConfig: {},
    height: 'auto',
    keepSource: true,
    proxyConfig: {
      ajax: {
        query: async ({ page }, formValues: Recordable<any>) => {
          return await getRoleList({
            page: page.currentPage,
            pageSize: page.pageSize,
            ...formValues,
          });
        },
      },
    },
    rowConfig: {
      keyField: 'id',
    },
    toolbarConfig: {
      custom: true,
      export: true,
      refresh: true,
      search: true,
      zoom: true,
    },
  } as VxeTableGridOptions<SystemRoleApi.SystemRole>,
});

function onActionClick({
  code,
  row,
}: OnActionClickParams<SystemRoleApi.SystemRole>) {
  switch (code) {
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

/**
 * 状态开关变更前确认；返回 false 则中止改变
 */
async function onStatusChange(
  newStatus: number,
  row: SystemRoleApi.SystemRole,
) {
  const label = newStatus === 1 ? $t('common.enabled') : $t('common.disabled');

  try {
    await ElMessageBox.confirm(
      `将「${row.name}」状态切换为「${label}」？`,
      $t('system.role.status'),
      { type: 'warning' },
    );
    await updateRole(row.id, {
      name: row.name,
      permissions: row.permissions ?? [],
      remark: row.remark,
      status: newStatus,
    });
    return true;
  } catch {
    return false;
  }
}

function onDelete(row: SystemRoleApi.SystemRole) {
  ElMessageBox.confirm(
    $t('ui.actionMessage.deleteConfirm', [row.name]),
    $t('ui.actionTitle.delete', [$t('system.role.name')]),
    { type: 'warning' },
  )
    .then(async () => {
      await deleteRole(row.id);
      ElMessage.success($t('ui.actionMessage.deleteSuccess', [row.name]));
      onRefresh();
    })
    .catch(() => {});
}

function onRefresh() {
  gridApi.query();
}

function onCreate() {
  formDrawerApi.setData(null).open();
}

async function onBatchDelete() {
  const rows = gridApi.grid.getCheckboxRecords() as SystemRoleApi.SystemRole[];

  if (rows.length === 0) {
    ElMessage.warning('请先选择要删除的数据');
    return;
  }

  try {
    await ElMessageBox.confirm(
      `确定删除选中的 ${rows.length} 个角色吗？`,
      '批量删除',
      { type: 'warning' },
    );
  } catch {
    return;
  }

  const result = await batchDeleteRoles(rows.map((row) => row.id));
  const failed = result.failed ?? [];

  if (failed.length > 0) {
    ElMessage.warning(
      `已删除 ${result.deleted.length} 个，${failed.length} 个未删除：${failed[0]?.message ?? ''}`,
    );
  } else {
    ElMessage.success(`已删除 ${result.deleted.length} 个角色`);
  }

  onRefresh();
}
</script>

<template>
  <Page auto-content-height>
    <FormDrawer @success="onRefresh" />
    <Grid :table-title="$t('system.role.list')">
      <template #toolbar-tools>
        <ElButton
          v-access:code="'System:Role:Create'"
          type="primary"
          @click="onCreate"
        >
          <Plus class="size-5" />
          {{ $t('ui.actionTitle.create', [$t('system.role.name')]) }}
        </ElButton>
        <ElButton
          v-access:code="'System:Role:Delete'"
          plain
          type="danger"
          @click="onBatchDelete"
        >
          批量删除
        </ElButton>
      </template>
    </Grid>
  </Page>
</template>
