<script lang="ts" setup>
import type { Recordable } from '@vben/types';

import type { SystemDeptApi } from '#/api/system/dept';

import { computed, nextTick, ref } from 'vue';

import { useVbenDrawer } from '@vben/common-ui';
import { $t } from '@vben/locales';

import { useVbenForm } from '#/adapter/form';
import { createDept, getDeptList, updateDept } from '#/api/system/dept';

import { useFormSchema } from '../data';

const emits = defineEmits(['success']);

const formData = ref<SystemDeptApi.SystemDept>();
const id = ref<string>();

/**
 * 上级部门选项：编辑时排除自身及其子孙。
 * 后端也会拒绝成环，这里提前过滤是为了不把无效选项摆在用户面前。
 */
async function parentOptions() {
  const tree = await getDeptList();

  return id.value ? removeSubtree(tree, id.value) : tree;
}

function removeSubtree(
  nodes: SystemDeptApi.SystemDept[],
  excludeId: string,
): SystemDeptApi.SystemDept[] {
  return nodes
    .filter((node) => node.id !== excludeId)
    .map((node) => ({
      ...node,
      children: node.children
        ? removeSubtree(node.children, excludeId)
        : undefined,
    }));
}

const schema = useFormSchema().map((item) =>
  item.fieldName === 'pid'
    ? {
        ...item,
        componentProps: { ...item.componentProps, api: parentOptions },
      }
    : item,
);

const [Form, formApi] = useVbenForm({
  schema,
  showDefaultActions: false,
});

const [Drawer, drawerApi] = useVbenDrawer<null | SystemDeptApi.SystemDept>({
  async onConfirm() {
    const { valid } = await formApi.validate();
    if (!valid) {
      return;
    }

    const values = (await formApi.getValues()) as Recordable<any>;
    drawerApi.lock();

    const payload = {
      name: values.name,
      pid: values.pid ? String(values.pid) : '0',
      remark: values.remark || null,
      sort: Number(values.sort ?? 0),
      status: values.status,
    };

    (id.value ? updateDept(id.value, payload) : createDept(payload))
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
    id.value = data?.id;
    formData.value = data ?? undefined;

    await nextTick();
    formApi.setValues(decode(data));
  },
});

/** 后端契约 → 表单值。'0' 表示顶级，选择器上要显示为未选 */
function decode(data?: null | Recordable<any>) {
  return {
    name: data?.name ?? '',
    pid: data?.pid && data.pid !== '0' ? String(data.pid) : undefined,
    remark: data?.remark ?? '',
    sort: data?.sort ?? 0,
    status: data?.status ?? 1,
  };
}

const drawerTitle = computed(() =>
  id.value
    ? $t('common.edit', $t('system.dept.name'))
    : $t('common.create', $t('system.dept.name')),
);

defineExpose({ drawerApi });
</script>

<template>
  <Drawer :title="drawerTitle">
    <Form />
  </Drawer>
</template>
