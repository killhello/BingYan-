# BingYan Chat

##一个轻量级 PHP 实时聊天室，支持公共聊天、群聊、好友私聊，包含等级/头衔系统、管理员面板、撤回消息、AI 助手等功能。

[赞助](https://afdian.com/a/bingyannb)

## 功能特性

### 聊天模块
- **公共聊天室** — 所有用户可参与的大厅聊天
- **群聊** — 创建/加入群组，群内聊天
- **好友私聊** — 添加好友，一对一私密聊天
- **AI 助手** — 在任意聊天中输入 `@ai <prompt>` 调用 AI 回复
- **消息撤回** — 2 分钟内可撤回自己的消息；管理员可撤回任意消息或取消撤回
- **图片发送** — 支持上传 JPG/PNG 图片（≤5MB），自动生成预览与灯箱查看
- **代码高亮** — ` ``` 代码块 ``` ` 与 `**加粗**` 标记渲染
- **增量刷新** — 无变化时不更新 DOM，减少闪烁与性能开销
- **进入动画** — 页面加载时消息渐入滑入效果（GPU 加速）

### 用户系统
- **注册/登录** — 用户名密码注册，GeeTest 人机验证
- **用户等级** — 按消息数量划分 Lv1~Lv10，每 10 条升一级，显示等级徽章图片
- **自定义头衔** — 用户可设置 ≤20 字头衔，每日限 3 次；管理员可删除他人头衔
- **头像** — 自动生成首字母头像

### 管理员功能
- **公告管理** — 发布/编辑系统公告
- **用户管理** — 查看用户列表、封禁/解封、禁言/解禁、删除用户
- **消息管理** — 撤回任意消息、取消撤回、1 分钟后自动删除
- **等级徽章** — 管理员显示独立徽章图片（默认 `BingYan.png`）

### 技术特性
- 纯 PHP + JSON 文件存储，无需数据库
- 分片存储（每文件 ~30KB），缓存加速
- 消息撤回 1 分钟后自动从文件和缓存中清除
- Glassmorphism 毛玻璃轻量主题
- 响应式布局，适配移动端

## 快速开始

### 环境要求

- PHP 7.4+
- GD 库（用于头像生成）
- 文件写入权限（`users/`、`chatlogs/`、`uploads/` 等目录）

### 安装

```bash
# 复制到 web 根目录
cp -r bingyan-chat/* /path/to/webroot/

# 设置写入权限
chmod -R 755 /path/to/webroot/users
chmod -R 755 /path/to/webroot/chatlogs
chmod -R 755 /path/to/webroot/uploads
chmod -R 755 /path/to/webroot/data
chmod -R 755 /path/to/webroot/avatars
```

### 配置

#### 1. GeeTest 人机验证（必须）

注册 [GeeTest](https://www.geetest.com/) 获取 ID 和 Key，编辑 `geetest_config.php`：

```php
return array(
    'id'  => '你的_GEETEST_ID',
    'key' => '你的_GEETEST_KEY',
);
```

#### 2. AI 助手（可选）

编辑 `ai_proxy.php`，替换 API Key：

```php
$apiKey = '你的_AI_API_KEY';
```

#### 3. 管理员账号（可选）

编辑 `config.php`，修改管理员用户名列表：

```php
define('ADMIN_USERNAMES', ['BingYan']);
```

管理员默认等级徽章图片为 `BingYan.png`，可替换为自己的图片。

### 使用

访问 `http://你的域名/` 即可进入聊天室。

1. 注册账号 → 登录
2. 在公共聊天室发言
3. 点击左侧菜单创建群聊或添加好友
4. 输入 `@ai <问题>` 调用 AI 助手

## 项目结构

```
bingyan-chat/
├── config.php               # 核心配置与辅助函数
├── chat.php                 # 公共聊天室主页面
├── group_chat.php           # 群聊页面
├── friend_chat.php          # 好友私聊页面
├── sidebar_component.php    # 侧边栏组件
│
├── get_messages.php         # 公共聊天 AJAX 消息加载
├── get_group_messages.php   # 群聊 AJAX 消息加载
├── get_friend_messages.php  # 私聊 AJAX 消息加载
├── send_message.php         # 发送公共消息
├── send_group_message.php   # 发送群消息
├── send_friend_message.php  # 发送私聊消息
├── recall_message.php       # 撤回/取消撤回消息
│
├── login.php                # 登录页面
├── login_process.php        # 登录处理
├── register.php             # 注册处理
├── logout.php               # 退出登录
├── user_settings.php        # 用户设置（头衔）
├── update_profile.php       # 更新资料
│
├── avatar.php               # 头像生成
├── upload_image.php         # 图片上传
├── admin_action.php         # 管理员操作 API
├── set_title.php            # 设置头衔 API
├── delete_title.php         # 删除头衔 API
├── save_announcement.php    # 保存公告 API
├── get_announcement.php     # 获取公告 API
│
├── ai_proxy.php             # AI 助手代理
├── geetest_config.php       # GeeTest 配置模板
├── geetest_lib.php          # GeeTest SDK
├── geetest_register.php     # GeeTest 注册
│
├── group_manager.php        # 群管理页面
├── group_admin.php          # 群管理 API
├── friends.php              # 好友管理页面
│
├── bead-pattern .png        # Lv1 等级徽章
├── bead-pattern(1).png      # Lv2 等级徽章
├── bead-pattern (2).png     # Lv3 等级徽章
├── ...
├── bead-pattern (9).png     # Lv10 等级徽章
├── BingYan.png              # 管理员等级徽章（可替换）
│
├── users/                   # 用户数据（JSON）
├── chatlogs/                # 聊天记录（分片 JSON）
├── data/                    # 群组/好友关系数据
├── uploads/                 # 上传的图片
└── avatars/                 # 头像缓存
```

## 等级系统

| 等级 | 所需消息数 | 徽章文件 |
|------|-----------|---------|
| Lv1  | 0         | `bead-pattern .png` |
| Lv2  | 10        | `bead-pattern(1).png` |
| Lv3  | 20        | `bead-pattern (2).png` |
| ...  | ...       | ... |
| Lv10 | 90        | `bead-pattern (9).png` |

管理员始终显示 `BingYan.png` 徽章。

## 自定义

- **等级徽章**：替换 `bead-pattern*.png` 文件，保持同名
- **管理员徽章**：替换 `BingYan.png`
- **主题颜色**：编辑 CSS 变量中的渐变与毛玻璃参数（搜索 `linear-gradient` 和 `glassmorphism`）
- **等级阈值**：修改 `config.php` 中 `getLevel()` 函数

## 许可

MIT License
