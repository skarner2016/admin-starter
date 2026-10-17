# 系统能力补全（第二轮）：P0 / P1

> 背景：`docs/dept-module.md` 落地后做了一次功能缺口盘点，本文档记录按盘点结论执行的 **P0（正确性/安全）** 与 **P1（后台必备能力）**。
> 范围：权限 / 用户 / 角色 / 菜单 / 部门 / 登录日志。
> 状态：**已实施**（2026-10-17）。

## 1. 本轮改动一览

| 优先级 | 事项 | 状态 |
| --- | --- | --- |
| P0-1 | 按钮级权限码在界面落地（四页工具栏 + 行内操作 + 状态开关） | ✅ |
| P0-2 | 角色 `status` 真正生效；super 角色不可停用 | ✅ |
| P0-3 | 部门 `status` 真正生效 | ✅ |
| P0-4 | 用户更新加自锁保护（不能停用自己 / 摘掉自己的 super） | ✅ |
| P0-5 | 统一密码策略（创建 = 重置 = 自助改密） | ✅ |
| P1-6 | 登录日志：查询接口 + 页面 + 查看权限码 | ✅ |
| P1-7 | 上传接口 + 头像 + 自助改密/改资料 | ✅ |
| P1-8 | 后端消息中文化（zh_CN 校验包 + 统一 401/403 文案） | ✅ |
| P1-9 | 批量删除（用户/角色）+ 列表导出（用户/角色/登录日志） | ✅ |

## 2. 行为语义（有变更，需知悉）

### 2.1 按钮级权限

- 四个系统页的工具栏按钮用 `v-access:code` 控制显隐；行内操作（编辑/删除/重置密码/新增下级）按权限码过滤；无编辑权限时状态开关降级为只读标签。
- 权限码仍是 `System:{User,Role,Menu,Dept}:{Create,Edit,Delete}`，来自 `admin_menus` 的按钮行，没有新增机制。
- 后端 `permission:` 中间件保持不变——界面隐藏只是 UX，越权仍会被 403。

### 2.2 角色停用

- 停用的角色**不再贡献菜单与权限码**：`/menu/all` 只统计 `status=1` 的角色，`/auth/codes` 只聚合启用角色的权限码。
- **停用 super 角色被禁止**（与「super 角色不允许删除」对称），避免唯一全量权限角色被关掉。
- 用户仍可登录；若其所有角色都停用，登录后菜单与权限码为空（等同于无任何功能），需要管理员恢复角色。
- 顺带修正：`RoleRequest` 原来的 `Rule::notIn(['super'])` 过宽——它把「编辑 super 角色本身」也挡成了 422（表单必须回传 `name='super'`）。现在只禁止「把别的角色改名成 super」，super 角色的备注/授权/状态可以正常编辑。

### 2.3 部门停用

- 停用部门**不能被分配给新用户**（`deptId` 变更时校验），也**不能作为新上级**（`pid` 变更时校验）。
- 只在「值发生变化」时校验：否则挂在停用部门下的存量用户连备注都保存不了。
- 管理页仍会返回停用部门（供重新启用），用户表单里的部门树也仍会显示它——服务端会拒绝选择并给出中文提示。

### 2.4 自锁保护

`AdminUserManageService::update()` 新增两条规则（与「不能删除自己」对称）：

- 不能把当前登录账号停用；
- super 不能摘掉自己的 `super` 角色。

### 2.5 密码策略

- 创建用户、管理员重置、自助改密**共用同一套规则**：`AdminUserService::PASSWORD_MIN_LENGTH`（8）与 `PASSWORD_PATTERN`（同时含大小写字母与数字）。
- 自助改密额外要求新旧密码不同。
- 前端创建表单与重置密码弹窗的校验同步更新。

### 2.6 登录日志

- 新增 `GET /api/system/login-log/list`（分页；支持 `username` / `ip` / `success` / `startTime` / `endTime`）。
- **这是本仓库第一个「读操作也需要权限码」的接口**：日志含 IP / UA，读取需要 `System:LoginLog:View`。该码同样由 `type=button` 行派生，没有引入第二套机制。
- `reason` 字段返回原始原因码（`invalid_credentials` / `locked` / `disabled`），中文文案在前端翻译，避免把展示文案写进数据库。

### 2.7 上传与个人中心

- `POST /api/upload`：需要登录，图片类型（jpg/jpeg/png/gif/webp）、≤2MB，落盘 `public` 磁盘，返回 `{ name, size, url }`（`/storage/...`）。
- `PUT /api/user/profile`：改自己的昵称 / 头像 / 简介。
- `PUT /api/user/password`：改自己的密码；**保留当前会话，其它令牌（含 refresh）立即失效**。
- 个人中心的「基础设置」「修改密码」已接真实接口（此前是纯演示）；用户管理表单新增头像上传。

### 2.8 后端消息

- 新增 `lang/zh_CN/validation.php`，`config/app.php` 默认 locale 改为 `zh_CN`（仍可用 `APP_LOCALE` 覆盖）。
- 统一 401 / 403 / 404 / 429 / 500 的中文文案；spatie 的英文权限异常单独翻译。
- 各 FormRequest 补齐 `attributes()`（用户名、密码等），校验消息不再出现「The 密码 field format is invalid.」。

### 2.9 批量删除与导出

- `POST /api/system/user/batch-delete`、`POST /api/system/role/batch-delete`：请求 `{ ids: string[] }`（1–100 条）。
- **逐条应用与单条删除相同的护栏**，返回 `{ deleted: string[], failed: [{ id, message }] }`：部分成功不会整批失败，且每条失败都有独立原因。
- 用户 / 角色页新增行选择与「批量删除」按钮；用户 / 角色 / 登录日志页开启表格导出。菜单与部门是树表，未开启导出（折叠状态下导出的是不完整的树，容易误导）。

### 2.10 未认证请求的 500 修复

Laravel 12 的 `withMiddleware()` 默认注册了 `redirectGuestsTo(fn () => route('login'))`，而本项目没有 `login` 路由——**未认证的非 JSON 请求**会在中间件里抛 `RouteNotFoundException` 变成 500。已在 `bootstrap/app.php` 中：

- `redirectGuestsTo(fn () => null)` 去掉不存在的登录路由；
- `shouldRenderJsonWhen(api/*)` 让 API 一律按 JSON 返回。

现在非 JSON 与 JSON 请求都会得到统一响应包的 401。

## 3. 新增接口

| 方法 | 路径 | 权限 |
| --- | --- | --- |
| POST | `/api/upload` | 登录 |
| PUT | `/api/user/profile` | 登录 |
| PUT | `/api/user/password` | 登录 |
| GET | `/api/system/login-log/list` | `System:LoginLog:View` |
| POST | `/api/system/user/batch-delete` | `System:User:Delete` |
| POST | `/api/system/role/batch-delete` | `System:Role:Delete` |

## 4. 升级步骤（存量环境）

```bash
cd apps/api
# 1) 登录日志菜单（幂等，只追加授权）
php artisan db:seed --class=LoginLogMenuSeeder
# 2) 头像等上传文件需要公开访问
php artisan storage:link
# 3) 权限码派生（不加 --prune，避免误删手工维护的码）
php artisan rbac:sync-permissions
```

前端新增 `/storage` 代理（`apps/web-ele/vite.config.ts`），开发环境可直接访问后端上传的文件；生产环境由反向代理把 `/storage` 指到 `apps/api/public/storage`。

## 5. 验证记录

| 项 | 结果 |
| --- | --- |
| `php artisan test` | **88 passed（456 assertions）** |
| 新增测试 | 角色停用/不可停用、部门停用语义（4 例）、自锁保护、密码策略、登录日志（4 例）、上传/个人中心（7 例）、批量删除（3 例） |
| `vue-tsc --noEmit --skipLibCheck` | 通过 |
| 浏览器 | 见提交说明与下轮联调记录 |

## 6. 仍未做（P2）

- 数据范围（按部门限制可见数据）——需先定义产品语义；
- 菜单 `meta` 全字段（activeIcon / activePath / badge / hideInBreadcrumb / hideInTab / hideChildrenInMenu）、图标选择器、`redirect` 可编辑、`component` 路径校验；
- 权限码总览页、角色用户名单、部门负责人/编码/用户数；
- 操作日志（审计）、会话管理（在线用户/踢下线）、CI、web-ele 的 e2e、API 文档。
