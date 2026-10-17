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
  deleteUser,
  getUserList,
  resetUserPassword,
  updateUser,
} from '#/api/system/user';

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
    columns: useColumns(onActionClick, onStatusChange),
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
      export: false,
      refresh: true,
      search: true,
      zoom: true,
    },
  } as VxeTableGridOptions<SystemUserApi.SystemUser>,
});

function onActionClick({ code, row }: OnActionClickParams<SystemUserApi.SystemUser>) {
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
  newStatus: number,
  row: SystemUserApi.SystemUser,
) {
  const label = newStatus === 1 ? $t('common.enabled') : $t('common.disabled');

  try {
    await ElMessageBox.confirm(
      `将「${row.username}」状态切换为「${label}」？`,
      $t('system.user.status'),
      { type: 'warning' },
    );
    await updateUser(row.id, {
      description: row.description,
      realName: row.realName,
      roles: row.roles ?? [],
      status: newStatus,
      username: row.username,
    });
    return true;
  } catch {
    return false;
  }
}

function onResetPassword(row: SystemUserApi.SystemUser) {
  ElMessageBox.prompt(
    `为「${row.username}」设置新密码`,
    $t('system.user.resetPassword'),
    {
      inputType: 'password',
      inputValidator: (value) => (value && value.length >= 6) || '至少 6 位',
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
</script>

<template>
  <Page auto-content-height>
    <FormDrawer @success="onRefresh" />
    <Grid :table-title="$t('system.user.list')">
      <template #toolbar-tools>
        <ElButton type="primary" @click="onCreate">
          <Plus class="size-5" />
          {{ $t('ui.actionTitle.create', [$t('system.user.name')]) }}
        </ElButton>
      </template>
    </Grid>
  </Page>
</template>
