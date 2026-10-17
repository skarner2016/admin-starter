import { defineConfig, viteCssLayerPlugin } from '@vben/vite-config';

import ElementPlus from 'unplugin-element-plus/vite';

export default defineConfig(async () => {
  return {
    application: {},
    vite: {
      plugins: [
        // element-plus 的 css 包进 @layer el，使 Tailwind 工具类可覆盖组件样式
        viteCssLayerPlugin({ layerName: 'el', packageName: 'element-plus' }),
        ElementPlus({ format: 'esm' }),
      ],
      server: {
        proxy: {
          // 后端为 apps/api（Laravel），其 routes/api.php 自带 /api 前缀，故不做 rewrite
          '/api': {
            changeOrigin: true,
            target: 'http://127.0.0.1:8000',
            ws: true,
          },
          // 上传文件由 public 磁盘提供（需先 php artisan storage:link）
          '/storage': {
            changeOrigin: true,
            target: 'http://127.0.0.1:8000',
            ws: true,
          },
        },
      },
    },
  };
});
