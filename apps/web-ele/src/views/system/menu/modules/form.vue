<script lang="ts" setup>
import type { Recordable } from '@vben/types';

import type { SystemMenuApi } from '#/api/system/menu';

import { computed, nextTick, ref } from 'vue';

import { useVbenDrawer } from '@vben/common-ui';
import { $t } from '@vben/locales';

import { useVbenForm } from '#/adapter/form';
import { createMenu, getMenuList, updateMenu } from '#/api/system/menu';

import { getMenuTypeOptions } from '../data';

const emits = defineEmits(['success']);

const formData = ref<SystemMenuApi.SystemMenu>();
const id = ref<string>();
const parentOptions = ref<Recordable<any>[]>([]);
const typeOptions = getMenuTypeOptions();

const schema = [
  {
    component: 'RadioGroup',
    componentProps: { isButton: true, options: typeOptions },
    defaultValue: 'menu',
    fieldName: 'type',
    label: $t('system.menu.type'),
    rules: 'required',
  },
  {
    component: 'ApiTreeSelect',
    componentProps: {
      allowClear: true,
      // 自取数据，避免组件挂载时选项尚未就绪
      api: async () => {
        if (parentOptions.value.length === 0) {
          await loadMenus();
        }
        return parentOptions.value;
      },
      childrenField: 'children',
      labelField: 'name',
      valueField: 'id',
    },
    fieldName: 'pid',
    label: $t('system.menu.parent'),
  },
  {
    component: 'Input',
    fieldName: 'name',
    label: $t('system.menu.menuName'),
    rules: 'required',
  },
  {
    component: 'Input',
    fieldName: 'metaTitle',
    help: $t('system.menu.metaTitleHelp'),
    label: $t('system.menu.menuTitle'),
    rules: 'required',
  },
  {
    component: 'Input',
    componentProps: { placeholder: '/dashboard 或 workspace' },
    dependencies: {
      show: (values: Recordable<any>) => values.type !== 'button',
      triggerFields: ['type'],
    },
    fieldName: 'path',
    label: $t('system.menu.path'),
  },
  {
    component: 'Input',
    componentProps: { placeholder: '/dashboard/workspace/index' },
    dependencies: {
      show: (values: Recordable<any>) =>
        values.type === 'catalog' || values.type === 'menu',
      triggerFields: ['type'],
    },
    fieldName: 'component',
    label: $t('system.menu.component'),
  },
  {
    component: 'Input',
    componentProps: { placeholder: 'System:User:Create' },
    dependencies: {
      show: (values: Recordable<any>) => values.type === 'button',
      triggerFields: ['type'],
    },
    fieldName: 'authCode',
    label: $t('system.menu.authCode'),
  },
  {
    component: 'Input',
    dependencies: {
      show: (values: Recordable<any>) =>
        values.type === 'link' || values.type === 'embedded',
      triggerFields: ['type'],
    },
    fieldName: 'linkSrc',
    label: $t('system.menu.linkSrc'),
  },
  {
    component: 'Input',
    componentProps: { placeholder: 'lucide:layout-dashboard' },
    fieldName: 'metaIcon',
    label: $t('system.menu.icon'),
  },
  {
    component: 'InputNumber',
    componentProps: { class: 'w-full' },
    defaultValue: 0,
    fieldName: 'metaOrder',
    label: '排序',
  },
  {
    component: 'Switch',
    defaultValue: true,
    fieldName: 'metaKeepAlive',
    label: $t('system.menu.keepAlive'),
  },
  {
    component: 'Switch',
    defaultValue: false,
    fieldName: 'metaAffixTab',
    label: $t('system.menu.affixTab'),
  },
  {
    component: 'Switch',
    defaultValue: false,
    fieldName: 'metaHideInMenu',
    label: $t('system.menu.hideInMenu'),
  },
  {
    component: 'RadioGroup',
    componentProps: {
      isButton: true,
      options: [
        { label: $t('common.enabled'), value: 1 },
        { label: $t('common.disabled'), value: 0 },
      ],
    },
    defaultValue: 1,
    fieldName: 'status',
    label: $t('system.menu.status'),
  },
];

const [Form, formApi] = useVbenForm({
  schema,
  showDefaultActions: false,
  wrapperClass: 'grid-cols-1',
});

const [Drawer, drawerApi] = useVbenDrawer<null | SystemMenuApi.SystemMenu>({
  async onConfirm() {
    const { valid } = await formApi.validate();
    if (!valid) {
      return;
    }

    const values = (await formApi.getValues()) as Recordable<any>;
    drawerApi.lock();

    const payload = encode(values);

    (id.value ? updateMenu(id.value, payload) : createMenu(payload))
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

    const data = drawerApi.getData() as
      | (Partial<SystemMenuApi.SystemMenu> & { pid?: string })
      | undefined;
    formApi.reset();

    if (parentOptions.value.length === 0) {
      parentOptions.value = await getMenuList();
    }

    id.value = data?.id;
    formData.value = data?.id ? (data as SystemMenuApi.SystemMenu) : undefined;

    await nextTick();
    formApi.setValues(decode(data));
  },
});

/** 上级菜单选项：供 ApiTreeSelect 的 api 自取 */
async function loadMenus() {
  parentOptions.value = (await getMenuList()) as Recordable<any>[];

  return parentOptions.value;
}

/** 扁平表单值 → 后端契约 */
function encode(values: Recordable<any>) {
  const meta: Recordable<any> = {
    title: values.metaTitle,
    icon: values.metaIcon || undefined,
    order: Number(values.metaOrder ?? 0),
    keepAlive: values.metaKeepAlive,
    affixTab: values.metaAffixTab,
    hideInMenu: values.metaHideInMenu,
  };

  if (values.type === 'link') {
    meta.link = values.linkSrc;
  }
  if (values.type === 'embedded') {
    meta.iframeSrc = values.linkSrc;
  }

  return {
    authCode: values.type === 'button' ? values.authCode : null,
    component: values.component || null,
    meta,
    name: values.name,
    path: values.path || null,
    pid: values.pid ? String(values.pid) : '0',
    redirect: null,
    status: values.status,
    type: values.type,
  };
}

/** 后端契约 → 扁平表单值 */
function decode(data?: Recordable<any>) {
  const meta = data?.meta ?? {};

  return {
    authCode: data?.authCode ?? '',
    component: data?.component ?? '',
    linkSrc: meta.link ?? meta.iframeSrc ?? '',
    metaAffixTab: meta.affixTab ?? false,
    metaHideInMenu: meta.hideInMenu ?? false,
    metaIcon: meta.icon ?? '',
    metaKeepAlive: meta.keepAlive ?? false,
    metaOrder: meta.order ?? 0,
    metaTitle: meta.title ?? '',
    name: data?.name ?? '',
    path: data?.path ?? '',
    pid: data?.pid && data.pid !== '0' ? String(data.pid) : undefined,
    status: data?.status ?? 1,
    type: data?.type ?? 'menu',
  };
}

const drawerTitle = computed(() =>
  id.value
    ? $t('common.edit', $t('system.menu.name'))
    : $t('common.create', $t('system.menu.name')),
);

defineExpose({ drawerApi });
</script>

<template>
  <Drawer :title="drawerTitle">
    <Form />
  </Drawer>
</template>
