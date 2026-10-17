# 部门模块技术方案（第三步）

> 前置阅读：`AGENTS.md`（全局约定）、`docs/login-module.md`（鉴权）、`docs/rbac-menu.md`（RBAC 与菜单）。
> 本文档状态：**已实施**（2026-10-17）。§12 的 4 条按「建议」列落地，实施结果与偏差见 §14。

## 1. 背景与目标

- `AGENTS.md` §4.1 已把 `GET /system/dept/list` 列入前端契约表，但一直没实现；`docs/rbac-menu.md` §4.3 明确「部门本期不做，若后续要做，届时补 `admin_depts` + `dept_id`」。本文档就是把这块补齐。
- 目标：
  1. 部门树 CRUD（`admin_depts`）+ 用户归属部门（`admin_users.dept_id`）；
  2. 部门纳入**现有**权限体系（菜单下发 + 按钮权限码），不引入第二套权限机制；
  3. 用户管理能按部门筛选、能看到并编辑用户所属部门。
- 非目标（见 §3.2）：数据范围 / 数据权限（按部门限制可见数据）。

## 2. 现状核对（读代码得出，直接决定怎么做）

| 事实 | 出处 | 影响 |
| --- | --- | --- |
| 权限码唯一来源是 `admin_menus` 里 `type=button` 行的 `auth_code`，由 `RoleService::syncMenusAndPermissions()` 派生到 spatie；`admin_role_menu` 是唯一事实源 | `app/Services/RoleService.php`、`docs/rbac-menu.md` §13.1 | 部门权限必须做成「菜单行 + 三个按钮行」，**不要**新建权限表或权限码枚举 |
| `accessMode = 'backend'`，路由与菜单全部由 `/menu/all` 下发；backend 模式下静态模块路由被**整体替换** | `src/preferences.ts`、`packages/utils/src/helpers/generate-routes-backend.ts` | 新增部门页面**不需要**动 `src/router/routes/modules/*`，只需插菜单行 |
| 前端能力已就绪：`ApiTreeSelect` 已在适配器注册；`CellTag` / `CellSwitch` / `CellOperation` 已补齐；`adapter/form.ts` 已导出 `z` | `src/adapter/component/index.ts:237`、`src/adapter/vxe-table.ts:88/109/141` | 上游 antd 版部门页可低成本移植，不必再补基础设施 |
| 可照抄的参照物：`playground/src/api/system/dept.ts`、`playground/src/views/system/dept/{list.vue,data.ts,modules/form.vue}`；旧 mock 菜单定义含 `System:Dept:Create/Edit/Delete` | `git show 151d80f9e:apps/backend-mock/utils/mock-data.ts` | 契约照抄，antd 组件替换为 Element Plus |
| 上游用户管理把部门做成**左侧部门树面板**（选中部门过滤用户），且用户表单部门**必填** | `playground/src/views/system/user/list.vue`、`.../data.ts` | 见 §4.3、§4.4，本期不照搬 |
| `AGENTS.md` §4.1 把 `/system/dept/list` 归到「分页 `{ items, total }`」一栏，但 mock 与 playground 实际返回**树数组**（`pagerConfig.enabled = false`） | `AGENTS.md:164`、`playground/src/views/system/dept/list.vue` | 文档笔误，见 §4.2 与 §7 的修正项 |
| 三个已有系统页面统一用 `useVbenVxeGrid` + `useVbenDrawer` + schema 表单 | `src/views/system/*/index.vue` | 部门页保持同一形态，不用 `useVbenModal` |

## 3. 范围

### 3.1 本期做

- `admin_depts` 表 + `admin_users.dept_id` 列
- 部门树 CRUD 接口（4 个）+ 同级同名校验接口
- 部门菜单与三个按钮权限码（super / admin 可见，user 不可见）
- 前端部门管理页（树表 + 工具栏新增 + 行内「新增下级 / 编辑 / 删除」+ 抽屉表单）
- 用户管理接入部门：表单部门字段、列表部门列、搜索按部门（含子部门）过滤
- 用户接口出参带 `deptId` / `deptName`，入参支持 `deptId`
- 后端 Feature 测试；前端 `vue-tsc` 通过

### 3.2 本期不做

- **数据范围 / 数据权限**（角色只能看本部门、本部门及以下的数据）——见 §4.1
- 部门负责人、部门编码、岗位 / 职位
- 用户页左侧部门树面板——见 §4.4
- 部门变更时的用户批量迁移工具
- 部门操作审计日志

### 3.3 明确不做

- 不新建权限表 / 权限枚举，不绕过 `admin_role_menu`
- 不引入第三方树组件（`ElTree` / `ElTreeSelect` 已够用）
- 不恢复已删除的 `apps/backend-mock`

## 4. 关键决策

### 4.1 决策一：部门是否驱动数据权限（**需确认**）

| 方案 | 内容 | 影响面 |
| --- | --- | --- |
| **A（推荐，本期）** | 部门只是组织归属 + 筛选维度，角色权限仍完全由菜单树决定 | 无侵入，现有 49 个测试不受影响 |
| B（单独立项） | 角色加 `data_scope`（全部 / 本部门 / 本部门及以下 / 仅本人），在 user / role / menu 列表与登录日志查询层按部门子树过滤 | 要改所有列表查询的查询作用域、`/auth/codes` 语义、角色表单与列表，属独立课题；上游 vben 也未实现数据范围 |

推荐 A：先落地组织结构不阻塞后续，B 需要产品先定义清楚「哪些列表受数据范围约束、超管是否豁免、越权访问是 403 还是空列表」。

### 4.2 决策二：`/system/dept/list` 返回树数组，不分页

理由：前端消费方是树表；mock 与 playground 都是树数组；分页对树结构无意义。同时**修正 `AGENTS.md` §4.1**，把 `/system/dept/list` 从「分页」一栏移出，与 `/system/menu/list` 并列说明为「树数组」。

### 4.3 决策三：`deptId` 可空（与上游必填不同）

理由：super / 系统账号可能不属于任何部门；存量用户升级后 `dept_id` 为空；若前端强制必填，老数据将无法保存。前端表单**不加** `rules: 'required'`。

### 4.4 决策四：不照搬左侧部门树面板

本期把部门放进用户列表的**搜索表单**（`ApiTreeSelect`，选中后按该部门及所有子孙部门过滤）。改动小，且与现有 `useGridFormSchema` 形态一致。左侧面板会改动页面布局（左右分栏 + 独立树数据源 + 选中态联动），作为可选增强留待后续。

### 4.5 决策五：删除策略是「拒绝」而非级联

- 有子部门 → 拒绝（与 `MenuService::hasChildren` 一致）
- 有用户绑定 → 拒绝（与 `RoleService::userCount` 一致）
- 不级联删除，不把用户的部门置空

### 4.6 决策六：权限码只加三个按钮码

`System:Dept:Create` / `System:Dept:Edit` / `System:Dept:Delete`；读接口只需登录。与现有 user / role / menu 三模块完全一致。旧 mock 在菜单节点上还挂了 `System:Dept:List`，本项目**不采用**——否则读权限粒度会与其他三个模块不一致（现有 `/system/menu/list` 等读接口都没有权限码）。

## 5. 数据模型

### 5.1 新表 `admin_depts`

`database/migrations/2026_10_17_120000_create_admin_depts_table.php`

| 列 | 类型 | 说明 |
| --- | --- | --- |
| `id` | bigint PK | |
| `pid` | unsignedBigInteger, default 0, index | 父部门，0 为顶级 |
| `name` | string(64) | 部门名称，**同级唯一**（见 §12 二） |
| `sort` | integer, default 0 | 同级排序 |
| `status` | tinyInteger, default 1 | 1 启用 / 0 禁用 |
| `remark` | string(255), nullable | 备注 |
| `timestamps` | | |

- 不加外键约束：与 `admin_role_menu`（只有复合主键 + 索引）现状一致，避免 SQLite / MySQL 迁移差异。
- 唯一性**不加数据库唯一索引**：树结构下「同级同名」无法用单列 `unique(name)` 表达（不同父级允许同名）。改为应用层校验（`MenuRequest` 的 `nameExists($name, $excludeId)` 同款做法）。

### 5.2 `admin_users` 加列

`database/migrations/2026_10_17_120001_add_dept_id_to_admin_users_table.php`

| 列 | 类型 | 说明 |
| --- | --- | --- |
| `dept_id` | unsignedBigInteger, nullable, index | 所属部门，`null` 表示未分配 |

### 5.3 模型

- 新增 `App\Models\DeptModel`（`$table = 'admin_depts'`）：`$fillable`、`casts`（`pid`/`sort`/`status` → int）、`children()`（`hasMany` 自身，`orderBy('sort')`）—— 照 `MenuModel` 写。
- `AdminUserModel`：`$fillable` 加 `dept_id`，新增 `dept()`（`belongsTo(DeptModel::class, 'dept_id')`）。

## 6. 接口契约

### 6.1 部门

| 方法 | 路径 | 权限 | 出参 |
| --- | --- | --- | --- |
| GET | `/api/system/dept/list` | 登录 | 树数组 `[{ id, pid, name, sort, status, remark, createTime, children? }]` |
| POST | `/api/system/dept` | `System:Dept:Create` | 单节点 |
| PUT | `/api/system/dept/{id}` | `System:Dept:Edit` | 单节点 |
| DELETE | `/api/system/dept/{id}` | `System:Dept:Delete` | `data: ''` |
| GET | `/api/system/dept/name-exists?name=&id=` | 登录 | `boolean` |

- 入参：`{ name, pid?, sort?, status, remark? }`，`pid` 省略按 0 处理。
- 出参 `id` / `pid` 一律**字符串**（与 `SystemMenuResource` 一致，vxe-table 树表的 `rowField` / `parentField` 依赖一致性）。
- 树构建：一次 `orderBy('sort')->orderBy('id')` 取全表后在内存里递归建树，沿用 `MenuService::buildTree()` 的写法，避免 N+1。
- `children` 为空时不输出该字段（与菜单一致）。

### 6.2 用户接口扩展

| 变更 | 说明 |
| --- | --- |
| `GET /system/user/list` 新增 `deptId` 过滤参数 | 传值时按**该部门及其所有子孙部门**过滤：先取子树 id 集合，再 `whereIn`，不做递归查库 |
| `SystemUserResource` 新增 `deptId` / `deptName` | 列表要显示部门列；`deptName` 取自 `dept` 关系（`loadMissing`，避免 N+1） |
| `POST` / `PUT /system/user` 新增 `deptId` | 校验 `nullable, integer, exists:admin_depts,id`；传 `0` 或空视为清空 → 存 `null` |

## 7. 后端实现清单

**新增**

- `app/Models/DeptModel.php`
- `app/Services/DeptService.php`（`tree()` / `create()` / `update()` / `delete()` / `hasChildren()` / `userCount()` / `descendantIds()` / `nameExists()`）
- `app/Http/Controllers/Api/System/DeptController.php`
- `app/Http/Requests/System/DeptRequest.php`
- `app/Http/Resources/SystemDeptResource.php`
- `database/migrations/2026_10_17_120000_create_admin_depts_table.php`
- `database/migrations/2026_10_17_120001_add_dept_id_to_admin_users_table.php`
- `database/seeders/DeptMenuSeeder.php`（幂等，见 §9.2）
- `database/seeders/DeptSeeder.php`（示例部门树，见 §9.4）
- `tests/Feature/SystemDeptTest.php`

**修改**

- `routes/api.php`：新增 4 条部门路由 + 1 条 `name-exists`；写操作挂 `permission:System:Dept:*` 中间件
- `app/Services/AdminUserManageService.php`：`paginate()` 支持 `deptId` 子树过滤；`create()` / `update()` 写 `dept_id`
- `app/Http/Requests/System/UserRequest.php`：`deptId` 校验
- `app/Http/Resources/SystemUserResource.php`：输出 `deptId` / `deptName`
- `app/Models/AdminUserModel.php`：`fillable` + `dept()` 关系
- `database/seeders/AdminUserSeeder.php`：三个账号挂到不同部门
- `database/seeders/DatabaseSeeder.php`：在 `MenuSeeder` 之后调用 `DeptMenuSeeder`、`DeptSeeder`
- `AGENTS.md` §4.1：修正 `/system/dept/list` 的契约描述（§4.2）

**不需要改**：`MenuService`、`RoleService`、`AdminUserService`（鉴权与权限派生逻辑完全不涉及部门）。

## 8. 前端实现清单

**新增**

- `apps/web-ele/src/api/system/dept.ts`：`SystemDeptApi` 命名空间（`SystemDept` 接口 + `MenuTypes` 式的类型约束）+ `getDeptList` / `createDept` / `updateDept` / `deleteDept` / `isDeptNameExists`
- `apps/web-ele/src/views/system/dept/index.vue`：树表页面（`useVbenVxeGrid` + `treeConfig` + 工具栏新增）
- `apps/web-ele/src/views/system/dept/data.ts`：`useFormSchema()` / `useColumns()`
- `apps/web-ele/src/views/system/dept/modules/form.vue`：抽屉表单（`useVbenDrawer`）

**修改**

- `src/api/system/index.ts`：`export * from './dept'`
- `src/api/system/user.ts`：`SystemUser` 加 `deptId` / `deptName`
- `src/views/system/user/data.ts`：表单加部门 `ApiTreeSelect`；`useGridFormSchema()` 加部门；`useColumns()` 加部门列
- `src/locales/langs/zh-CN/system.json`、`en-US/system.json`：新增 `dept` 段（`title` / `list` / `deptName` / `parentDept` / `sort` / `status` / `remark` / `createTime` / `operation`），`user` 段加 `dept`

**不需要改**：`src/router/routes/modules/*`（backend 模式菜单由后端下发）、`src/adapter/*`（所需组件与渲染器都已存在）。

**移植替换清单（playground → web-ele）**

| playground 写法 | web-ele 写法 |
| --- | --- |
| `Button, message` from `antdv-next` | `ElButton, ElMessage` from `element-plus` |
| `useVbenModal` | `useVbenDrawer`（与现有三个系统页面统一） |
| `Textarea` 组件 | `Input` + `componentProps: { rows: 3, type: 'textarea' }`（现有 user 表单写法） |
| `z.string().min(...)` | 可用（`adapter/form.ts` 已导出 `z`），也可用 `rules: 'required'` + 后端 FormRequest 兜底 |
| `RadioGroup` 的 `buttonStyle/optionType` | `isButton: true`（Element Plus 适配器参数） |

## 9. 菜单与种子数据

### 9.1 菜单行

| name | type | path | component | authCode | 父级 / sort |
| --- | --- | --- | --- | --- | --- |
| `SystemDept` | menu | `dept` | `/system/dept/index` | — | `System` 目录，sort 4 |
| `SystemDeptCreate` | button | — | — | `System:Dept:Create` | `SystemDept`，sort 1 |
| `SystemDeptEdit` | button | — | — | `System:Dept:Edit` | `SystemDept`，sort 2 |
| `SystemDeptDelete` | button | — | — | `System:Dept:Delete` | `SystemDept`，sort 3 |

`component` 必须是 `views/` 下真实存在的文件（`/system/dept/index` → `views/system/dept/index.vue`），否则前端回退到 not-found 页。

授权：`super`、`admin` 给全（菜单 + 三个按钮）；`user` 不给，保持「user 看不到系统管理」的既有语义。

### 9.2 ⚠️ 不要靠重跑 `MenuSeeder` 来加部门

`MenuSeeder::run()` 开头就是 `DB::table('admin_role_menu')->delete()` + `MenuModel::query()->delete()`，**会清空所有菜单和全部角色授权**。存量环境重跑它等于回滚管理员的自定义菜单。

做法：新增**幂等** `DeptMenuSeeder`：

1. `MenuModel::updateOrCreate(['name' => 'SystemDept'], [...])`，按钮行同理（按 `name` 定位）；
2. 只对 `super` / `admin` **追加**授权（`insertOrIgnore` 到 `admin_role_menu`，或复用 `RoleService::syncMenusAndPermissions()` 并带上该角色原有 menu id）；
3. 不删除任何现有菜单或授权。

`DatabaseSeeder` 里排在 `MenuSeeder` 之后，全新环境两条路径结果一致。

### 9.3 存量环境升级步骤

```bash
cd apps/api
php artisan migrate
php artisan db:seed --class=DeptMenuSeeder
php artisan rbac:sync-permissions     # 派生三个按钮码；不加 --prune，避免误删手工维护的权限码
```

前端无需迁移（菜单由后端下发），刷新页面即可看到「部门管理」。

### 9.4 示例部门数据（`DeptSeeder`）

```
总公司
├── 研发部
│   ├── 前端组
│   └── 后端组
├── 市场部
└── 财务部
```

三个账号分挂不同部门（如 `vben` → 总公司、`admin` → 研发部、`jack` → 前端组），便于验证「按部门含子部门过滤」这一条最容易写错的行为。

## 10. 测试方案

`tests/Feature/SystemDeptTest.php`，风格照 `SystemRbacTest`（`RefreshDatabase` + `setUp()` 里 `$this->seed()`，用 `Sanctum::actingAs` 切换账号）：

| 用例 | 断言要点 |
| --- | --- |
| 部门列表返回树 | 嵌套 `children`、`id`/`pid` 为字符串、按 `sort` 排序 |
| 创建 / 更新 / 删除部门 | 落库正确；`status` 为 int |
| 同级同名被拒绝 | 422；不同父级同名**允许** |
| 删除有子部门被拒绝 | 业务错误（`code != 0`） |
| 删除有用户绑定被拒绝 | 业务错误 |
| pid 设为自己或自己的子孙被拒绝 | 422 / 业务错误（防树环路） |
| 读只需登录、写需权限码 | 普通角色 `jack` 写操作 403；读 200 |
| 按钮菜单派生权限码 | 创建 `SystemDept*` 按钮行后 spatie 出现三个码（复用现有断言方式） |
| 用户列表按 `deptId` 过滤 | 传父部门时**包含**子孙部门的用户；不传时返回全部 |
| 用户创建 / 更新写 `dept_id` | `deptId` 为 0 / 空时存 `null` |
| 用户出参含 `deptId` / `deptName` | 字段存在且为字符串 |

前端：`vue-tsc --noEmit --skipLibCheck` 通过（本仓库前端无单测，维持现状）。

## 11. 风险与陷阱

1. **重跑 `MenuSeeder` 会清空菜单与角色授权**（§9.2）——这是本次最容易踩的坑。
2. **pid 环路**：更新时必须校验新父级不在自身子树内，否则 `buildTree` 无限递归。
3. **树构建 N+1**：一次取全表在内存建树，不要逐层查库。
4. **含子部门过滤**：先算出子树 id 集合再 `whereIn`；不要递归查库、也不要用 `LIKE` 拼路径。
5. **id 类型**：出参必须 `(string)` 转换，vxe-table 树表选中与 `parentField` 匹配依赖字符串一致。
6. **SQLite（本地 `.env`）与 MySQL（`.env.example`）都要能跑**：不加外键、不用数据库方言函数。
7. **`ApiTreeSelect` 字段映射**：适配器默认 `label`/`children`，部门字段是 `name`，必须显式传 `labelField: 'name'` / `valueField: 'id'` / `childrenField: 'children'`。
8. **搜索表单清空部门**：`allowClear` 要能取消过滤，避免清空后仍带旧 `deptId` 查询。
9. **文档一致性**：`AGENTS.md` §4.1 的 `/system/dept/list` 描述需同步修正（§4.2），否则下一个人还会按「分页」实现。

## 12. 待确认事项（已按「建议」列确认落地）

| # | 事项 | 结论 |
| --- | --- | --- |
| 一 | 部门是否本期就做数据范围（§4.1 方案 A / B） | **A**：只做组织树与筛选；B 单独立项 |
| 二 | 部门名称唯一性：同级唯一 / 全局唯一 | **同级唯一**（应用层校验，不加数据库唯一索引） |
| 三 | 用户表单的部门是否必填 | **非必填**（可空，兼容存量用户与 super 账号） |
| 四 | 用户页要不要左侧部门树面板 | 本期**不做**；用搜索表单里的部门树（§4.4） |

## 13. 实施步骤（分批，每批可独立验证）

| 批次 | 内容 | 验证方式 |
| --- | --- | --- |
| 1 | 两个 migration + `DeptModel` + `AdminUserModel` 关系 | `php artisan migrate` 通过；`php artisan migrate:fresh --seed` 通过 |
| 2 | `DeptService` / `DeptController` / `DeptRequest` / `SystemDeptResource` + 路由 + 用户接口扩展 | `SystemDeptTest` 全绿；`php artisan test` 无回归；`./vendor/bin/pint` |
| 3 | `DeptMenuSeeder` + `DeptSeeder` + `AdminUserSeeder` 挂部门 + 修正 `AGENTS.md` | 全新 migrate/seed 与存量升级（§9.3）两条路径都验证 |
| 4 | 前端 `api/system/dept.ts` + 部门页面 + i18n | 页面可增删改查；`vue-tsc` 通过 |
| 5 | 前端用户页接入部门（表单 / 列 / 筛选） | 联调通过；按部门筛选含子部门行为正确 |

## 14. 实施记录（落地时相对本计划的偏差）

按 §13 的 5 个批次全部落地。以下是需要评审确认的偏差与计划外改动。

### 14.1 计划外修复（均为既有 bug，与部门无关但阻塞了验收）

1. **`AdminUserManageService::create()` 用了不存在的常量**：写的是 `self::SUPER_ROLE`，而该常量定义在 `AdminUserService` 上（同类里另外三处都写对了）。带 `roles` 创建用户必然抛 `Undefined constant` 致命错误，用户新增/编辑接口实际不可用。此前被下面的密码校验 422 掩盖——测试在 422 断言处就失败了，没走到这一行。已改为 `AdminUserService::SUPER_ROLE`。
2. **密码策略与测试夹具/种子数据不一致**：`UserRequest` 要求密码含大小写与数字，而 `SystemRbacTest` 用 `secret123`、种子密码是 `123456`、`ResetPasswordRequest` 只要求 `min:6`。**处理方式：不动安全规则，改测试夹具**（`secret123` → `Secret123`）。⚠️ 遗留问题：**创建要求强密码、重置只要求 6 位**，两条路径策略不一致，需要产品确认统一到哪一侧。
3. **`SystemUserResource` 的 `deptName` 不能用 `whenLoaded`**：Laravel 12 的 `whenLoaded` 在「关系已加载但值为 null」时**返回 null 而不是 default**，未分配部门的用户会拿到 `null` 而非 `''`。改为 `$this->dept?->name ?? ''`。
4. **测试里 URI 不能直接拼裸中文**：`getJson('/x?name=研发部')` 会被 URI 处理破坏（实测返回 false，服务层为 true）。测试里改用 `http_build_query()`，与浏览器/axios 的行为一致。
5. **`AGENTS.md` 除 §4.1 契约外还改了 §9**：原「已知待办/陷阱」有 3 条已过期（后端已实现、代理已指向 `apps/api`、`apps/api` 已纳入 git），改为记录本次真正会踩的坑（加菜单不要重跑 `MenuSeeder`、数据范围未实现、backend 模式静态路由是死代码、本地 `.env` 与 `.env.example` 不一致）。
6. **422 校验错误提示是空白的**（浏览器功能测试时发现）：`apps/web-ele/src/api/request.ts` 的 `errorMessageResponseInterceptor` 里写的是 `responseData?.error ?? responseData?.message`，而后端校验错误（422）的 `error` 是 `{ 字段: [消息] }` **对象**，`ElMessage.error(对象)` 渲染出空提示。改为只在 `error` 是字符串时用它，否则退回 `message`。影响面是**所有** FormRequest 校验失败的提示（部门重名、菜单重名、用户密码格式等），不只是部门。

### 14.2 与计划的差异

- 前端**不做**客户端异步唯一性校验，依赖后端 422 提示（与菜单页一致）；`isDeptNameExists` 已按契约导出但页面暂未调用（既有 `isMenuNameExists` / `isMenuPathExists` 同样未被页面调用）。
- 部门页未加搜索表单（部门树规模小，与菜单页一致）。

### 14.3 验证记录

| 项 | 结果 |
| --- | --- |
| `php artisan test` | **64 passed（350 assertions）** |
| `./vendor/bin/pint` | 通过（80 files） |
| 全新库 `migrate:fresh --seed` | 25 菜单 / 6 部门 / 12 权限；super+admin 各 25 菜单 12 权限，user 5 菜单 0 权限 |
| 存量库升级（`migrate` + `DeptMenuSeeder`） | 菜单 21→25、权限 9→12；既有角色与授权**未被清空**（用一条既存测试角色验证） |
| seeder 幂等性 | 重复执行 `DeptMenuSeeder` / `DeptSeeder` 不产生重复数据、不清空授权 |
| 接口冒烟 | 创建、同级重名 422、更新、环路 422、删除、按部门（含子部门）过滤、清空部门，全部符合预期 |
| 前端 `vue-tsc --noEmit --skipLibCheck` | 通过 |
| 浏览器（`vben` / `123456`） | 逐功能点测过：菜单下发 → 部门页树表展开（缩进 0/20/40px、子级按 `sort` 排序）→ 新增顶层部门 → 「新增下级」父级预填并落为子级 → 修改（改名/排序/备注回显与落库一致）→ 列表内状态开关（确认框 + `PUT` 200 + 开关翻转）→ 同级重名 422 提示 → 删除保护（有子部门被拒并提示）→ 正常删除（成功提示 + 行消失）；用户页部门列显示「研发部 / 前端组」、编辑抽屉回显部门。其余需在下拉/树控件中选择父级的场景（环路、按部门筛选、清空部门、普通角色权限边界）用应用同源接口逐项验证，结果与后端测试一致 |

> 浏览器验证的已知限制：本次使用的 in-app 浏览器面板**不推进 CSS 入场动画**，指针点击/坐标点击会因元素不稳定而超时，popper 里的选项也无法点选（滑块验证、抽屉里的选择器都受影响）。另外 **vxe-table 的树展开按钮与 ElTree 的节点点击不响应合成事件**（需要真实指针输入）。抽屉的 `data-state="open"` 与全部字段渲染均正常，属面板输入/渲染限制而非页面缺陷。因此：表单填写与提交、状态开关、Popconfirm/MessageBox 确认、列表刷新用页面内 DOM 事件完成；需要在下拉树里选父级的场景改走同源接口。滑块验证是上游演示页的客户端组件，用页面内鼠标事件序列通过。

## 附：参考

- 前端契约：`playground/src/api/system/dept.ts`
- 页面与列定义：`playground/src/views/system/dept/{list.vue,data.ts,modules/form.vue}`
- 旧 mock 菜单定义（含 `System:Dept:*`）：`git show 151d80f9e:apps/backend-mock/utils/mock-data.ts`
- 后端可照抄的形态：`app/Http/Controllers/Api/System/MenuController.php`、`app/Services/MenuService.php`、`app/Http/Requests/System/MenuRequest.php`
- 测试形态：`tests/Feature/SystemRbacTest.php`
- 前置方案：`docs/login-module.md`、`docs/rbac-menu.md`
