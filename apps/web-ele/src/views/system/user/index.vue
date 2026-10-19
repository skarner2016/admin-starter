<script lang="ts" setup>
import type { Recordable } from '@vben/types';

import type {
  OnActionClickParams,
  VxeTableGridOptions,
} from '#/adapter/vxe-table';
import type { SystemUserApi } from '#/api/system/user';

import { Page, useVbenDrawer } from '@vben/common-ui';
import { Plus } from '@vben/icons';
import { $t } from '@vben/locales';

import { ElButton, ElMessage, ElMessageBox } from 'element-plus';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import {
  batchDeleteUsers,
  deleteUser,
  getUserList,
  resetUserPassword,
  updateUser,
} from '#/api/system/user';

import { useColumns, useGridFormSchema } from './data';
import Form from './modules/form.vue';
import { buildUserStatusTogglePayload } from './payload';

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
          return await getUserList({
            page: page.currentPage,
            pageSize: page.pageSize,
            ...formValues,
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
  } as VxeTableGridOptions<SystemUserApi.SystemUser>,
});

function onActionClick({
  code,
  row,
}: OnActionClickParams<SystemUserApi.SystemUser>) {
  switch (code) {
    case 'delete': {
      onDelete(row);
      break;
    }
    case 'edit': {
      formDrawerApi.setData(row).open();
      break;
    }
    case 'reset': {
      onResetPassword(row);
      break;
    }
    default: {
      break;
    }
  }
}

async function onStatusChange(
  newStatus: SystemUserApi.SystemUser['status'],
  row: SystemUserApi.SystemUser,
) {
  const label = newStatus === 1 ? $t('common.enabled') : $t('common.disabled');

  try {
    await ElMessageBox.confirm(
      `将「${row.username}」状态切换为「${label}」？`,
      $t('system.user.status'),
      { type: 'warning' },
    );
    await updateUser(row.id, buildUserStatusTogglePayload(row, newStatus));
    return true;
  } catch {
    return false;
  }
}

function onResetPassword(row: SystemUserApi.SystemUser) {
  ElMessageBox.prompt(
    `为「${row.username}」设置新密码（至少 8 位，需含大小写字母和数字）`,
    $t('system.user.resetPassword'),
    {
      inputType: 'password',
      // 与后端 AdminUserService 的密码策略保持一致
      inputValidator: (value) => {
        if (!value || value.length < 8) {
          return '至少 8 位';
        }
        return (
          /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/.test(value) ||
          '需包含大小写字母和数字'
        );
      },
    },
  )
    .then(async ({ value }) => {
      await resetUserPassword(row.id, value);
      ElMessage.success($t('system.user.resetPassword'));
      onRefresh();
    })
    .catch(() => {});
}

function onDelete(row: SystemUserApi.SystemUser) {
  ElMessageBox.confirm(
    $t('ui.actionMessage.deleteConfirm', [row.username]),
    $t('ui.actionTitle.delete', [$t('system.user.name')]),
    { type: 'warning' },
  )
    .then(async () => {
      await deleteUser(row.id);
      ElMessage.success($t('ui.actionMessage.deleteSuccess', [row.username]));
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
  const rows = gridApi.grid.getCheckboxRecords() as SystemUserApi.SystemUser[];

  if (rows.length === 0) {
    ElMessage.warning('请先选择要删除的数据');
    return;
  }

  try {
    await ElMessageBox.confirm(
      `确定删除选中的 ${rows.length} 个用户吗？`,
      '批量删除',
      { type: 'warning' },
    );
  } catch {
    return;
  }

  // 后端逐条应用删除护栏，返回每条结果（部分成功不会整批失败）
  const result = await batchDeleteUsers(rows.map((row) => row.id));
  const failed = result.failed ?? [];

  if (failed.length > 0) {
    ElMessage.warning(
      `已删除 ${result.deleted.length} 个，${failed.length} 个未删除：${failed[0]?.message ?? ''}`,
    );
  } else {
    ElMessage.success(`已删除 ${result.deleted.length} 个用户`);
  }

  onRefresh();
}
</script>

<template>
  <Page auto-content-height>
    <FormDrawer @success="onRefresh" />
    <Grid :table-title="$t('system.user.list')">
      <template #toolbar-tools>
        <ElButton
          v-access:code="'System:User:Create'"
          type="primary"
          @click="onCreate"
        >
          <Plus class="size-5" />
          {{ $t('ui.actionTitle.create', [$t('system.user.name')]) }}
        </ElButton>
        <ElButton
          v-access:code="'System:User:Delete'"
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
