<script setup lang="ts">
import type { Recordable } from '@vben/types';

import type { VbenFormSchema } from '#/adapter/form';

import { computed, onMounted, ref } from 'vue';

import { ProfileBaseSetting } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { ElMessage } from 'element-plus';

import { getUserInfoApi, updateProfileApi, uploadFile } from '#/api';

const profileBaseSettingRef = ref();
const userStore = useUserStore();

const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      fieldName: 'realName',
      component: 'Input',
      label: '姓名',
      rules: 'required',
    },
    {
      fieldName: 'avatar',
      component: 'Upload',
      componentProps: {
        accept: 'image/*',
        httpRequest: uploadFile,
        limit: 1,
        listType: 'picture-card',
      },
      help: '支持 jpg / png / gif / webp，不超过 2MB',
      label: '头像',
    },
    {
      fieldName: 'description',
      component: 'Input',
      componentProps: {
        rows: 3,
        type: 'textarea',
      },
      label: '个人简介',
    },
  ];
});

/** fileList → URL：优先取本次上传的响应，其次取已有头像的 url */
function avatarUrl(fileList: any): string | undefined {
  const file = Array.isArray(fileList) ? fileList[0] : undefined;

  return file?.response?.url ?? file?.url ?? undefined;
}

onMounted(async () => {
  // 后端返回的是 description（UserInfo 类型里叫 desc），这里按实际字段读取
  const data = (await getUserInfoApi()) as Recordable<any>;

  profileBaseSettingRef.value?.getFormApi().setValues({
    realName: data.realName,
    description: data.description ?? '',
    avatar: data.avatar
      ? [{ name: 'avatar', status: 'done', url: data.avatar }]
      : [],
  });
});

async function handleSubmit(values: Recordable<any>) {
  const info = await updateProfileApi({
    avatar: avatarUrl(values.avatar),
    description: values.description ?? '',
    realName: values.realName,
  });

  userStore.setUserInfo(info);
  ElMessage.success('资料已更新');
}
</script>
<template>
  <ProfileBaseSetting
    ref="profileBaseSettingRef"
    :form-schema="formSchema"
    @submit="handleSubmit"
  />
</template>
