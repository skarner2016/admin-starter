# AGENTS.md

本文件是本仓库的**唯一 AI 协作约定入口**。任何在本仓库工作的 Agent（或人）在动手前必须先读本文件。

本仓库是一个**中后台管理系统起手模板**：

- **前端**：基于 [Vue Vben Admin](https://github.com/vbenjs/vue-vben-admin) 5.x 的 pnpm monorepo（**只保留 `web-ele` 应用**）。
- **后端**：`apps/api` 下的 **Laravel 12** 应用，为前端提供 REST 接口。

> **核心原则：不要重复造轮子。** 动手前先在本仓库的 `packages/*`、Laravel 官方能力、`playground` 示例里找现成实现。**禁止**另起炉灶写第二套请求库、权限系统、表单/表格封装、组件库适配层或自研 JWT/鉴权。

---

## 1. 仓库结构

```
admin-starter/
├── apps/
│   ├── web-ele/          # ★ 前端主应用（Element Plus 版 vben admin）
│   └── api/              # ★ 后端 Laravel 应用（当前为未提交的骨架，需实现业务接口）
├── packages/
│   ├── @core/
│   │   ├── base/         # 底层能力：design / icons / shared / typings
│   │   ├── ui-kit/       # 无业务 UI 组件：form-ui / layout-ui / menu-ui / popup-ui / tabs-ui / shadcn-ui
│   │   ├── composables/  # 通用组合式函数
│   │   └── preferences/  # 偏好设置内核
│   ├── effects/          # 面向业务的实现：access / common-ui / hooks / layouts / plugins / request
│   ├── constants/ icons/ locales/ preferences/ stores/ styles/ types/ utils/
├── internal/             # 工程化配置：lint-configs / node-utils / tailwind-config / tsconfig / vite-config
├── scripts/              # turbo-run / vsh / clean.mjs / deploy
├── playground/           # ★ 全量示例应用（组件、表单、表格、权限、请求的参考实现，优先照抄）
├── docs/                 # VitePress 文档站（中文约定最全，改代码前先查这里）
├── turbo.json / pnpm-workspace.yaml / lefthook.yml
└── AGENTS.md
```

**重要：本仓库是从上游 vue-vben-admin 裁剪而来。** `apps/backend-mock`、`apps/web-antd`、`apps/web-naive`、`apps/web-tdesign`、`apps/web-antdv-next`、`.github/*` 已从工作区移除。**不要试图恢复这些目录**，也不要按上游文档去改这些 app。

---

## 2. 技术栈

### 2.1 前端（以 vben admin 为基线，不要换栈）

| 维度 | 选型 | 说明 |
| --- | --- | --- |
| 框架 | Vue 3.5 + TypeScript 6 | `<script setup>` + Composition API |
| 构建 | Vite 8 + Turborepo | 配置统一走 `internal/vite-config` |
| 包管理 | pnpm 11（`packageManager: pnpm@11.16.0`） | **禁止 npm/yarn**，`preinstall` 有 `only-allow pnpm` 守卫 |
| Node | `^22.18.0 || ^24.12.0` | 见 `package.json` 的 `engines` |
| UI 组件库 | **Element Plus** | 通过 `src/adapter/component` 适配到 vben 体系 |
| 状态 | Pinia | `@vben/stores` + 应用内 `src/store` |
| 路由 | Vue Router 5 | 文件即路由，见 §3.3 |
| 样式 | Tailwind CSS 4 + 原生 CSS 变量 | 组件库样式已被 `@layer el` 包裹，工具类可覆盖 |
| 请求 | axios，封装于 `@vben/request` | 应用层配置在 `src/api/request.ts` |
| i18n | vue-i18n 11 | `src/locales/langs/{zh-CN,en-US}` |
| 依赖版本 | **统一用 `catalog:`** | 版本集中在 `pnpm-workspace.yaml` 的 `catalog` 段，**禁止在子包写死版本号** |

### 2.2 后端（Laravel 12）

| 维度 | 选型 |
| --- | --- |
| 框架 | **Laravel 12**（slim skeleton：`bootstrap/app.php` 统一配置，无 `Http/Kernel.php`） |
| 语言 | PHP `^8.2` |
| 数据库 | 默认 MySQL |
| 队列/缓存/会话 | redis driver（开箱即用，无需额外中间件） |
| 格式化 | Laravel Pint |
| 测试 | PHPUnit 11 |

> **版本基线**：后端固定为 **Laravel 12**（`composer.json` 约束 `laravel/framework: ^12.0`，`composer.lock` 锁定 **v12.70.0**）。新代码一律按 Laravel 12 的写法写，不要沿用 Laravel 11 及更早版本的过时 API。

> `apps/api` 目前是**纯骨架**：只有 `routes/web.php`、默认 `User` 模型与 3 个初始 migration，**没有任何 API 路由、没有安装 Sanctum**。业务接口需要按 §4 实现。

---

## 3. 前端开发约定

### 3.1 常用命令（在仓库根目录执行）

```bash
pnpm install            # 安装依赖（必须 pnpm）
pnpm dev:ele            # 启动 web-ele 开发服务（默认端口 5777）
pnpm dev:play           # 启动 playground 全量示例
pnpm build:ele          # 构建 web-ele
pnpm build              # 构建全部（turbo）
pnpm lint               # oxlint + eslint + stylelint
pnpm format             # oxfmt 格式化
pnpm check              # 循环依赖 + 依赖检查 + 类型检查 + cspell
pnpm check:type         # 仅类型检查（turbo run typecheck）
pnpm test:unit          # vitest 单测
pnpm test:e2e           # playwright e2e
pnpm changeset          # 生成变更集（改动 packages/* 时必做）
pnpm commit             # czg 交互式提交（遵循 Conventional Commits）
```

### 3.2 应用目录约定（`apps/web-ele/src`）

```
src/
├── api/            # 接口层：request.ts（请求实例）+ core/（按模块拆分的接口函数）
├── adapter/        # 组件库适配：component/index.ts、form.ts、vxe-table.ts
├── layouts/        # 应用级布局
├── locales/        # i18n，langs/{zh-CN,en-US}
├── router/         # routes/{core,modules}、guard.ts、access.ts
├── store/          # 应用级 Pinia store（auth.ts 等）
├── views/          # 页面
├── bootstrap.ts    # 应用启动装配（组件适配 → i18n → pinia → 权限 → 路由 → 挂载）
├── main.ts         # 偏好设置初始化 + 动态加载 bootstrap
└── preferences.ts  # 项目级偏好覆盖（defineOverridesPreferences）
```

- **路径别名**：`#/*` → `apps/web-ele/src/*`（见 `package.json` 的 `imports`）。应用内引用一律用 `#/`。
- **跨包引用**：一律用 `@vben/xxx` 工作区包，不要跨应用相对路径引用。

### 3.3 路由与菜单（文件即路由）

- 路由文件放 `src/router/routes/modules/*.ts`，构建期用 `import.meta.glob` 自动聚合 → **同时自动生成菜单**。
- 只有顶级路由 `path` 以 `/` 开头；`children` 用相对片段（如 `about`）。父级未写 `redirect` 时框架自动补到第一个子路由。
- 权限用 `meta.authority`（字符串或数组）；无需权限的页面不写该字段。
- 核心路由（登录、404、根布局）在 `src/router/routes/core`，**业务页面不要放这里**。
- 详细规范见 `docs/src/guide/essentials/route.md`。

### 3.4 请求层与响应契约（务必对齐后端）

- 请求实例：`apps/web-ele/src/api/request.ts`，导出 `requestClient`（`responseReturn: 'data'`）与 `baseRequestClient`。
- `baseURL` 来自 `VITE_GLOB_API_URL`；已内置请求头 `Authorization: Bearer <token>`、`Accept-Language`。
- **统一响应包**（`defaultResponseInterceptor` 按此解析，`successCode: 0`）：

  ```json
  { "code": 0, "data": {}, "message": "ok", "error": null }
  ```

  非 `0` 视为业务错误；HTTP 401 触发 `authenticateResponseInterceptor`（刷新 token 或登出）。
- 接口函数按模块放 `src/api/core/*.ts`，例如 `auth.ts`、`user.ts`、`menu.ts`。

### 3.5 组件与 UI 约定

- **所有表单/表格/弹窗组件必须先过 `src/adapter/component/index.ts` 适配**，再用于 `@vben/common-ui` 的 `useVbenForm` / `useVbenModal` / `useVbenDrawer` / `useVbenVxeTable`。**不要绕过 adapter 直接用 `ElXxx`**，否则 schema 驱动的表单/表格会失效。
- 布局、菜单、标签页、弹窗等框架级 UI 用 `@vben/layouts`、`@vben/common-ui`、`@vben-core/ui-kit`，**不要自己写一套 Layout**。
- 图标统一走 `@vben/icons`（Iconify）。
- 样式用 Tailwind 工具类 + 主题变量，避免写死颜色；Element Plus 样式层为 `@layer el`，可被工具类覆盖。

### 3.6 权限

- 权限码来自后端 `/auth/codes`；指令 `v-access:code`（`registerAccessDirective` 已全局注册）。
- 路由级权限用 `meta.authority`。参考 `playground/src/views/demos/access`。

---

## 4. 后端开发约定（`apps/api`）

### 4.1 需实现的接口契约

前端的接口定义已固定（鉴权与用户见 `apps/web-ele/src/api/core/*.ts`，系统管理见 `apps/web-ele/src/api/system/*.ts`），后端**必须按此契约实现**，返回体遵循 §3.4 的统一响应包：

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| POST | `/auth/login` | 登录，返回 `{ accessToken }`；refresh token 写入 HttpOnly Cookie |
| POST | `/auth/refresh` | 用 Cookie 中的 refresh token 换新 accessToken（`withCredentials`）；**返回裸字符串，不套响应包** |
| POST | `/auth/logout` | 登出并清除 refresh cookie |
| GET | `/auth/codes` | 返回当前用户权限码 `string[]` |
| GET | `/user/info` | 返回当前用户信息（`UserInfo`） |
| GET | `/menu/all` | 返回当前用户的菜单/路由树（`RouteRecordStringComponent[]`，字段与 vben 路由 `meta` 对齐） |
| GET | `/system/user/list`、`/system/role/list` | 分页列表（`{ items, total }`） |
| GET | `/system/menu/list`、`/system/dept/list` | **树数组，不分页**（`children` 嵌套，`id` 一律字符串） |
| GET | `/system/login-log/list` | 登录日志分页；**读也需要权限码** `System:LoginLog:View`（日志含 IP/UA） |
| POST/PUT/DELETE | `/system/{user,role,menu,dept}`（+ `/system/user/{id}/password`） | 系统管理增删改；写操作按按钮权限码校验，见 §3.6 |
| POST | `/system/{user,role}/batch-delete` | 批量删除，`{ ids: string[] }`；逐条应用单条删除的护栏，返回 `{ deleted, failed: [{id,message}] }` |
| POST | `/upload` | 图片上传（登录即可），返回 `{ name, size, url }`，`url` 形如 `/storage/...` |
| PUT | `/user/profile`、`/user/password` | 个人中心：改自己的资料 / 改密（当前会话保留，其它令牌失效） |

> 上述路径与响应结构参考自上游被移除的 `apps/backend-mock`。该目录已不在 `HEAD`（"project init" 提交中裁剪掉），需从裁剪前的提交取回，例如：`git show 151d80f9e:apps/backend-mock/api/auth/login.post.ts`。**这些 mock 已删除，前端开发环境需改为代理到 `apps/api`（见 §5）。**

### 4.2 Laravel 约定（优先用官方能力，不要自研）

- **鉴权**：用官方 **Laravel Sanctum** 做 API Token / SPA Cookie 鉴权，**不要自研 JWT**。安装后按 Sanctum 文档配置 `routes/api.php` 与中间件。
- **路由**：API 路由放 `routes/api.php`（Laravel 12 需在 `bootstrap/app.php` 的 `withRouting(api: ...)` 中显式注册）。
- **控制器**：`app/Http/Controllers/Api/` 下按模块拆分；请求校验用 **FormRequest**，不要手写 `if` 校验。
- **响应**：用 **API Resource**（`app/Http/Resources`）统一出参；用全局 `withExceptions` 或中间件保证错误也符合统一响应包。
- **数据**：Eloquent 模型 + **migration**（`php artisan make:migration`），**不要手写 SQL 建表**；填充用 Seeder/Factory。
- **CORS**：跨域用 Laravel 内置的 CORS 配置（`config/cors.php` / `HandleCors`），不要手写响应头。
- **队列/任务**：用 Laravel Queue + Job，不要写自研后台进程。
- **格式化**：提交前跑 `./vendor/bin/pint`。
- **测试**：`php artisan test`（PHPUnit），Feature 测试覆盖接口契约。

### 4.3 命名规则（强制）

| 类型 | 规则 | 示例 |
| --- | --- | --- |
| Model | `XxxModel`，置于 `app/Models/` | `AdminUserModel`、`LoginLogModel`、`DeptModel` |
| Service | `XxxService`，置于 `app/Services/` | `AdminUserService`、`AuthService` |
| Trait | `XxxTrait`，置于 `app/Helpers/Traits/` | `ApiResponseTrait` |

- **Model 必须显式声明 `$table`**：`AdminUserModel` 这类名字不符合 Laravel 的单数推导规则，不声明会被推导成 `admin_user_models`。例如 `AdminUserModel` 内写 `protected $table = 'admin_users';`。
- 数据表统一加 `admin_` 前缀（如 `admin_users`、`admin_roles`、`admin_login_logs`），与 `laravel-permission` 在 `config/permission.php` 中配置的表名保持一致。

### 4.4 后端常用命令（在 `apps/api` 下）

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link          # 头像等上传文件对外访问（/storage/...）
php artisan serve                 # 默认 http://127.0.0.1:8000
php artisan test
./vendor/bin/pint
```

---

## 5. 前后端联调

1. 后端启动：`cd apps/api && php artisan serve`（默认 `127.0.0.1:8000`）。
2. 前端接口地址：`apps/web-ele/.env.development` 中 `VITE_GLOB_API_URL=/api`。
3. 开发代理：`apps/web-ele/vite.config.ts` 的 `server.proxy`。当前已指向 `http://127.0.0.1:8000`，并**不做 rewrite**（Laravel 的 `routes/api.php` 自带 `/api` 前缀，前后端路径天然一致）。
   另有一条 `/storage` 代理，用于上传文件（见 §4.4 的 `storage:link`）。

4. `apps/web-ele/.env.development` 中 `VITE_NITRO_MOCK=false`（mock 应用已删除），`VITE_GLOB_API_URL=/api`。
5. 生产：`VITE_GLOB_API_URL` 会在打包时注入 `_app-config-{version}-{hash}.js`，可打包后动态改接口地址，无需按环境多次打包。

---

## 6. 质量门禁与提交规范

- **Git hooks（lefthook）**：commit 时自动跑 lint/format/commitlint，勿用 `--no-verify` 绕过。
- **提交信息**：Conventional Commits（`feat:` / `fix:` / `chore:` 等），用 `pnpm commit`。
- **类型**：提交前 `pnpm check:type` 必须通过。
- **拼写**：`cspell` 会校验 `**/*.ts` 与 README；新增专有名词请加入 `cspell.json`。
- **依赖边界**：`pnpm check:dep` 校验包依赖规则；跨包引用只能走 `@vben/*` 声明依赖，勿隐式引用。
- **变更集**：改动 `packages/*` 必须 `pnpm changeset`。
- **代码风格**：oxlint + oxfmt（主）+ eslint/stylelint（补齐），不要手工调整格式。

---

## 7. 「不要重复造轮子」清单

写代码前，先按下表确认是否已有现成能力：

| 需求 | 直接复用 |
| --- | --- |
| HTTP 请求 / 拦截器 / token 刷新 | `@vben/request` + `apps/web-ele/src/api/request.ts` |
| 表单（schema 驱动） | `@vben/common-ui` 的 `useVbenForm` + 应用 `adapter/form.ts` |
| 表格 | `useVbenVxeTable` + 应用 `adapter/vxe-table.ts` |
| 弹窗 / 抽屉 | `useVbenModal` / `useVbenDrawer` |
| 布局 / 菜单 / 标签页 | `@vben/layouts`、`@vben-core/layout-ui`、`menu-ui`、`tabs-ui` |
| 权限（路由 + 指令 + 码） | `@vben/access`、`meta.authority`、`v-access:code` |
| 状态与持久化 | `@vben/stores`（Pinia + persistedstate） |
| 主题 / 偏好设置 | `@vben/preferences` + `src/preferences.ts` |
| i18n | `@vben/locales` + `src/locales/langs/*` |
| 图标 | `@vben/icons` |
| 工具函数 / hooks | `@vben/utils`、`@vben/hooks`、`@vben-core/composables` |
| 登录/锁屏/异常页等通用页面 | `src/views/_core/*` |
| 后端鉴权 | Laravel Sanctum |
| 后端出参/校验/迁移/队列/CORS | API Resource / FormRequest / Migration / Queue / 内置 CORS |
| 参照实现 | `playground/`（全量示例）、`docs/src/guide/**`（中文规范） |

**禁止事项**：

- ❌ 引入第二套 UI 组件库（antd/naive/tdesign）——本项目只用 Element Plus。
- ❌ 绕过 `adapter` 直接用 `ElXxx` 写业务表单/表格。
- ❌ 自研请求封装、权限系统、JWT 鉴权、路由生成。
- ❌ 在子包中写死依赖版本（必须用 `catalog:`）。
- ❌ 用 npm/yarn 安装。
- ❌ 恢复已删除的上游目录（`backend-mock`、其它 `web-*` 应用）。

---

## 8. 常见任务

**新增页面**

1. `apps/web-ele/src/views/<模块>/index.vue` 编写页面。
2. `apps/web-ele/src/router/routes/modules/<模块>.ts` 注册路由（自动生成菜单）。
3. i18n：在 `src/locales/langs/{zh-CN,en-US}` 补 key，路由 `meta.title` 用 i18n key。

**新增接口**

1. 后端：`routes/api.php` 注册路由 → `Api` 控制器 → FormRequest 校验 → API Resource 出参。
2. 前端：`apps/web-ele/src/api/core/<模块>.ts` 写接口函数（类型放 `namespace`）。

**新增表单/表格页**

- 直接照抄 `playground/src/views/examples/form`、`playground/src/views/examples/vxe-table`。

**改动 `packages/*`**

- 遵循包依赖边界（`pnpm check:dep`），补 changeset，跑 `pnpm check`。

---

## 9. 已知待办 / 陷阱

1. **加系统菜单不要重跑 `MenuSeeder`**：它开头会 `delete` 掉全部 `admin_menus` 与 `admin_role_menu`，等于回滚管理员在后台维护的菜单与角色授权。新增模块请照 `database/seeders/DeptMenuSeeder.php` / `LoginLogMenuSeeder.php` 写幂等 seeder（按 `name` 定位 + 只追加授权）。
2. **数据范围（按部门限制可见数据）未实现**：部门目前是组织树 + 用户筛选维度 + 停用语义，角色权限完全由菜单树决定。若要做数据权限，见 `docs/dept-module.md` §4.1 方案 B。
3. **backend 模式下静态路由是死代码**：`accessMode=backend`（`src/preferences.ts`）时 `src/router/routes/modules/*.ts` 会被 `/menu/all` 的结果整体替换。加页面只需插 `admin_menus` 行 + 建 `views/**` 文件，**不要**改路由模块文件。
4. **`web-ele` 是唯一前端应用**：所有前端需求都在 `apps/web-ele` 落地，不要新建 app，除非明确要求。
5. **本地 `.env` 与 `.env.example` 不一致**：本地默认 sqlite + array 缓存，`.env.example` 是 mysql + redis，切环境时注意；上传文件需要 `php artisan storage:link`。
6. **角色 `status`、部门 `status` 是有语义的**：停用角色不贡献菜单/权限码（且 super 角色不可停用）；停用部门不能被新分配、不能作新上级（仅在值变化时校验）。改这两块逻辑前先看 `docs/system-completion.md` §2。
7. **登录日志的读接口要求权限码** `System:LoginLog:View`（全仓唯一的「读也要码」例子），不要顺手改成「登录即可」。
8. **密码策略是共用的**：`AdminUserService::PASSWORD_MIN_LENGTH` / `PASSWORD_PATTERN`，创建、管理员重置、自助改密三条路径必须一致。
9. **本机 pnpm 环境**（如遇 `ERR_PNPM_PM_ON_FAIL` / hooks 报 `Cannot find module @vben/oxlint-config/dist`）：项目 pin 的 `pnpm@11.16.0` 在 darwin-x64 没有原生二进制；可临时装到独立前缀后加入 `PATH`（`npm i -g --prefix /tmp/pnpm-11 pnpm@11.16.0`），并跑一次 `pnpm -r run --if-present stub` 补齐 lint 配置包的 `dist/`。不要用 `--no-verify` 绕过 hooks。
