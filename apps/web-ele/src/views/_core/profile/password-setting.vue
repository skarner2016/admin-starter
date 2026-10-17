<script setup lang="ts">
import type { Recordable } from '@vben/types';

import type { VbenFormSchema } from '#/adapter/form';

import { computed, ref } from 'vue';

import { ProfilePasswordSetting, z } from '@vben/common-ui';

import { ElMessage } from 'element-plus';

import { changePasswordApi } from '#/api';

const profilePasswordSettingRef = ref();

const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      fieldName: 'oldPassword',
      label: '旧密码',
      component: 'VbenInputPassword',
      componentProps: {
        placeholder: '请输入旧密码',
      },
      rules: z.string().min(1, { message: '请输入旧密码' }),
    },
    {
      fieldName: 'newPassword',
      label: '新密码',
      component: 'VbenInputPassword',
      componentProps: {
        passwordStrength: true,
        placeholder: '请输入新密码',
      },
      help: '至少 8 位，需包含大小写字母和数字',
      rules: z
        .string()
        .min(8, { message: '新密码至少 8 位' })
        .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/, {
          message: '需包含大小写字母和数字',
        }),
    },
    {
      fieldName: 'confirmPassword',
      label: '确认密码',
      component: 'VbenInputPassword',
      componentProps: {
        passwordStrength: true,
        placeholder: '请再次输入新密码',
      },
      dependencies: {
        rules(values) {
          const { newPassword } = values;
          return z
            .string({ error: '请再次输入新密码' })
            .min(1, { message: '请再次输入新密码' })
            .refine((value) => value === newPassword, {
              message: '两次输入的密码不一致',
            });
        },
        triggerFields: ['newPassword'],
      },
    },
  ];
});

async function handleSubmit(values: Recordable<any>) {
  await changePasswordApi({
    oldPassword: values.oldPassword,
    password: values.newPassword,
  });

  ElMessage.success('密码修改成功，其它会话已失效');
  profilePasswordSettingRef.value?.getFormApi().reset();
}
</script>
<template>
  <ProfilePasswordSetting
    ref="profilePasswordSettingRef"
    class="w-1/3"
    :form-schema="formSchema"
    @submit="handleSubmit"
  />
</template>
