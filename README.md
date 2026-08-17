# 信息学奥赛一本通答案网 (YBT Answers)

C++ 在线评测系统「信息学奥赛一本通」(ybt.ssoier.cn) 的附属题库网站，由 **SZY创新工作室** 开发。

公益非盈利项目，收录原网站全部题目（题目描述 + 参考解答），支持数学公式渲染与代码高亮，不提供在线评测，仅做题库展示与搜索。每道题的答案由 AI 自动生成（OpenAI 兼容接口），也可人工校对。

线上示例：https://ybt.szystudio.cn

---

## 功能列表

- **安装向导** — 四步可视化安装（环境检查 → 数据库 → 管理员与 AI API → 完成），自动建库建表、导入初始分类，安装后自删
- **题目展示** — 详情页完整呈现题号、标题、时限、描述、输入/输出说明与样例，支持 MathJax 公式与深色代码高亮
- **AI 参考答案** — 答案默认折叠展示并标注「由 AI 生成，仅供参考」，人工修改后自动标记「人工校对」
- **一键抓取** — 输入原网站链接或题号自动解析入库（标题、时限、描述、样例），抓取后自动调用 AI 生成答案
- **批量抓取** — 支持题号范围（如 1000-1050）或题号列表，逐题执行，实时进度条与日志，可随时停止
- **搜索** — 题号纯数字直接跳转，标题模糊匹配并高亮；导航栏实时搜索建议（防抖 300ms，前 5 条）
- **侧边栏题目树** — 大部分 → 小部分 → 章节 → 题目四级树形目录，展开/折叠、当前节点高亮，移动端抽屉式
- **章节管理** — 大部分/小部分/章节三级分类增删改，支持上下移动排序，后台修改自动刷新前台缓存
- **题目管理** — 添加/编辑/删除题目，软删除回收站（可恢复/彻底删除/清空），按章节筛选与搜索
- **答案管理** — 编辑页查看与手动修改答案，「重新生成」前确认，人工修改过的答案弹窗提示覆盖风险
- **系统设置** — AI API Key 加密存储、模型与 Endpoint 可配置、连接测试、管理员密码修改
- **后台安全** — 登录失败 5 次锁定 15 分钟、CSRF 全覆盖、密码 `password_hash` 哈希
- **响应式设计** — 桌面/移动端双端适配，参考 Linear、Vercel、GitHub 的开发者工具设计语言

---

## 环境要求

### 服务器

| 组件 | 最低版本 | 说明 |
|------|---------|------|
| PHP | **8.0+** | 推荐 PHP 8.2+ |
| MySQL | **5.7+** | 或 MariaDB 10.2+ |
| Web 服务器 | Nginx / Apache | Apache 需启用 `mod_rewrite` |
| 操作系统 | Linux | 推荐 Ubuntu 20.04+ / Debian 11+ |

### 必需的 PHP 扩展

| 扩展 | 用途 | 必装 |
|------|------|:--:|
| `pdo_mysql` | 数据库连接（PDO） | **是** |
| `curl` | 抓取原网站题目、调用 AI API | **是** |
| `openssl` | API Key 加密存储 (AES-256-CBC) | **是** |
| `mbstring` | 多字节字符串处理 | **是** |
| `json` | JSON 编解码 | **是** |

**安装命令（Debian/Ubuntu）：**

```bash
sudo apt install php php-mysql php-curl php-mbstring php-xml
```

**安装命令（CentOS/RHEL）：**

```bash
sudo yum install php php-mysqlnd php-curl php-mbstring php-xml
```

验证扩展是否齐全：

```bash
php -m | grep -E "pdo_mysql|curl|openssl|mbstring|json"
```

---

## 快速部署

### 1. 上传文件

将整个项目目录上传至 Web 服务器（如 `/www/wwwroot/ybt.example.com/`）。

### 2. 配置 Web 服务器

#### Nginx 配置

```nginx
server {
    listen 80;
    server_name ybt.example.com;
    root /www/wwwroot/ybt.example.com;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        # 批量抓取 + AI 生成答案耗时较长
        fastcgi_read_timeout 300s;
    }

    # 禁止访问敏感目录与文件（项目自带 .htaccess，Nginx 需单独配置）
    location ~ ^/(includes|cache|logs)/ {
        deny all;
    }
}
```

#### Apache 配置

项目自带 `.htaccess`（禁止目录列表、保护 `includes/` 敏感文件、gzip 与静态缓存），确保 `mod_rewrite` 已启用：

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 3. 目录权限

```bash
cd /www/wwwroot/ybt.example.com

# cache/（侧边栏树缓存）与 logs/（运行日志）需可写，安装时会自动创建并写入 .htaccess
mkdir -p cache logs
chown -R www-data:www-data cache logs includes
chmod 755 cache logs
```

### 4. 运行安装向导

浏览器访问 `http://你的域名/install.php`，按照向导填写：

| 步骤 | 内容 |
|------|------|
| 环境检查 | PHP 版本、必需扩展、目录可写性（9 项自动检测） |
| 数据库 | MySQL 主机、端口、用户名、密码、数据库名（不存在自动创建） |
| 管理员与 AI API | 管理员账号密码（强度校验）、AI API Key（必填，加密存储）、模型与 Endpoint（可测试连接） |

提交后系统自动完成：

1. 创建 6 张数据表（InnoDB + utf8mb4_unicode_ci）
2. 导入初始题库分类（四大部分、19 个小部分）
3. 写入管理员账户（`password_hash` 哈希）与加密后的 API Key
4. 生成 `includes/config.local.php` 与 `includes/secret.key`（加密密钥，权限 600）
5. **自动删除 `install.php`**（防止二次安装）

### 5. 登录后台

访问 `http://你的域名/admin/login.php`，使用安装时设置的管理员账户登录。

---

## 使用教程

### 1. 管理章节

「章节管理」页支持三级结构：

- **+ 大部分**：如「一、语言及算法基础篇」
- **+ 小部分**：如「基础(一) C++语言基础」，需指定所属大部分
- **+ 添加章节**：题目挂载的最小单位，需指定所属小部分
- 章节支持 ↑↓ 调整顺序、编辑名称与归属、删除（非空章节禁止删除）

### 2. 一键抓取题目

「一键抓取」页：

- **单个抓取**：粘贴原网站链接（如 `https://ybt.ssoier.cn/problem_show.php?pid=1000`）或直接输入题号，选择归属章节，勾选「自动调用 AI 生成答案」
- **批量抓取**：题号范围（单次最多 500 题）或逗号分隔的题号列表，逐题执行，进度条与日志实时刷新，按钮变为「停止抓取」可随时中断
- 已存在的题号会更新题目内容（不会覆盖已有人工校对的答案状态判断以外的字段）

### 3. 管理题目

「题目管理」页：

| 操作 | 说明 |
|------|------|
| **编辑** | 修改题目全部字段；手动修改答案代码后自动标记「人工校对」 |
| **重新生成** | 调用 AI 重新生成答案；若答案已被人工修改，弹窗确认覆盖风险 |
| **删除** | 软删除，移入回收站 |
| **回收站** | 恢复 / 彻底删除 / 清空回收站 |

### 4. 系统设置

- **API Key**：留空表示保持不变，页面仅显示掩码（如 `sk-***abcd`），永不明文回显
- **模型 / Endpoint**：默认 `deepseek-v4-flash` / `https://api.deepseek.com`，兼容任意 OpenAI 风格接口
- **测试 API 连接**：发送轻量请求验证 Key 有效性
- **修改密码**：至少 8 位，包含大写、小写、数字、符号中至少三种

### 5. 前台使用

- **搜索**：导航栏输入即出建议；搜索页纯数字题号直接跳转详情页
- **侧边栏**：四级目录树逐级展开，当前章节/题目高亮，顶栏按钮可折叠（移动端为抽屉 + 遮罩）
- **题目详情**：样例与参考代码均为深色代码块，悬浮出现「复制」按钮；答案默认折叠，可展开

---

## 目录结构

```
信息学奥赛一本通答案网/
├── index.php              # 首页：Hero 搜索、统计卡片、题目列表（分页/章节筛选）
├── problem.php            # 题目详情：公式渲染、样例代码块、AI 答案折叠展示
├── search.php             # 搜索结果页：题号跳转、关键字高亮、分页
├── terms.php              # 使用协议页
├── install.php            # 安装向导（安装成功后自动删除）
├── .htaccess              # Apache 安全规则与静态资源缓存
├── admin/                 # 后台管理目录
│   ├── index.php          # 仪表盘：统计卡片 + 最近更新
│   ├── login.php          # 登录（失败 5 次锁定 15 分钟）
│   ├── logout.php         # 退出登录
│   ├── chapters.php       # 章节管理：三级分类增删改 + 排序
│   ├── problems.php       # 题目管理：列表、筛选、回收站
│   ├── problem_edit.php   # 题目编辑：全字段表单 + 答案管理
│   ├── fetch.php          # 一键抓取：单个 + 批量（进度条/日志）
│   ├── settings.php       # 系统设置：AI API、密码修改
│   └── ajax.php           # 后台 AJAX API：章节/答案/抓取/测试连接
├── api/
│   └── search_suggest.php # 搜索建议 API（前 5 条）
├── includes/              # Web 禁止直接访问（.htaccess 保护）
│   ├── config.php         # 全局配置：常量、错误处理、会话
│   ├── config.local.php   # 安装时生成的数据库配置（勿提交仓库）
│   ├── secret.key         # 安装时生成的加密密钥（勿提交仓库）
│   ├── db.php             # PDO 单例
│   ├── functions.php      # 公共函数：转义、CSRF、分页、日志
│   ├── auth.php           # 管理员认证与登录锁定
│   ├── crypto.php         # AES-256-CBC 加解密
│   ├── api_client.php     # AI API 客户端（OpenAI 兼容接口）
│   ├── scraper.php        # 原网站抓取与解析
│   ├── tree_cache.php     # 侧边栏树文件缓存
│   ├── layout.php         # 前台通用布局（顶栏/侧边栏/页脚）
│   └── admin_layout.php   # 后台通用布局
├── css/
│   ├── style.css          # 全局样式（CSS 变量、组件、动效）
│   ├── responsive.css     # 响应式断点
│   └── admin.css          # 后台样式
├── js/
│   ├── main.js            # 全局交互：Toast、复制、代码懒加载高亮
│   ├── sidebar.js         # 侧边栏树：折叠、抽屉、激活定位
│   ├── search.js          # 搜索建议（防抖 300ms）
│   └── admin.js           # 后台交互：模态框、抓取进度、AJAX
├── assets/
│   └── logo.svg           # 站点 Logo
├── cache/                 # 侧边栏树缓存（Web 禁止访问）
├── logs/                  # 运行日志 app.log（Web 禁止访问）
└── README.md
```

---

## 数据库表结构

| 表名 | 用途 | 关键字段 |
|------|------|---------|
| `parts` | 大部分 | name, sort_order |
| `subparts` | 小部分 | part_id, name, sort_order |
| `chapters` | 章节 | subpart_id, name, sort_order |
| `problems` | 题目 | pid (唯一索引), chapter_id, title, time_limit, memory_limit, description, input_desc, output_desc, input_sample, output_sample, source_url, answer_code, is_answer_manual, deleted_at (软删除) |
| `settings` | 系统配置 | key (唯一), value（API Key 为 AES-256-CBC 密文） |
| `admins` | 管理员 | username (唯一), password_hash |

---

## 安全机制

| 防护措施 | 实现方式 |
|---------|---------|
| **SQL 注入** | 所有 SQL 使用 PDO 预处理 + 参数绑定，无字符串拼接 |
| **XSS 跨站脚本** | 所有输出经 `htmlspecialchars()` 转义；抓取内容剥离 script/on* 属性 |
| **CSRF 跨站请求** | 所有 POST 表单与 AJAX 携带 CSRF Token，`hash_equals` 验证 |
| **密码存储** | 管理员密码 → `password_hash()`；登录失败 5 次锁定 15 分钟 |
| **API Key 保护** | `openssl_encrypt(AES-256-CBC)` + 随机 IV 存储；密钥文件 `secret.key` 权限 600 且 Web 不可访问；页面仅显示掩码 |
| **目录访问保护** | `includes/`、`cache/`、`logs/` 均有 `.htaccess` 拒绝访问 |
| **会话安全** | 登录时 `session_regenerate_id(true)`；Cookie `HttpOnly` + `SameSite=Lax` |
| **安装安全** | `install.php` 安装成功后自动删除；已安装状态访问安装页直接重定向 |
| **错误处理** | 生产环境不输出错误详情，统一写入 `logs/app.log` |

---

## 注意事项

### 权限要求

1. `cache/` 与 `logs/` 目录必须对 Web 进程（如 `www-data`）可写
2. `includes/` 目录需可写（安装时生成 `config.local.php` 与 `secret.key`）
3. 安装完成后建议将 `includes/` 改为只读，仅保留 `cache/`、`logs/` 可写

### 抓取说明

1. 抓取依赖原网站 `ybt.ssoier.cn` 的页面结构（h3 标题、`pshow()` 脚本、`pre` 样例），若原站改版需同步更新 `includes/scraper.php`
2. 批量抓取逐题串行执行并内置间隔，请控制单次范围（≤500 题），避免对原站造成压力
3. 抓取使用 GBK→UTF-8 自动转码，兼容原站编码

### AI 答案说明

1. 答案由 AI 自动生成，**仅供参考，可能存在错误**，前台已明确标注
2. 接口为 OpenAI 兼容格式（`/chat/completions`），默认 `https://api.deepseek.com` + `deepseek-v4-flash`，可在后台更换为任意兼容服务
3. 单次调用超时 60 秒，失败记录日志并在后台提示，不影响题目入库

### 性能说明

1. 侧边栏树使用文件缓存（`cache/sidebar_tree.php`），后台修改分类后自动清除
2. 答案代码高亮采用 IntersectionObserver 懒加载，滚动到可视区域才渲染
3. MathJax、highlight.js、字体均走 CDN 并 `defer` 加载，不阻塞首屏

---

## 故障排查

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| **PDOException: No such file or directory** | `localhost` 走 Unix socket 且路径错误 | 将数据库主机从 `localhost` 改为 `127.0.0.1` |
| **访问任何页面跳转 install.php** | `includes/config.local.php` 缺失 | 重新运行安装向导 |
| **后台按钮点击无反应** | 旧版 CSS/JS 浏览器缓存 | Ctrl+F5 强制刷新（资源已带版本号） |
| **章节页/详情页 500** | 查看 `logs/app.log` 定位 | 日志中有具体异常信息 |
| **抓取失败：未找到标题** | 原网站页面结构变化 | 更新 `includes/scraper.php` 解析规则 |
| **AI 答案生成失败** | Key 无效 / 模型名错误 / 网络不通 | 后台「系统设置」→「测试 API 连接」排查 |
| **侧边栏不显示新章节** | 缓存未清除 | 后台任意分类操作会自动清缓存；或手动删除 `cache/sidebar_tree.php` |
| **登录被锁定** | 连续失败 5 次 | 等待 15 分钟自动解锁 |
| **公式不渲染** | MathJax CDN 被墙 | 检查浏览器控制台，必要时更换 CDN |

### 日志位置

- 应用日志：`logs/app.log`（异常、登录失败、API 错误、抓取错误）
- Web 服务器错误日志：Nginx/Apache 默认位置

---

## 设计理念

- **配色**：深石板蓝主色 (#334155) + 翡翠绿强调 (#059669) + 暖灰背景 (#f8fafc) + 纯白卡片，无大面积渐变与霓虹
- **字体**：Inter 正文 + JetBrains Mono 代码，Google Fonts 引入
- **排版**：正文 15px/1.7，清晰字号层级（h1 28 / h2 22 / h3 18 / h4 16）
- **动效**：150–250ms 过渡，`cubic-bezier(0.4, 0, 0.2, 1)`，列表 hover 仅变色不位移
- **响应式**：<768px 侧边栏转抽屉 + 半透明遮罩，表格横向滚动，触控友好
- **安静不打扰**：无弹窗骚扰，状态用 Toast 与徽标表达，专业低调的开发者工具语言

---

## 技术架构

- **后端**：原生 PHP 8（无框架），PDO 单例，PSR-12 风格
- **前端**：原生 HTML5 + CSS3 + ES6（无构建工具、无前端框架），Fetch API AJAX
- **公式渲染**：MathJax 3（tex-mml-chtml，CDN + defer）
- **代码高亮**：highlight.js 11（atom-one-dark 深色主题，CDN + 懒加载）
- **加密**：OpenSSL AES-256-CBC（API Key 可逆存储）+ `password_hash`（密码不可逆哈希）
- **部署**：仅需 PHP + MySQL，无 Composer 依赖

---

## 开源协议

MIT License

Copyright (c) 2026 SZY Innovation Studio

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

**Powered by SZY Innovation Studio**
