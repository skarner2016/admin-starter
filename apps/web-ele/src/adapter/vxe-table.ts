import type { FormValues } from '@vben/common-ui';
import type { VxeTableGridOptions } from '@vben/plugins/vxe-table';
import type { Recordable } from '@vben/types';

import type { ComponentPropsMap, ComponentType } from './component';

import { h } from 'vue';

import { $t } from '@vben/locales';
import {
  setupVbenVxeTable,
  useVbenVxeGrid as useGrid,
} from '@vben/plugins/vxe-table';

import { ElButton, ElImage, ElPopconfirm, ElSwitch, ElTag } from 'element-plus';

import { useVbenForm } from './form';

/** 支持 'meta.title' 这类嵌套字段取值 */
function getValue(row: Record<string, any>, field: string) {
  return field
    .split('.')
    .reduce<any>((acc, key) => (acc == null ? acc : acc[key]), row);
}

setupVbenVxeTable({
  configVxeTable: (vxeUI) => {
    vxeUI.setConfig({
      grid: {
        align: 'center',
        border: false,
        columnConfig: {
          resizable: true,
        },
        minHeight: 180,
        formConfig: {
          // 全局禁用vxe-table的表单配置，使用formOptions
          enabled: false,
        },
        proxyConfig: {
          autoLoad: true,
          response: {
            result: 'items',
            total: 'total',
            list: 'items',
          },
          showActiveMsg: true,
          showResponseMsg: false,
        },
        round: true,
        showOverflow: true,
        size: 'small',
      } as VxeTableGridOptions,
    });

    /**
     * 解决 vxeTable 在热更新时可能会出错的问题
     */
    vxeUI.renderer.forEach((_item, key) => {
      if (key.startsWith('Cell')) {
        vxeUI.renderer.delete(key);
      }
    });

    // 表格配置项可以用 cellRender: { name: 'CellImage' },
    vxeUI.renderer.add('CellImage', {
      renderTableDefault(renderOpts, params) {
        const { props } = renderOpts;
        const { column, row } = params;
        const src = row[column.field];
        return h(ElImage, { src, previewSrcList: [src], ...props });
      },
    });

    // 表格配置项可以用 cellRender: { name: 'CellLink' },
    vxeUI.renderer.add('CellLink', {
      renderTableDefault(renderOpts) {
        const { props } = renderOpts;
        return h(
          ElButton,
          { size: 'small', link: true },
          { default: () => props?.text },
        );
      },
    });

    // 单元格渲染：Tag。options 形如 [{ label, value, type }]，type 取 Element Plus 语义色
    vxeUI.renderer.add('CellTag', {
      renderTableDefault({ options, props }, { column, row }) {
        const value = getValue(row, column.field);
        const tagOptions = options ?? [
          { label: $t('common.enabled'), type: 'success', value: 1 },
          { label: $t('common.disabled'), type: 'danger', value: 0 },
        ];
        const item = tagOptions.find(
          (opt: Record<string, any>) => opt.value === value,
        );
        const { label: _label, ...rest } = item ?? {};

        return h(
          ElTag,
          { ...props, ...rest },
          { default: () => item?.label ?? value },
        );
      },
    });

    // 单元格渲染：状态开关，变更前可经 attrs.beforeChange 拦截
    vxeUI.renderer.add('CellSwitch', {
      renderTableDefault({ attrs, props }, { column, row }) {
        const loadingKey = `__loading_${column.field}`;

        async function onChange(newVal: any) {
          row[loadingKey] = true;
          try {
            const result = await attrs?.beforeChange?.(newVal, row);
            if (result !== false) {
              row[column.field] = newVal;
            }
          } finally {
            row[loadingKey] = false;
          }
        }

        return h(ElSwitch, {
          activeValue: 1,
          inactiveValue: 0,
          ...props,
          loading: row[loadingKey] ?? false,
          modelValue: row[column.field],
          'onUpdate:modelValue': onChange,
        });
      },
    });

    /**
     * 单元格渲染：操作按钮列。
     * options 可以是预设字符串（edit / detail / delete）或 { code, text, danger, show }；
     * 点击后回调 attrs.onClick({ code, row })。
     */
    vxeUI.renderer.add('CellOperation', {
      renderTableDefault({ attrs, options, props: rawProps }, { column, row }) {
        const defaultProps = { link: true, size: 'small', ...(rawProps ?? {}) };

        const presets: Record<string, Record<string, any>> = {
          delete: { danger: true, text: $t('common.delete') },
          detail: { text: $t('common.detail') },
          edit: { text: $t('common.edit') },
        };

        const operations: Record<string, any>[] = (
          options || ['edit', 'detail', 'delete']
        )
          .map((opt: Record<string, any> | string) => {
            if (typeof opt === 'string') {
              return presets[opt]
                ? { code: opt, ...presets[opt], ...defaultProps }
                : { code: opt, text: opt, ...defaultProps };
            }
            return { ...defaultProps, ...(presets[opt.code!] ?? {}), ...opt };
          })
          .map((opt: Record<string, any>) => {
            const resolved: Record<string, any> = {};
            Object.keys(opt).forEach((key) => {
              resolved[key] =
                typeof opt[key] === 'function' ? opt[key](row) : opt[key];
            });
            return resolved;
          })
          .filter((opt: Record<string, any>) => opt.show !== false);

        function renderBtn(opt: Record<string, any>, listen = true) {
          const { code, danger, text, ...rest } = opt;

          return h(
            ElButton,
            {
              ...rest,
              type: danger ? 'danger' : 'primary',
              onClick: listen
                ? () => attrs?.onClick?.({ code, row })
                : undefined,
            },
            { default: () => text },
          );
        }

        const align =
          column.align === 'left'
            ? 'justify-start'
            : column.align === 'center'
              ? 'justify-center'
              : 'justify-end';

        return h(
          'div',
          { class: `flex items-center gap-1 ${align}` },
          operations.map((opt) =>
            opt.code === 'delete'
              ? h(
                  ElPopconfirm,
                  {
                    title: $t('ui.actionTitle.delete', [
                      attrs?.nameTitle || '',
                    ]),
                    onConfirm: () => attrs?.onClick?.({ code: opt.code, row }),
                  },
                  { reference: () => renderBtn(opt, false) },
                )
              : renderBtn(opt),
          ),
        );
      },
    });

    // 这里可以自行扩展 vxe-table 的全局配置，比如自定义格式化
    // vxeUI.formats.add
  },
  useVbenForm,
});

export const useVbenVxeGrid = <
  T extends Record<string, any>,
  TFormValues extends FormValues = FormValues,
  TSubmitValues extends FormValues = TFormValues,
>(
  ...rest: Parameters<
    typeof useGrid<
      T,
      ComponentType,
      ComponentPropsMap,
      TFormValues,
      TSubmitValues
    >
  >
) =>
  useGrid<T, ComponentType, ComponentPropsMap, TFormValues, TSubmitValues>(
    ...rest,
  );

export type OnActionClickParams<T = Recordable<any>> = {
  code: string;
  row: T;
};

export type OnActionClickFn<T = Recordable<any>> = (
  params: OnActionClickParams<T>,
) => void;

export type * from '@vben/plugins/vxe-table';
