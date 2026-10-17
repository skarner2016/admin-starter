<script lang="ts" setup>
import type { Recordable } from '@vben/types';

import type { SystemRoleApi } from '#/api/system/role';

import { computed, nextTick, ref } from 'vue';

import { useVbenDrawer } from '@vben/common-ui';

import { ElTree } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { getMenuList } from '#/api/system/menu';
import { createRole, updateRole } from '#/api/system/role';
import { $t } from '#/locales';

import { useFormSchema } from '../data';

const emits = defineEmits(['success']);

const formData = ref<SystemRoleApi.SystemRole>();
const treeRef = ref<InstanceType<typeof ElTree>>();
const menuTree = ref<Recordable<any>[]>([]);
const loadingMenus = ref(false);
const id = ref<string>();

const [Form, formApi] = useVbenForm({
  schema: useFormSchema(),
  showDefaultActions: false,
});

const [Drawer, drawerApi] = useVbenDrawer<null | SystemRoleApi.SystemRole>({
  async onConfirm() {
    const { valid } = await formApi.validate();
    if (!valid) {
      return;
    }

    const values = await formApi.getValues();
    drawerApi.lock();

    const payload = { ...values, permissions: checkedKeys() };

    (id.value ? updateRole(id.value, payload) : createRole(payload))
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

    if (menuTree.value.length === 0) {
      await loadMenus();
    }

    await nextTick();

    // check-strictly：勾选与存储一一对应，不做父子级联，避免回显时误勾子节点
    treeRef.value?.setCheckedKeys(data?.permissions ?? [], false);
    if (data) {
      formApi.setValues(data);
    }
  },
});

function checkedKeys(): string[] {
  return (treeRef.value?.getCheckedKeys() ?? []).map((key) => String(key));
}

async function loadMenus() {
  loadingMenus.value = true;
  try {
    menuTree.value = (await getMenuList()) as Recordable<any>[];
  } finally {
    loadingMenus.value = false;
  }
}

const drawerTitle = computed(() =>
  formData.value?.id
    ? $t('common.edit', $t('system.role.name'))
    : $t('common.create', $t('system.role.name')),
);

defineExpose({ drawerApi });
</script>

<template>
  <Drawer :title="drawerTitle">
    <Form>
      <template #permissions="slotProps">
        <div
          v-loading="loadingMenus"
          class="max-h-80 w-full overflow-auto rounded border border-border p-2"
        >
          <ElTree
            ref="treeRef"
            :data="menuTree"
            node-key="id"
            show-checkbox
            check-strictly
            default-expand-all
            :props="{ children: 'children', label: 'name' }"
          >
            <template #default="{ data }">
              <span>
                {{ data.meta?.title ? $t(data.meta.title) : data.name }}
              </span>
            </template>
          </ElTree>
        </div>
        <input v-bind="slotProps" type="hidden" />
      </template>
    </Form>
  </Drawer>
</template>
