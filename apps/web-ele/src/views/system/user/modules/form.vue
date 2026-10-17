<script lang="ts" setup>
import type { Recordable } from '@vben/types';

import type { SystemUserApi } from '#/api/system/user';

import { computed, nextTick, ref } from 'vue';

import { useVbenDrawer } from '@vben/common-ui';
import { $t } from '@vben/locales';

import { ElMessage } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { getRoleList } from '#/api/system/role';
import { createUser, updateUser } from '#/api/system/user';

import { useFormSchema } from '../data';

const emits = defineEmits(['success']);

const formData = ref<SystemUserApi.SystemUser>();
const id = ref<string>();

async function roleOptions() {
  const { items } = await getRoleList({ page: 1, pageSize: 200 });

  return items.map((role) => ({ label: role.name, value: role.name }));
}

const schema = useFormSchema().map((item) =>
  item.fieldName === 'roles'
    ? { ...item, componentProps: { ...item.componentProps, api: roleOptions } }
    : item,
);

const [Form, formApi] = useVbenForm({
  schema,
  showDefaultActions: false,
});

const [Drawer, drawerApi] = useVbenDrawer<null | SystemUserApi.SystemUser>({
  async onConfirm() {
    const { valid } = await formApi.validate();
    if (!valid) {
      return;
    }

    const values = (await formApi.getValues()) as Recordable<any>;

    if (!id.value && !values.password) {
      ElMessage.error($t('system.user.password'));
      return;
    }

    drawerApi.lock();

    // 编辑时不提交 password，避免覆盖（后端 update 也不处理该字段）
    // deptId 空值统一提交空串，后端按「未分配」处理
    const payload = {
      ...values,
      deptId: values.deptId ?? '',
      password: id.value ? undefined : values.password,
    };

    (id.value ? updateUser(id.value, payload) : createUser(payload))
      .then(() => {
        emits('success');
        drawerApi.close();
      })
      .catch(() => {
        drawerApi.unlock();
      });
  },

  async onOpenChange(isOpen) {
    if (!isOpen) {
      return;
    }

    const data = drawerApi.getData();
    formApi.reset();
    formData.value = data ?? undefined;
    id.value = data?.id;

    await nextTick();

    if (data) {
      formApi.setValues({ ...data, password: '' });
    }
  },
});

const drawerTitle = computed(() =>
  id.value
    ? $t('common.edit', $t('system.user.name'))
    : $t('common.create', $t('system.user.name')),
);

defineExpose({ drawerApi });
</script>

<template>
  <Drawer :title="drawerTitle">
    <Form />
  </Drawer>
</template>
