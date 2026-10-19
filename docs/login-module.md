# 用户登录模块技术方案（管理后台）

> 状态：**待评审**
> 日期：2026-10-17
> 范围：`apps/api`（Laravel 12）用户登录模块 + 后端动态菜单
> 关联文档：[`AGENTS.md`](../AGENTS.md)（仓库协作约定）

> **修订记录**
> - 2026-10-17 初版。
> - 2026-10-17 修订一：登录账号改用独立的 `admin_users` 表（模型 `AdminUserModel`）；`laravel-permission` 表名加 `admin_` 前缀；统一响应改为 Trait（`app/Helpers/Traits/ApiResponseTrait.php`）。
> - 2026-10-17 修订二：`admin_users` 去掉 `email`、新增 `real_name`；`homePath` 改为后端统一返回常量。
> - 2026-10-17 修订三：登录账号列名定为 `username`（与前端请求/响应同名，零转换）。
> - 2026-10-17 修订四：**`accessMode` 定为 `backend`，前端菜单由后端动态返回**（新增 `admin_menus` 表与 `/menu/all`，需前端改 `preferences.ts` 一处）；`admin_login_logs` 本期做；删除 `dept_id`；`homePath` 硬编码不写配置；`desc` 改名 `description`；数据库定为 MySQL + Redis；`AdminUserService` + `AdminUserController`；`personal_access_tokens` 加前缀；删除 `password_reset_tokens`。
> - 2026-10-18 修订五（本版）：access / refresh 写入同一 `session_id`，**logout 即时吊销整条会话**（§4.2、§6.4）；**refresh 校验账号 `status`**，停用即拒绝续期并清理残留令牌；新增 `account.active` 中间件对已认证请求复核 `status`（§8.5）。旧「只能吊销 refresh」的结论作废。

本文档为评审存档。`docs/` 目录为 VitePress 文档包，其 `srcDir` 为 `src`，故本文件不会被发布到文档站。

---

## 1. 背景与目标

`web-ele`（`apps/web-ele`）**就是管理后台前端**，`apps/api` 是它唯一的后端。因此：

- 只有**管理端一类账号**，不存在"面向 C 端用户 + 管理后台"的双端令牌模型。
- 登录模块是后台 **RBAC 的入口**：登录成功后要立即提供角色、权限码与**菜单**，供前端生成路由与按钮级授权。
- 后台是高价值目标，安全与审计要求高于普通站点。

**目标**：在 `apps/api` 中实现登录模块，并让**前端菜单由后端动态返回**，严格满足 `apps/web-ele` 的接口契约。

**关键结论（先看这条）**：登录凭据为 **`username`**（列名、请求参数、响应字段三者同名，后端零转换）；账号存于独立的 `admin_users` 表；菜单由 `admin_menus` 表驱动、经 `/menu/all` 下发（`accessMode` 切 `backend`，需前端改 `preferences.ts` 一处）；`super` 角色**必须显式拿到全量权限码**（vben 5.x 无 super 豁免）；access / refresh 按 `session_id` 组成登录会话，登出或管理员操作可即时吊销（见 §6.4、§8.5）。

---

## 2. 范围

### 2.1 本期做

1. `php artisan install:api` 安装 Sanctum；引入 `spatie/laravel-permission`（表名加 `admin_` 前缀）。
2. 新增 `admin_users` 表（`username` / `real_name` / `avatar` / `password` / `description` / `status`），**不使用 Laravel 默认 `users` 表**。
3. 新增 **`admin_menus`** 表（自关联树）+ `admin_role_menu` 关联表，实现 `/menu/all` 按角色下发菜单树。
4. 6 个接口：`/auth/login`、`/auth/refresh`、`/auth/logout`、`/auth/codes`、`/user/info`、`/menu/all`。
5. 统一响应包（`ApiResponseTrait`）+ 统一异常 + 登录限流 + 失败锁定（5 次 / 15 分钟）+ `admin_login_logs` 登录审计。
6. 环境切到 **MySQL + Redis**（`.env` / `config/database.php`）。
7. Seeder（`admin_users` + 角色/权限 + 菜单树）+ Feature 测试。
8. 修正前端联调配置（`vite.config.ts` 去掉 `rewrite`、改 target；`.env.development` 关掉 mock）。

### 2.2 本期需前端配合的改动（一处）

**把菜单来源切到后端**：`apps/web-ele/src/preferences.ts` 增加 `app.accessMode: 'backend'`。

`packages/@core/preferences/src/config.ts` 的默认值是 `frontend`，web-ele 未覆盖；不改这一行，`/menu/all` 永远不会被调用（`fetchMenuListAsync` 是死代码，见 §4.3）。

```ts
// apps/web-ele/src/preferences.ts
export const overridesPreferences = defineOverridesPreferences({
  app: {
    accessMode: 'backend',   // 新增
    name: import.meta.env.VITE_APP_TITLE,
  },
  copyright: appCopyrightPreferences,
});
```

> 仅改 `apps/web-ele`，**不涉及 `packages/*`，无需 changeset**。其余前端文件一律不动（登录表单已用 `username`，响应字段也已对齐）。

### 2.3 本期不做（列第二期）

- 首次登录强制改密（`must_change_password` → 需要前端改密页）。
- 服务端验证码校验（前端已有滑块组件，需把 challenge 提交后端）。
- TOTP 二次验证。
- 移除登录页 demo 用的 `MOCK_USER_OPTIONS` 账号选择器（`login.vue` 中会自动填 `123456`，**生产必须移除**）。
- **菜单管理 CRUD**（`/system/menu/*` 增删改）：本期只建表 + Seeder + `/menu/all`，管理界面留给后续（见 §14 待确认二）。
- **Seeder 演示账号的环境守卫**：本期不加，见 §14 待确认三。

### 2.4 明确不做

- `/auth/register`（后台无自助注册）。
- `/auth/forgot-password`（改由管理员重置密码）。
- `/system/*` 系统管理列表（依赖用户管理模块，另行规划）。

---

## 3. 现状与硬约束

| 事实 | 影响 |
| --- | --- |
| `apps/api` 是纯骨架：无 `routes/api.php`、未装 Sanctum | 需先跑 `php artisan install:api` |
| `bootstrap/app.php` 未注册 `api:` 路由 | 同上，该命令会自动补 |
| 默认 `users` 表 / `User` 模型 / `UserFactory` / `config/auth.php` provider 均为脚手架产物 | 本期改用 `admin_users`，需一并清理（见 §7.1） |
| `accessMode` 默认 `frontend`，web-ele 未覆盖 | **必须改为 `backend`** 才能启用后端菜单（见 §2.2、§4.3） |
| 前端登录表单字段名已是 `username`（`login.vue` / `LoginParams`） | 列名定为 `username` 后无需改前端 |
| `pageMap` 由 `import.meta.glob('../views/**/*.vue')` 生成 | 后端 `component` 必须是能匹配到 `views/` 下真实文件的路径（见 §6.6） |
| 前端 proxy 会 `rewrite` 去掉 `/api` 前缀 | **必须修正**，否则与 Laravel `/api` 前缀冲突（见 §9） |
| `.env` 现为 sqlite + database driver | 本期切 MySQL + Redis（见 §7.5） |
| `AGENTS.md`：用 Sanctum、不重复造轮子 | 选型据此（见 §4） |

---

## 4. 技术选型与决策

| 关注点 | 选型 | 理由 |
| --- | --- | --- |
| 访问令牌 | **Laravel Sanctum**（`HasApiTokens`，opaque DB token） | 官方、可即时吊销、支持 `expires_at`；避免自研 JWT 的签名/密钥/吊销难题 |
| 刷新令牌 | **Sanctum token + `refresh` ability**，HttpOnly Cookie 下发 | 复用同一套基础设施，无需新建表；轮换 = 删旧建新 |
| 角色 / 权限码 | **`spatie/laravel-permission`**（表名 `admin_` 前缀） | 角色、权限、`/auth/codes` 全部现成，不自写 RBAC |
| 菜单 | **自建 `admin_menus` 树 + `/menu/all`**（`accessMode: backend`） | 菜单可运营；这是本次的明确要求 |
| 统一响应 / 异常 | 自建 **Trait** `App\Helpers\Traits\ApiResponseTrait` + `withExceptions` | 轻量，无魔法包装；控制器直接 `use` |
| 限流 / 锁定 | 内置 `RateLimiter` + 登录失败锁定（5 次 / 15 分钟） | 防暴力破解 |

### 4.1 为什么不用 JWT

旧 mock 用 JWT。但前端登出走 Cookie、**不携带 Bearer**（见 §6.4），后端只能靠落库的令牌记录定位并吊销，而 JWT 无法即时吊销。Sanctum 的 opaque token 天然可吊销，且 `expires_at` 能表达短时效，故弃 JWT。

### 4.2 令牌模型

登录时签发两个 Sanctum token：

- **access**：ability `access`，TTL **60 分钟**。
- **refresh**：ability `refresh`，TTL **7 天**，写 HttpOnly Cookie。

两者写入同一 `session_id`（`admin_personal_access_tokens.session_id`，UUID）标记为**同一次登录会话**；refresh 轮换时沿用原 `session_id`。登出凭 Cookie 里的 refresh token 定位会话，即可连带吊销同会话的 access token（见 §6.4）。

刷新时校验 refresh token 的 ability 与 `expires_at`，**轮换**（删除旧 refresh、签发新 refresh、换发新 access），可检测重放；**并校验账号 `status`**——账号被停用后拒绝刷新并清理其残留令牌。

### 4.3 菜单来源：`accessMode` 定为 `backend`（已决策）

`packages/effects/access/src/accessible.ts` 按 `preferences.app.accessMode` 三选一：

| accessMode | 菜单/路由来源 | `/menu/all` |
| --- | --- | --- |
| `frontend` | 前端静态文件 `src/router/routes/modules/*.ts`，按 `roles` + `meta.authority` 过滤 | 不调用（`fetchMenuListAsync` 死代码） |
| **`backend`（本方案）** | **后端 `/menu/all` 返回的菜单树** | **必需** |
| `mixed` | 两者合并（`mergeRoutesByName`，后端 meta 优先） | 必需 |

**决策：`backend`** —— 前端菜单完全由后端下发。配套改动见 §2.2（`preferences.ts` 加 `accessMode: 'backend'`）。

**连带影响（已核实，无实际损失）**：`v-access:role` 指令只在 `frontend` 模式生效（`packages/effects/access/src/directive.ts` 有 `accessMode === 'frontend' && arg === 'role'` 判断）。已确认 **`apps/web-ele` 中没有任何 `v-access:role` 用法**，故切换无破坏。`v-access:code` 两种模式都生效。

> 因此：**权限码（`/auth/codes`）是唯一的按钮级授权载体**，必须做扎实。

### 4.4 命名约定（本项目全局）

见 `AGENTS.md` §4.3。要点：

- **数据表**统一加 `admin_` 前缀（`admin_users`、`admin_roles`、`admin_menus`、`admin_login_logs`、`admin_personal_access_tokens`），与 `laravel-permission` 的表名前缀一致。
- **模型**以 `Model` 结尾（`AdminUserModel`、`MenuModel`、`LoginLogModel`、`PersonalAccessTokenModel`），置于 `app/Models/`；因不符合 Laravel 单数推导，**必须显式声明 `$table`**。
- **Service** 以 `Service` 结尾（`AdminUserService`、`MenuService`）；**Trait** 以 `Trait` 结尾（`ApiResponseTrait`）；**Controller** 以 `Controller` 结尾（`AuthController`、`AdminUserController`、`MenuController`）。

---

## 5. 权限模型与前端契约

### 5.1 前端授权语义（已核对源码，务必遵守）

- `hasAccessByRoles(roles)`：与 `userStore.userRoles` 取**交集**，无 `super` 豁免。
- `hasAccessByCodes(codes)`：与 `accessStore.accessCodes` 取**交集**。
- **空 `codes` ≠ 全放行**：`accessStore.accessCodes` 为空时，页面上**所有** `v-access:code` 按钮全部隐藏。

**推论（重要）**：

1. `super` 角色必须**显式返回全量权限码**（后端聚合所有 permission），不能指望前端豁免。
2. `/user/info` 的 `roles` 必须准确（`userStore.userRoles` 依赖它）。

### 5.2 契约映射

| 接口 | 返回 | 前端用途 |
| --- | --- | --- |
| `/user/info` | 用户资料 + `roles` | `useUserStore.userRoles`、`useUserStore.userInfo` |
| `/auth/codes` | 权限码 `string[]` | `useAccessStore.accessCodes`、`v-access:code` |
| `/menu/all` | 菜单路由树 | `generateRoutesByBackend` → 动态路由 + 菜单 |

---

## 6. 接口契约（必须逐字对齐前端）

统一响应包（前端 `defaultResponseInterceptor` 的 `successCode: 0`）：

```json
{ "code": 0, "data": {}, "message": "ok", "error": null }
```

### 6.1 `POST /api/auth/login`

- 请求：`{ "username": "vben", "password": "123456", "captcha": true }`
  - 后端**不做参数转换**：`LoginRequest` 直接读 `username`，查询 `admin_users.username`。
  - `captcha` 与 `selectAccount` 是前端登录页已有字段（`SliderCaptcha` / demo 选择器）。FormRequest 默认忽略未声明字段，**不要**因多余字段返回 422。
- 成功 `200`：`{ code: 0, data: { accessToken: "..." }, ... }`，同时 `Set-Cookie` 下发 refresh token（HttpOnly）。
- 失败：`422`（校验）/ `401`（账号或密码错误）/ `403`（账号禁用）/ `423`（账号锁定），body 为统一包且 `code != 0`。

### 6.2 `GET /api/auth/codes`（需 `auth:sanctum`）

- 成功：`data: ["AC_100100", ...]`（`string[]`）。
- 来源：spatie permissions；`super` 角色返回**全量**权限码（见 §5.1）。

### 6.3 `POST /api/auth/refresh`

- **无 Bearer**（access 已过期），仅靠 Cookie（前端 `withCredentials: true`）。
- **⚠️ 返回体是裸 token 字符串，不套响应包**。前端 `refreshTokenApi` 用 `baseRequestClient`（无拦截器）直接取 `resp.data` 当 token：

  ```ts
  const resp = await refreshTokenApi();
  const newToken = resp.data;   // 期望 body 就是 token 字符串
  ```

- 成功 `200`：body 为 `"<newAccessToken>"`，并 `Set-Cookie` 轮换后的 refresh token。
- 失败：`401`（无 Cookie / 令牌无效或过期 / 重放）。

### 6.4 `POST /api/auth/logout`

- **前端同样不携带 Bearer**（走 `baseRequestClient`）→ 只能靠 Cookie 定位用户。
- 成功：`200` 统一包（`data: ''`），清除 Cookie 并吊销**整个登录会话**（`session_id` 相同的 access + refresh 一起删）。
- 说明：access token 虽未被出示，但它与 Cookie 里的 refresh token 共享 `session_id`，因此**能即时吊销**，不必再依赖 60 分钟 TTL 兜底。吊销粒度是会话级：只影响本次登录，其它设备的会话不受影响（见 §8.5）。
- 迁移前签发、没有 `session_id` 的老令牌退化为只吊销 refresh 自身。

### 6.5 `GET /api/user/info`（需 `auth:sanctum`）

- 成功：`data` 返回 `{ userId, username, realName, avatar, roles, homePath, description }`
- **字段映射**（`AdminUserResource`）：`username` → `username`（同名）、`real_name` → `realName`、`description` → `description`。
- **`homePath` 硬编码在 Resource 里，取固定值 `/dashboard/workspace`**，不落库、不写配置、不按用户计算。
- `userId` 用 `userId` 而非旧 mock 的 `id`，以贴合 `BasicUserInfo.userId: string`。
- 前端 `BasicUserInfo`（`packages/@core/base/typings/src/basic.d.ts`）要求 `username`；`profile.vue`、`layouts/basic.vue` 会直接读 `userInfo.username`。
  > 注：`UserInfo.desc`（`packages/types/src/user.ts`）是前端类型里的遗留字段，**全仓库无任何运行时读取**；本方案响应用 `description`，不影响运行。

### 6.6 `GET /api/menu/all`（需 `auth:sanctum`）

返回类型 `RouteRecordStringComponent[]`（vben 路由树，**不是**菜单管理表结构）。响应 `data` 为树，示例：

```json
[
  {
    "name": "Dashboard",
    "path": "/dashboard",
    "redirect": "/dashboard/workspace",
    "meta": { "icon": "lucide:layout-dashboard", "order": -1, "title": "page.dashboard.title" },
    "children": [
      {
        "name": "Workspace",
        "path": "workspace",
        "component": "/dashboard/workspace/index",
        "meta": { "icon": "carbon:workspace", "title": "page.dashboard.workspace" }
      }
    ]
  }
]
```

**硬性约束**（均已核对 `packages/utils/src/helpers/generate-routes-backend.ts`）：

1. **每个节点必须有 `name`**。`convertRoutes` 对缺失 `name` 会 `console.error('route name is required')`。
2. **`component` 是相对 `views/` 的路径字符串**，解析器 `normalizeViewPath` 会去掉开头的 `./`/`../` 与 **`/views`** 前缀，并自动补 `.vue`。所以 `/dashboard/workspace/index` 与 `/views/dashboard/workspace/index` **都能解析**（旧 mock 两种写法混用，无碍）；推荐统一写成**不带 `/views` 前缀**的形式。
3. **`component` 解析失败会回退到 `/_core/fallback/not-found.vue`** 并打错误日志。上线前务必核对每个 `component` 能匹配到 `views/` 下的真实文件。
4. **顶级目录（有 children）可省略 `component`**：`generateAccessible` 会删掉父级 component 并把节点挂到根布局（`BasicLayout`）下。也可显式给 `BasicLayout`（在 `layoutMap` 中）。
5. `meta.title` 用 **i18n key**（如 `page.dashboard.title`），前端 `$t()` 渲染。
6. `meta.menuVisibleWithForbidden: true` 的节点会被替换为 403 组件（用于"可见但无权限"的菜单）。
7. **只返回 `status=1` 且 `type != 'button'` 的节点**；按钮类节点（`type=button`）不进菜单树，其 `auth_code` 通过 `/auth/codes` 下发。
8. 按当前用户角色过滤：`admin_role_menu` 决定角色可见菜单；`super` 返回全部启用菜单。

---

## 7. 数据模型

### 7.1 新增 `admin_users` 表（替代默认 `users`）

**不使用 Laravel 默认的 `users` 表**。模型 `AdminUserModel`（`app/Models/AdminUserModel.php`，`protected $table = 'admin_users'`）。

- 新增迁移 `database/migrations/xxxx_create_admin_users_table.php`。
- 从默认迁移 `0001_01_01_000000_create_users_table.php` 中**移除 `users` 表与 `password_reset_tokens` 表**；**保留 `sessions`**。
- 同步清理引用默认 `User` 的脚手架：`app/Models/User.php`、`database/factories/UserFactory.php`、`database/seeders/DatabaseSeeder.php`、`config/auth.php` 的 provider（指向 `AdminUserModel`）。

| 字段 | 说明 |
| --- | --- |
| `id` | 主键 |
| `username` | unique，**登录凭据**（前端以 `username` 提交，后端不转换） |
| `real_name` | 昵称，响应映射为 `realName` |
| `avatar` | 头像 URL，可空 |
| `password` | bcrypt hash |
| `description` | 备注信息，供用户列表展示；响应字段同名 |
| `status` | `1` 启用 / `0` 禁用 |
| `timestamps` | |

> **无 `email`**：登录凭据为 `username`，本期不需要邮箱；找回密码改由管理员重置。
> **无 `home_path`**：`homePath` 硬编码在 `AdminUserResource`（见 §6.5）。
> **无 `dept_id`**：本期无部门模块，已删除（原为数据权限预留）。
> **`description` 取代原 `desc`**：`DESC` 是 SQL 保留字，改名同时消除了保留字隐患。

### 7.2 新增 `admin_menus` 表（菜单树，驱动 `/menu/all`）

| 字段 | 说明 |
| --- | --- |
| `id` | 主键 |
| `pid` | 父菜单 id，顶级为 `0`（自关联树） |
| `name` | 路由 `name`，**必填且唯一**（见 §6.6 约束 1） |
| `path` | 路由 path；顶级以 `/` 开头，子级用相对片段（如 `workspace`） |
| `component` | 组件路径字符串，相对 `views/`（如 `/dashboard/workspace/index`）；`type=catalog` 可空 |
| `type` | `catalog`（目录）/ `menu`（页面）/ `button`（按钮权限点） |
| `auth_code` | 仅 `type=button` 使用：按钮权限码，与 spatie permission 名对齐 |
| `icon` | 图标（Iconify 名） |
| `sort` | 排序，映射为 `meta.order` |
| `status` | `1` 启用 / `0` 禁用（`/menu/all` 过滤掉禁用项） |
| `meta` | JSON：其余 meta 字段（`keepAlive`、`affixTab`、`badge*`、`authority`、`menuVisibleWithForbidden`、`hideInMenu` 等） |
| `timestamps` | |

**`admin_role_menu`**（关联表）：`role_id` + `menu_id`（复合主键）。

> `meta.title` **统一放在 `meta` JSON 里**（i18n key，如 `page.dashboard.title`），表中不单列 `title` 字段（已决策）。

### 7.3 新增其他表

- **`admin_personal_access_tokens`**：Sanctum 令牌表**加 `admin_` 前缀**。做法：
  - 自定义模型 `PersonalAccessTokenModel`（`app/Models/PersonalAccessTokenModel.php`，`protected $table = 'admin_personal_access_tokens'`）；
  - 在 `AppServiceProvider::boot()` 中 `Sanctum::usePersonalAccessTokenModel(PersonalAccessTokenModel::class);`
  - 发布并**修改 Sanctum 迁移里的表名**（`Schema::create('admin_personal_access_tokens', ...)`）。
- **`spatie/laravel-permission`**：表名加 `admin_` 前缀。该包没有单一 "prefix" 开关，而是在 `config/permission.php` 的 `table_names` 中逐张指定：
  `admin_roles` / `admin_permissions` / `admin_model_has_roles` / `admin_model_has_permissions` / `admin_role_has_permissions`。
  ⚠️ 已发布的迁移文件在**运行时**读取该配置，所以必须在执行 `php artisan migrate` **之前**改好 `table_names`。
- **`admin_login_logs`**（见 §8.3）：`username`、`admin_user_id`（可空）、`success`（bool）、`reason`（可空）、`ip`、`user_agent`、`created_at`。

### 7.4 菜单表与授权的关系（务必对齐）

两套东西各司其职，**不要混淆**：

| 用途 | 来源 |
| --- | --- |
| 菜单/路由结构 | `admin_menus`（`type=catalog` / `menu`），经 `/menu/all` 下发 |
| 按钮级权限码 | spatie `admin_permissions`，经 `/auth/codes` 下发 |
| 角色可见哪些菜单 | `admin_role_menu` |
| 角色拥有哪些权限码 | spatie `admin_role_has_permissions` |

`admin_menus` 中 `type=button` 行的 `auth_code` 必须与 spatie permission 名**一一对应**，否则菜单管理界面显示的按钮权限与实际生效的权限码会脱节。Seeder 里两处需同源生成。

### 7.5 环境：MySQL + Redis

`.env` 与 `config/database.php` 切到：

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=admin_starter
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

> 前置条件：本地需有可用的 **MySQL** 与 **Redis** 服务，且 PHP 装了 `phpredis` 扩展（`REDIS_CLIENT=phpredis`）。若用 `predis` 需 `composer require predis/predis`。
> `SESSION_DRIVER=redis` 后，`sessions` 表不再必需，但保留无害（默认迁移里本来就有）。

### 7.6 Seeder

- `admin_users`：`vben` / `admin` / `jack`，密码统一 `123456`，`real_name` 分别 Vben / Admin / Jack。
- 角色：`super` / `admin` / `user`；权限码对齐旧 mock 的 `AC_100100` 等；**`super` 授予全部权限**（见 §5.1）。
- `admin_menus`：按旧 mock 的 `MOCK_MENUS` / `MOCK_MENU_LIST` 结构重建菜单树（Dashboard、Demos、System 等），`admin_role_menu` 按角色分配。

---

## 8. 关键实现细节

### 8.1 统一响应与异常

统一响应封装为 **Trait**：`apps/api/app/Helpers/Traits/ApiResponseTrait.php`（`namespace App\Helpers\Traits`）。控制器 `use ApiResponseTrait;` 即可，提供：

- `successResponse($data = null, string $message = 'ok', int $code = 0): JsonResponse`
- `errorResponse(string $message, int $code = -1, mixed $error = null, int $status = 200): JsonResponse`
- `paginatedResponse(...)`：出参 `{ items, total }`。

**⚠️ Trait 与全局异常处理的衔接**：`bootstrap/app.php` 的 `withExceptions` 闭包不是控制器、无法直接 `use` 该 Trait。为避免响应格式两处维护，让异常渲染类复用同一 Trait：

```php
// app/Exceptions/ApiExceptionHandler.php
final class ApiExceptionHandler
{
    use ApiResponseTrait;

    public function render(Throwable $e, Request $request): JsonResponse { /* 分派到 errorResponse */ }
}
```

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(fn (Throwable $e, Request $r) => app(ApiExceptionHandler::class)->render($e, $r));
})
```

这样"统一响应"仍只有 Trait 一处定义。

异常分派：`ValidationException`(422)、`AuthenticationException`(401)、`AuthorizationException`(403)、`ModelNotFoundException`(404)、`ThrottleRequestsException`(429)、账号锁定(423)、`Throwable`(500)，**保留真实 HTTP 状态码** —— 前端 `authenticateResponseInterceptor` 依赖 401 触发刷新。

### 8.2 登录校验与限流

1. 顺序：查 `admin_users.username` → `Hash::check` → 校验 `status` → 签发令牌。失败统一返回模糊信息，不泄露"用户是否存在"。
2. 限流：`RateLimiter::for('login', fn ($r) => Limit::perMinute(5)->by($r->input('username').$r->ip()))`，路由挂 `throttle:login`。
3. **失败锁定：连续 5 次失败锁定 15 分钟**（叠加在限流之上），锁定期间返回 `423`。失败次数基于 `admin_login_logs` 统计，**锁定期间被拦下的尝试（`reason=locked`）只记审计日志、不计入阈值** —— 否则攻击者持续探测就能把 15 分钟窗口不断右移，把账号永久锁死（DoS）。

### 8.3 登录审计日志（本期做）

`admin_login_logs` 在登录成功/失败分支写入：`username`、`admin_user_id`（可空）、`success`、`reason`、`ip`、`user_agent`、`created_at`。同时为 §8.2 的失败锁定提供计数依据。

### 8.4 Cookie 与 CORS

- Cookie 名 `vben_refresh_token`；`httpOnly=true`、`path=/`、`maxAge=7d`。
  - dev（同源经 Vite 代理）：`secure=false, sameSite=lax`。
  - 跨域生产：`secure=true, sameSite=none`。
- CORS：Laravel 12 slim 默认不发布 `config/cors.php`（框架内置默认值 = `allowed_origins: *` + `supports_credentials: false`，浏览器会拒绝「带凭据 + `*`」的组合）。本项目已补 `config/cors.php`：`supports_credentials=true`、`allowed_origins` 从 `CORS_ALLOWED_ORIGINS` 读取（英文逗号分隔，回退到本地前端 5777 端口），生产部署必须把该变量设为前端真实域名（**不能用 `*`**）。开发走 Vite 代理属同源，不触发 CORS。

### 8.5 会话吊销（后台刚需）

- **登出**：按 `session_id` 吊销该会话全部令牌（access + refresh），秒级生效，不影响其它设备。
- **管理员禁用账号 / 重置密码**：`AdminUserService::revokeAllTokens()` 吊销该用户全部令牌（access + refresh）。
- **刷新时校验 `status`**：账号被停用后 `/auth/refresh` 直接 401 并清理残留令牌，避免"禁用后仍能续期"。
- **请求级兜底**：所有 `auth:sanctum` 路由紧跟 `account.active` 中间件（`EnsureAccountIsActive`），`status !== 1` 的账号一律 401。即使令牌因直接改库等原因残留未删，也不会漏放。

由 `AdminUserService` 提供吊销方法并测试覆盖；管理入口见用户管理模块。

### 8.6 令牌清理

`routes/console.php` 加 `sanctum:prune-expired` 定时任务，防 `admin_personal_access_tokens` 膨胀。

### 8.7 不引入 CSRF / SPA stateful 模式

走纯 Bearer + 独立 Cookie，不引入 `EnsureFrontendRequestsAreStateful`，避开 CSRF 复杂度。

---

## 9. 前后端联调

**关键修正**：当前 `apps/web-ele/vite.config.ts` 的 proxy 会 `rewrite` 掉 `/api`，而 Laravel 默认给 `routes/api.php` 加 `/api` 前缀 —— 二者冲突（`/api/auth/login` 会被转发成 `/auth/login`，后端 404）。

**推荐：删掉 `rewrite`，保留 Laravel 约定前缀**，只改 target：

```ts
'/api': {
  changeOrigin: true,
  target: 'http://127.0.0.1:8000',
  ws: true,
}
```

这样前端 `VITE_GLOB_API_URL=/api` + 后端 `/api/...` 天然对齐。同时 `apps/web-ele/.env.development` 的 `VITE_NITRO_MOCK` 设为 `false`（mock 应用已删除）。

**登录时序**：`POST /api/auth/login`（提交 `username`）→ 存 accessToken、写 refresh Cookie → 并发 `GET /api/user/info` + `GET /api/auth/codes` → 跳 `homePath`（硬编码常量）→ 路由守卫再拉 `GET /api/menu/all` 生成动态路由与菜单。后续 access 过期 → 401 → 拦截器调 `/api/auth/refresh` → 重放原请求。

---

## 10. 安全考量

- 密码 bcrypt（`BCRYPT_ROUNDS=12` 已有）；refresh token 在 DB 中只存哈希（Sanctum 天然如此）。
- refresh **轮换 + 重放检测**：旧 token 再次使用即失效并（可选）吊销该用户全部会话。
- 登录限流 + 失败锁定（5 次 / 15 分钟）；access TTL 60 分钟；logout 清除 Cookie 并吊销该会话的 access + refresh（按 `session_id`）。
- **`account.active` 中间件**对每个已认证请求复核账号 `status`：停用立即失效，不依赖"停用时吊销令牌"这一入口。
- Cookie `HttpOnly`，生产 `Secure + SameSite=None`。
- 登录审计日志（§8.3）覆盖成功/失败与来源 IP/UA。
- ⚠️ Seeder 含弱口令演示账号（`123456`），**本期不加环境守卫**，投产前必须处理（见 §14 待确认三）。

---

## 11. 代码结构与文件清单

```
apps/api/
├── routes/api.php                          # 6 个路由（install:api 生成）
├── bootstrap/app.php                       # api: 路由 + withExceptions 统一异常
├── app/
│   ├── Models/
│   │   ├── AdminUserModel.php              # $table='admin_users'; +HasApiTokens, +HasRoles
│   │   ├── MenuModel.php                   # $table='admin_menus'（自关联树）
│   │   ├── LoginLogModel.php               # $table='admin_login_logs'
│   │   └── PersonalAccessTokenModel.php    # $table='admin_personal_access_tokens'
│   ├── Exceptions/ApiExceptionHandler.php  # 复用 ApiResponseTrait，供 withExceptions 调用
│   ├── Helpers/Traits/ApiResponseTrait.php # 统一响应（success/error/paginated）
│   ├── Http/
│   │   ├── Controllers/Api/AuthController.php       # login/refresh/logout/codes
│   │   ├── Controllers/Api/AdminUserController.php  # info
│   │   ├── Controllers/Api/MenuController.php       # all
│   │   ├── Middleware/EnsureAccountIsActive.php     # 别名 account.active：停用账号 401
│   │   ├── Requests/Auth/LoginRequest.php           # FormRequest 校验（读 username）
│   │   ├── Resources/AdminUserResource.php          # /user/info 出参 + 硬编码 homePath
│   │   └── Resources/MenuResource.php               # /menu/all 出参（路由树）
│   └── Services/
│       ├── AdminUserService.php            # 令牌签发/轮换/吊销 + Cookie 组装
│       └── MenuService.php                 # 菜单树构建 + 按角色过滤
├── config/{sanctum,auth,permission,database}.php   # guard + spatie 表前缀 + MySQL
└── database/{migrations,seeders}/
```

`AdminUserService` 承载令牌逻辑、`MenuService` 承载菜单树逻辑，控制器只做编排。

---

## 12. 测试方案（`php artisan test`）

Feature 测试用 `RefreshDatabase`（MySQL 测试库或 sqlite in-memory）：

- 登录成功 / 密码错 / 账号禁用 / 账号锁定（均以 `username` 提交）；
- `/auth/codes` 与 `/user/info` 未鉴权返回 401；
- refresh 成功、轮换后旧 token 失效、重放被拒；
- logout 清 Cookie 并吊销该会话的 access + refresh；只影响本会话；
- refresh 拒绝已停用账号，并清理其残留令牌；
- 停用账号后，未吊销的 access token 访问受保护接口返回 401（`account.active`）；
- 限流 429、失败锁定 423；锁定期间持续探测不会延长锁定窗口（防账号 DoS）；
- `/user/info` 返回硬编码 `homePath` 常量；
- **`/menu/all`：树形结构、`type=button` 与 `status=0` 被过滤、按角色过滤、`super` 拿全量、每个节点都有 `name`**；
- `admin_login_logs` 成功/失败均落库；
- 响应包结构与 HTTP 状态码断言；
- `AdminUserService` 会话吊销方法。

---

## 13. 实施步骤

1. `php artisan install:api`（装 Sanctum、生成 `routes/api.php`、注册 `api:` 路由）。
2. 切环境：改 `.env` 与 `config/database.php` 到 **MySQL + Redis**（§7.5）。
3. `composer require spatie/laravel-permission`；发布配置与迁移，并在**执行 `php artisan migrate` 之前**把 `config/permission.php` 的 `table_names` 改为 `admin_` 前缀。
4. Sanctum 令牌表加前缀：发布迁移改表名为 `admin_personal_access_tokens`，新建 `PersonalAccessTokenModel`，在 `AppServiceProvider` 里 `Sanctum::usePersonalAccessTokenModel()`。
5. 新增 `admin_users` / `admin_menus` / `admin_role_menu` / `admin_login_logs` 迁移与模型；从默认迁移移除 `users` 与 `password_reset_tokens`，清理 `User` 模型 / `UserFactory` / `DatabaseSeeder` / `config/auth.php` provider。
6. `ApiResponseTrait` + `ApiExceptionHandler` + `bootstrap/app.php` 异常渲染 + `RateLimiter`。
7. `AdminUserService` / `MenuService` → `AuthController` / `AdminUserController` / `MenuController` / `LoginRequest` / `AdminUserResource` / `MenuResource` → `routes/api.php`。
8. 写 Seeder（用户 + 角色/权限 + 菜单树，`super` 授全量权限）。
9. 修 `vite.config.ts`（去 rewrite、改 target）与 `.env.development`；改 `apps/web-ele/src/preferences.ts` 加 `accessMode: 'backend'`。
10. 补 Feature 测试、`sanctum:prune-expired` 定时、跑 `./vendor/bin/pint`。

---

## 14. 待确认事项

### 14.1 仍需你确认

| # | 事项 | 建议 |
| --- | --- | --- |
| 一 | **Seeder 弱口令演示账号**（`vben`/`admin`/`jack` 口令 `123456`）本期不加环境守卫，投产前必须处理 | 投产前加 `local` 守卫或删除演示账号 |

### 14.2 已决策（不再待确认）

| 事项 | 决策 |
| --- | --- |
| `accessMode` | **`backend`** —— 前端菜单由后端动态返回（需改 `preferences.ts` 一处） |
| `admin_login_logs` 登录审计 | **本期做** |
| `admin_users.dept_id` | **删除** |
| `homePath` | **硬编码 `/dashboard/workspace`**，不写配置、不落库 |
| `desc` 字段 | 改名 **`description`**，供用户列表展示备注 |
| Seeder 环境守卫 | 本期不处理 |
| access / refresh TTL | **60 分钟 / 7 天** |
| 登录失败锁定 | **连续 5 次锁 15 分钟**（锁定期间的探测不计入阈值，窗口自「最后一次真实失败」起算） |
| 数据库 | **MySQL + Redis** |
| Service / Controller 命名 | **`AdminUserService`** / **`AdminUserController`** |
| `personal_access_tokens` 前缀 | **加前缀** → `admin_personal_access_tokens` |
| `password_reset_tokens` | **删除** |
| 菜单 `title` | **并入 `meta` JSON**（与 vben 路由 `meta` 一致，表中不单列 `title`） |
| 菜单管理 CRUD | **本期不做**，放第二步（本期只建表 + Seeder + `/menu/all`） |

---

## 15. 实施补充（落地时新增的两点）

1. **`admin_menus` 不设 `redirect` 列**：父级路由的 `redirect` 由前端框架自动补到第一个子路由（`packages/effects/access/src/accessible.ts` 的 `mapTree` 逻辑），因此用 `sort` 控制首个子项即可，无需额外字段。
2. **Seeder 必须包含 `Profile` / `VbenAbout`**：`accessMode` 切到 `backend` 后，原先由静态模块 `apps/web-ele/src/router/routes/modules/vben.ts` 提供的 `/profile`、`/vben-admin/about` 不再注册。而顶栏用户下拉的「个人中心」是按**路由 name** 跳转的（`apps/web-ele/src/layouts/basic.vue` 中 `router.push({ name: 'Profile' })`），故 `Profile` 必须由后端菜单下发（带 `meta.hideInMenu: true`：可路由，但不显示在菜单里）。同理保留 `VbenAbout` 以对齐原静态菜单。

> 另注：前端测试用的 Json 请求默认**不携带 Cookie**（`MakesHttpRequests::prepareCookiesForJsonRequest()` 在未调用 `withCredentials()` 时返回空数组），编写涉及 refresh/logout 的测试时必须显式 `withCredentials()`。

---

## 附：参考

- 旧 mock 契约（已从工作区删除，可回溯历史）：
  - `git show 151d80f9e:apps/backend-mock/api/auth/login.post.ts`
  - `git show 151d80f9e:apps/backend-mock/utils/mock-data.ts` —— `MOCK_USERS` / `MOCK_CODES` / `MOCK_MENUS`（`/menu/all` 结构）/ `MOCK_MENU_LIST`（菜单管理表结构）
  - `git show 151d80f9e:apps/backend-mock/utils/response.ts` —— 响应包结构
- 前端契约定义：`apps/web-ele/src/api/core/{auth,user,menu}.ts`、`apps/web-ele/src/api/request.ts`、`apps/web-ele/src/store/auth.ts`。
- 菜单/路由解析：`packages/utils/src/helpers/generate-routes-backend.ts`（`component` 路径规范化）、`packages/effects/access/src/accessible.ts`（`accessMode` 分派）。
- 授权语义：`packages/effects/access/src/{use-access,directive}.ts`、`packages/stores/src/modules/{access,user}.ts`。
- 登录页：`apps/web-ele/src/views/_core/authentication/login.vue`。
- 命名约定：`AGENTS.md` §4.3。
- 上游规范：`docs/src/guide/in-depth/login.md`、`docs/src/guide/essentials/{route,server}.md`。

## 附：风险与前置条件

- **前置条件**：本地需可用的 **MySQL** 与 **Redis**；`REDIS_CLIENT=phpredis` 需要 PHP 装 `phpredis` 扩展（否则改用 `predis` 并 `composer require predis/predis`）。
- **`accessMode` 切 `backend` 后菜单完全依赖 `/menu/all`**：该接口挂掉会导致无菜单可显示。`component` 路径写错会静默回退到 not-found 页（仅 console 报错），上线前需逐个核对。
- `/auth/refresh` 返回裸 token 是前端硬约束，**不要**给它套响应包，否则刷新链路静默失效。
- Seeder 含弱口令演示账号，投产前必须处理（§14.1 第三条）。
- logout 的 access 即时吊销依赖 `session_id`，因此**新增迁移必须在部署时执行**（`2026_10_18_100000_add_session_id_to_admin_personal_access_tokens_table`）；迁移前签发的老令牌退化为只吊销 refresh。
