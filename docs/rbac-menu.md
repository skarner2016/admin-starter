# RBAC 与菜单管理技术方案（第二步）

> 状态：**待评审**
> 日期：2026-10-17
> 范围：`apps/api` 角色/权限/菜单管理 + `apps/web-ele` 系统管理页面
> 前置：登录模块已完成（见 [`login-module.md`](./login-module.md)）
> 关联：[`AGENTS.md`](../AGENTS.md)

---

## 1. 背景与现状

第一步已完成并验证：`admin_users` / `admin_menus` / `admin_role_menu` / `admin_login_logs` 四表、6 个接口（登录/刷新/登出/权限码/用户信息/菜单树）、spatie 权限表（`admin_` 前缀）、动态菜单（`accessMode=backend`）、27 个测试通过。

本阶段目标：把 RBAC 与菜单**做成可运营的** —— 能在界面上增删改角色、菜单，并给角色授权。

---

## 2. 关键发现（决定架构的三点）

这三点都是读前端代码才看得出来的，直接决定本阶段怎么做：

### 2.1 前端的"权限"就是**菜单 id 数组**

`playground/src/views/system/role/modules/form.vue` 的授权控件是一棵**菜单树**：

```vue
<Tree :tree-data="permissions" multiple value-field="id" label-field="meta.title" ... />
```

数据源是 `getMenuList()`（菜单接口），选中值绑定到角色的 `permissions` 字段。旧 mock 也是这个语义：`permissions: faker.helpers.arrayElements(getMenuIds(MOCK_MENU_LIST))`。

**所以 `SystemRole.permissions: string[]` 存的是 menu id，不是权限码字符串。** 这直接决定后端要用 `admin_role_menu` 承接它（见 §4.1）。

### 2.2 系统页面在 web-ele 里**完全不存在**

`apps/web-ele/src/views/` 只有 `_core` / `dashboard` / `demos`。全部系统管理页面只在 `playground/` 里，且**是 antd 味的**，不能直接复制：

| 文件 | 需替换的依赖 |
| --- | --- |
| `system/role/modules/form.vue` | `Spin` from `antdv-next`、`DataNode` type from `antdv-next/dist/tree` |
| `system/user/data.ts` | `Tag` from `antdv-next` |
| `system/menu/modules/form.vue` | `IconifyIcon`（OK）、`$te`（OK） |

web-ele 用的是 Element Plus，必须逐个换成 `ElTag` / `ElSpin` 等，且组件要先过 `src/adapter/component` 适配（AGENTS.md §3.5）。

### 2.3 web-ele 的 vxe-table 适配器**缺三个单元格渲染器**

`apps/web-ele/src/adapter/vxe-table.ts` 只注册了 `CellImage`、`CellLink`。而系统页面用到：

- `CellTag`（状态、菜单类型）
- `CellSwitch`（状态开关）
- `CellOperation`（操作列，菜单页还用 `options: [append/edit/delete]`）

playground 的适配器里这三个都有（`playground/src/adapter/vxe-table.ts:86/105/136`）。**必须先在 web-ele 补齐，否则页面渲染即报错。**

---

## 3. 范围

### 3.1 本阶段做

1. **数据层调整**：`admin_menus` 补 `redirect` 列、`type` 扩到 5 种；`admin_roles` 补 `remark` / `status`。
2. **菜单管理接口**：`list` / `name-exists` / `path-exists` / `create` / `update` / `delete`。
3. **角色管理接口**：`list` / `create` / `update` / `delete`（含"授权"读写）。
4. **用户管理接口**：`list` / `create` / `update` / `delete`（含角色分配、重置密码）。
5. **权限桥接**：角色授权（menu id 集合）→ `admin_role_menu` + spatie permissions 同步。
6. **前端**：web-ele 新建 `src/views/system/{user,role,menu}`、`src/api/system/*`、补 vxe 渲染器、补 i18n、注册菜单。
7. **Seeder + 测试**。

### 3.2 本阶段不做

- **部门管理**（`/system/dept/*`）：第一步已删 `dept_id`，本期无部门模块（见 §4.3）。
- **数据权限 / 数据范围**：依赖部门，后续。
- **操作审计日志**（登录审计已有 `admin_login_logs`，操作日志另开）。

---

## 4. 核心决策（需你确认）

### 4.1 ⚠️ 待确认一：权限模型的桥接方式

现状是"两套"：`admin_role_menu`（管菜单显示）+ spatie（管 `/auth/codes`）。而前端只提交**一份** menu id 数组。两种接法：

**方案 A（推荐）：菜单树为唯一事实源，spatie 作为派生索引**

- `admin_role_menu` 存角色 ↔ 菜单（含 `type=button` 的按钮行）；
- 保存角色时，在同一事务内：① sync `admin_role_menu`；② 把该集合里 **button 行的 `auth_code`** 同步给 spatie `syncPermissions()`；
- 菜单 CRUD 需同步维护 spatie：新增按钮行 → `Permission::findOrCreate(authCode)`；改 `auth_code` → 重命名权限；删按钮行 → 删权限并 detach；
- `/auth/codes`、`/menu/all` **保持第一步的实现不变**（无回归）。
- 代价：`auth_code` ↔ permission 名 需要维护一致性，落在两个 Service 里（角色保存、菜单 CRUD）。

**方案 B：去掉 spatie，单一来源**

- `/auth/codes` 改为：用户角色 → `admin_role_menu` → `admin_menus` 中 `type=button` 的 `auth_code` 去重。
- 优点：只有一套授权数据，无同步负担，可删掉 spatie 5 张表。
- 代价：**与 AGENTS.md §7「用 spatie/laravel-permission」相悖**，且要改写第一步的 `/auth/codes` 与相关测试。

> **建议 A**：延续既定选型与第一步代码，把 spatie 明确降级为"派生索引"，并在 §9 加一条一致性校验命令兜底。

### 4.2 ⚠️ 待确认二：用户管理的字段契约

playground 的 `SystemUser` 是 `{ id, name, deptId, remark, status }` —— 与我们的 `admin_users`（`username` / `real_name` / `description` / `status`）**不一致**，且其表单把**部门设为必填**（`ApiTreeSelect` + `getDeptList` + `rules: 'required'`）。

而 web-ele 是唯一前端、页面由我们新建，所以：

**建议**：不复用 playground 的 `SystemUser` 形状，按 `admin_users` 定义 web-ele 自己的契约：

```
{ id, username, realName, avatar, description, status, roles: string[], createTime }
```

并去掉部门字段（部门本期不做）。

### 4.3 部门：本期不做

`/system/dept/*` 在 `AGENTS.md` §4.1 的契约表里，但第一步已按你的决定删除 `dept_id`。本期不做部门，用户表单不含部门字段。若后续要做，届时补 `admin_depts` + `dept_id`。

---

## 5. 数据模型调整

### 5.1 `admin_menus`

| 变更 | 说明 |
| --- | --- |
| **+`redirect`** | `SystemMenu` 的顶层字段（管理表单要读写）。第一步曾决定不加、靠前端自动补；但管理界面要能编辑它，**必须落列** |
| **`type` 扩到 5 值** | 管理界面是 `catalog / menu / embedded / link / button`；第一步只支持前三种与 `button`。`embedded` 用 `meta.iframeSrc`，`link` 用 `meta.link` |

> 注意：`/menu/all`（运行时菜单）仍只下发 `catalog/menu/embedded/link` 且过滤 `button`、`status=0`；`/system/menu/list`（管理用）返回**全部**类型与状态。

### 5.2 `admin_roles`（spatie 表）

| 变更 | 说明 |
| --- | --- |
| **+`remark`** | 角色备注（表单有） |
| **+`status`** | `1` 启用 / `0` 禁用（表单有，且列表有状态开关） |

spatie 的 `roles` 表可以自由加列，不影响其功能。

### 5.3 `admin_users`

无需改列。`description` 对应界面上的"备注"。

### 5.4 id 一律以**字符串**返回

前端契约里 `id` / `pid` 都是 `string`（`SystemMenu.id: string`、`SystemRole.id: string`）。后端 bigint 主键必须在 Resource 里 `(string)` 转换，否则 vxe-table 的树形/选中会出问题。

---

## 6. 接口契约

统一响应包沿用第一步（`ApiResponseTrait`）。以下路径均带 `/api` 前缀。

### 6.1 菜单管理

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/system/menu/list` | 返回**树**，含全部 `type`（含 button）与 `status=0`；字段 `id,pid,name,path,component,type,authCode,redirect,status,meta,children` |
| GET | `/system/menu/name-exists` | `?name=&id=` → `boolean`（`id` 用于编辑时排除自身） |
| GET | `/system/menu/path-exists` | `?path=&id=` → `boolean` |
| POST | `/system/menu` | 创建 |
| PUT | `/system/menu/{id}` | 更新 |
| DELETE | `/system/menu/{id}` | 删除；**存在子级时拒绝**（返回业务错误） |

> 契约来源：`playground/src/api/system/menu.ts`。

### 6.2 角色管理

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/system/role/list` | 分页 `{ items, total }`；支持 `name`/`status` 等过滤。item：`id,name,remark,status,createTime,permissions`（**permissions = menu id 数组**） |
| POST | `/system/role` | 创建，body 含 `permissions` |
| PUT | `/system/role/{id}` | 更新，body 含 `permissions` |
| DELETE | `/system/role/{id}` | 删除；**仍有用户绑定时拒绝** |

> 分页形状以 `{ items, total }` 为准（vxe 的 `proxyConfig.response` 已配置 `result: 'items'`）。前端 `getRoleList` 的类型标注写成 `Array<SystemRole>` 是松类型，不代表真实形状。

### 6.3 用户管理

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/system/user/list` | 分页 `{ items, total }`；支持 `username`/`status` 过滤 |
| POST | `/system/user` | 创建（含初始密码、角色） |
| PUT | `/system/user/{id}` | 更新（不含密码） |
| DELETE | `/system/user/{id}` | 删除；**不能删自己**、**不能删最后一个 super** |
| PUT | `/system/user/{id}/password` | 重置密码（建议；前端需配套按钮） |

> 禁用账号 / 重置密码 / 改角色时，复用第一步已实现的 `AdminUserService::revokeAllRefreshTokens()` 即时踢下线（§8.5 of login-module）。

### 6.4 保持不变的接口

`/auth/login`、`/auth/refresh`、`/auth/logout`、`/auth/codes`、`/user/info`、`/menu/all` —— **本阶段不动**（除非 §4.1 选方案 B）。

---

## 7. 前端改造（web-ele）

### 7.1 新增文件

```
apps/web-ele/src/
├── api/system/{index,user,role,menu}.ts      # 契约（按 §6 定义，去掉 dept）
├── locales/langs/{zh-CN,en-US}/system.json   # 从 playground 复制并删掉 dept 段
└── views/system/
    ├── user/{index.vue,data.ts,modules/{form.vue,detail.vue}}
    ├── role/{index.vue,data.ts,modules/form.vue}
    └── menu/{index.vue,data.ts,modules/form.vue}
```

> i18n 无需改注册逻辑：`apps/web-ele/src/locales/index.ts` 用 `import.meta.glob('./langs/**/*.json')` 自动聚合，放进去即生效。

### 7.2 移植时的替换清单（antd → Element Plus）

| 位置 | 原 | 改为 |
| --- | --- | --- |
| role 表单 | `Spin` from `antdv-next` | `ElSpin`（或 `v-loading`） |
| role 表单 | `DataNode` from `antdv-next/dist/tree` | 用 `@vben/common-ui` 的 Tree 类型或本地 interface |
| role 表单 | `Tree` from `@vben/common-ui` | 保留（与组件库无关） |
| user 详情 | `Tag` from `antdv-next` | `ElTag` |
| menu 表单 | `IconifyIcon` / `$te` | 保留 |

### 7.3 补 vxe-table 单元格渲染器（阻塞项）

在 `apps/web-ele/src/adapter/vxe-table.ts` 增加 `CellTag` / `CellSwitch` / `CellOperation`，用 Element Plus 实现（`ElTag`、`ElSwitch`、`ElButton` + 二次确认）。可参照 `playground/src/adapter/vxe-table.ts:86/105/136` 的行为契约（`options[].code`、`attrs.onClick`、`attrs.beforeChange`、`attrs.nameField`）。

### 7.4 按钮级权限码

系统页面的操作按钮用 `v-access:code` 控制，权限码沿用旧 mock 的命名：

- `System:Menu:Create` / `System:Menu:Edit` / `System:Menu:Delete`
- `System:Role:Create` / `System:Role:Edit` / `System:Role:Delete`
- `System:User:Create` / `System:User:Edit` / `System:User:Delete`

这些码由菜单表里对应的 `type=button` 行承载（§8），并经 `/auth/codes` 下发。

---

## 8. 自举问题（容易漏）

`accessMode=backend` 后**菜单完全由 `admin_menus` 下发**，所以新增的系统页面**必须自己登记进菜单表**，否则页面上根本看不到入口、也无法路由。

Seeder 需补：

```
System (catalog, /system)                      → 目录
├── SystemUser (menu, /system/user,  component=/system/user/index)
│   ├── button System:User:Create / Edit / Delete
├── SystemRole (menu, /system/role,  component=/system/role/index)
│   ├── button System:Role:Create / Edit / Delete
└── SystemMenu (menu, /system/menu,  component=/system/menu/index)
    └── button System:Menu:Create / Edit / Delete
```

`component` 必须与 §7.1 新建的 `views/system/*/index.vue` 路径一致，否则前端静默回退到 not-found（login-module §6.6 约束 3）。

---

## 9. 关键实现细节

1. **角色授权的同步事务**（方案 A）：`RoleService::syncPermissionsAndMenus(role, menuIds)` 在 `DB::transaction` 内完成 `admin_role_menu` 的 sync + spatie `syncPermissions`。任一步失败整体回滚。
2. **菜单 CRUD 与权限码一致性**：按钮行 `auth_code` 的增改删要同步 spatie permission。建议提供兜底命令 `php artisan rbac:sync-permissions`（以菜单表为准重建权限表），并在 CI/部署后可选执行。
3. **`/menu/all` 与 `/system/menu/list` 分离**：前者是运行时菜单（过滤 button/禁用、按角色），后者是管理视图（全量树）。两个 Resource，不要复用。
4. **删除的级联与保护**：
   - 删菜单：有子级拒绝；同时清理 `admin_role_menu` 引用、删除对应 permission（按钮行）。
   - 删角色：有用户绑定拒绝；清理 `admin_role_menu` + spatie `role_has_permissions`。
   - 删用户：清理其 token（`revokeAllRefreshTokens`）与 `model_has_roles`。
5. **`super` 保护**：不允许删除/停用 `super` 角色，不允许删除最后一个 `super` 用户（否则系统无法再授权）。
6. **创建/更新角色、菜单、用户**一律走 FormRequest 校验；出参一律走 Resource（统一字符串 id）。
7. **缓存**：spatie 有权限缓存，角色/权限变更后需 `permission:cache-reset`（spatie 的 `syncPermissions` 内部会处理，但自定义命令里要显式调用）。

---

## 10. 测试方案

沿用 `RefreshDatabase` + `seed()`：

- **菜单**：树形结构、`name-exists`/`path-exists` 含自身排除、创建/更新/删除、删父级被拒、按钮行过滤只影响 `/menu/all` 不影响 `/system/menu/list`。
- **角色**：分页形状 `{items,total}`、创建带 `permissions`（menu id）后 `admin_role_menu` 与 spatie 权限**双写一致**、删除有用户绑定时被拒、`super` 不可删。
- **用户**：分页、创建/更新/删除、角色分配、重置密码后旧 refresh token 失效、不能删自己、不能删最后一个 super。
- **端到端一致性**：给角色勾选某按钮菜单 → 该角色用户 `/auth/codes` 立即包含对应 `auth_code`（验证 §4.1 的桥接）。
- **`rbac:sync-permissions`**：手工制造不一致后执行命令，断言恢复一致。

---

## 11. 实施步骤

1. 迁移：`admin_menus` 加 `redirect`、`type` 语义扩展；`admin_roles` 加 `remark`/`status`。
2. 模型/Resource：`MenuModel` 支持 5 类型；新增 `RoleModel`（spatie Role 子类或直接用）、`AdminRoleResource`、`SystemMenuResource`（管理视图）。
3. `MenuService` 扩展（管理树）+ 新增 `RoleService`、`AdminUserManageService`。
4. FormRequest + Controller（`System/MenuController`、`RoleController`、`UserController`）+ 路由。
5. `rbac:sync-permissions` 命令。
6. Seeder：System 目录 + 三页面 + 按钮权限行；`admin_roles` 的 `remark`/`status`。
7. 前端：补 vxe 渲染器 → 补 `api/system/*` + i18n → 移植三个页面（Element Plus 化）。
8. 测试 + `pint` + 前端 `pnpm check:type`。

---

## 12. 待确认事项

| # | 事项 | 建议 |
| --- | --- | --- |
| 一 | **权限模型桥接**：方案 A（菜单树为源、spatie 派生）还是方案 B（去 spatie、单一来源） | **A**（延续 AGENTS.md 与第一步代码） |
| 二 | **用户管理字段契约**：按 `admin_users` 自定义（`username`/`realName`/`description`）还是沿用 playground 的 `name`/`deptId`/`remark` | **自定义**（且不含部门） |
| 三 | **重置密码接口**是否本期做 | 做（`PUT /system/user/{id}/password`） |
| 四 | 前端页面移植是否本期一次做完（三个页面 + 渲染器 + i18n） | 是；但工作量大，也可先做**菜单 + 角色**、用户管理放下一批 |

---

## 13. 实施记录（落地时相对本计划的偏差）

均已实现并验证（后端 49 个测试通过、pint 通过、全新 migrate/seed 通过、`vue-tsc` 通过）。以下是与本计划的差异，需评审确认：

1. **权限模型取方案 A 并已落地**：`admin_role_menu` 为唯一事实源；spatie 权限由该集合里 `type=button` 行的 `auth_code` **派生同步**（`RoleService::syncMenusAndPermissions`）。实测：9 个按钮菜单 → 9 个权限码；super/admin 各 21 菜单 + 9 码，user 5 菜单 + 0 码。
2. **新增了服务端权限校验（计划外）**：写操作挂了 `permission:System:{Menu,Role,User}:{Create,Edit,Delete}` 中间件。计划只要求前端 `v-access:code`，但管理端 CRUD 若只有前端校验等于没有授权，故补上。读操作仍只要求登录（若要细粒度控制读权限，需再增权限码）。
3. **新增 `Gate::before` 放行 super（计划外）**：`/auth/codes` 本就对 super 返回全量权限，若不加这条，super 会出现"按钮看得见、接口 403"的不一致。
4. **`admin_menus` 结构调整**：新增 `redirect` 列；`path` 改为**可空**（按钮类节点没有路由地址）。
5. **phase-1 的 `AC_*` 演示权限码已移除**：权限改由按钮菜单派生后，保留这些无菜单支撑的码会破坏"菜单表是唯一事实源"，且角色保存时会静默清掉它们。`/auth/codes` 输出随之变化，两个 phase-1 测试已相应调整。
6. **改角色不吊销令牌（偏离计划 §8.5）**：`/auth/codes` 与 `/menu/all` 每次请求按当前角色重算，不存在陈旧授权，吊销只会把用户无故踢下线。**禁用账号 / 重置密码仍会吊销，且改为吊销全部令牌（access + refresh）** —— 只删 refresh 会让已签发的 access 继续有效最长 60 分钟，与"即时生效"矛盾。
7. **前端角色授权树用 `check-strictly`**：勾选与存储值一一对应、不做父子级联，避免回显时误勾子节点；父级缺失由 `/menu/all` 的祖先补齐逻辑兜底。
8. **`rbac:sync-permissions` 默认不删权限**（加 `--prune` 才删），避免误删手工维护的权限码。

---

## 附：参考

- 前端契约：`playground/src/api/system/{menu,role,user,dept}.ts`
- 表单/列定义：`playground/src/views/system/*/data.ts`、`.../modules/form.vue`
- 渲染器参照：`playground/src/adapter/vxe-table.ts`（`CellTag` / `CellSwitch` / `CellOperation`）
- i18n 键：`playground/src/locales/langs/zh-CN/system.json`
- 旧 mock 契约（含按钮 `authCode` 命名）：`git show 151d80f9e:apps/backend-mock/utils/mock-data.ts`（`MOCK_MENU_LIST`）
- 第一步成果：`docs/login-module.md`
