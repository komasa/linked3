# Linked3 AI v27.6.2 — 部署文档

> **版本**: v27.6.2
> **更新日期**: 2026-07-21
> **适用环境**: WordPress 6.0+ / PHP 7.4+

---

## 一、部署前检查

### 1.1 环境要求

| 项目 | 最低版本 | 推荐版本 |
|------|----------|----------|
| PHP | 7.4 | 8.2+ |
| WordPress | 6.0 | 6.5+ |
| MySQL | 5.7 | 8.0+ |
| 内存限制 | 256M | 512M+ |
| 最大执行时间 | 120s | 300s |

### 1.2 PHP 扩展检查

```bash
# 必需扩展
php -m | grep -E "curl|json|mbstring|openssl|pdo|xml|zip"

# 推荐扩展（提升性能）
php -m | grep -E "opcache|redis|imagick|intl"
```

### 1.3 WordPress 配置确认

```php
// wp-config.php 确保以下配置
define('WP_DEBUG', true);           // 调试期开启
define('WP_DEBUG_LOG', true);       // 日志写入文件
define('WP_DEBUG_DISPLAY', false);  // 不在页面显示错误
define('DISALLOW_FILE_EDIT', true); // 禁止后台编辑文件
@ini_set('memory_limit', '512M');
@set_time_limit(300);
```

---

## 二、安装步骤

### 2.1 全新安装

```bash
# 1. 上传插件
scp linked3-v27.6.2-full.tar.gz user@server:/tmp/
ssh user@server
cd /path/to/wordpress/wp-content/plugins/
tar -xzf /tmp/linked3-v27.6.2-full.tar.gz
mv linked3 linked3-ai   # 确保目录名为 linked3-ai

# 2. 修复权限
chown -R www-data:www-data linked3-ai/
find linked3-ai/ -type d -exec chmod 755 {} \;
find linked3-ai/ -type f -exec chmod 644 {} \;

# 3. WordPress 后台激活
# 浏览器访问: wp-admin/plugins.php → 激活 "Linked3 AI"
```

### 2.2 升级安装（从旧版本）

```bash
# 1. 备份当前版本
cp -r wp-content/plugins/linked3-ai/ wp-content/plugins/linked3-ai-backup-$(date +%Y%m%d)/

# 2. 备份数据库
mysqldump -u root -p wordpress > /tmp/wordpress-backup-$(date +%Y%m%d).sql

# 3. FTP 删除旧目录（重要！避免文件残留）
rm -rf wp-content/plugins/linked3-ai/

# 4. 上传新版本
tar -xzf /tmp/linked3-v27.6.2-full.tar.gz -C wp-content/plugins/
mv wp-content/plugins/linked3 linked3-ai

# 5. 修复权限
chown -R www-data:www-data linked3-ai/
find linked3-ai/ -type d -exec chmod 755 {} \;
find linked3-ai/ -type f -exec chmod 644 {} \;

# 6. WordPress 后台 → 如插件被禁用，重新激活
```

### 2.3 后台 ZIP 上传安装

```
1. WordPress 后台 → 插件 → 安装新插件 → 上传插件
2. 选择 linked3-v27.6.2-full.zip
3. 点击"立即安装"
4. 安装完成后 → "启用插件"
```

> **注意**: 如果从旧版本升级，必须先在后台禁用插件 → FTP删除旧目录 → 上传新版本 → 重新激活。直接覆盖安装可能导致旧文件残留。

---

## 三、配置指南

### 3.1 API 密钥配置

```
WordPress 后台 → Linked3 AI → 设置 → AI 配置
- 填入 API Key (OpenAI / DeepSeek / 通义千问 等)
- 选择默认模型
- 测试连接
```

### 3.2 SEO 模块配置

```
WordPress 后台 → Linked3 AI → SEO 设置
- 配置搜索引擎推送 (百度/Google/Bing)
- 设置关键词生成策略
- 配置自动发布规则
```

### 3.3 认知操作系统 (COS)

```
WordPress 后台 → Linked3 AI → 认知操作系统
- 首次使用需初始化 COS Engine
- 配置元学习杠杆链
- 设置技能结晶阈值
```

---

## 四、冒烟测试清单

> **目的**: 部署后快速验证核心功能是否正常。每项测试需在 **5分钟内** 完成。

### 4.1 基础冒烟测试（必做，10项）

| # | 测试项 | 操作步骤 | 预期结果 | 通过 |
|---|--------|----------|----------|------|
| 1 | 插件激活 | 后台 → 插件 → 激活 Linked3 AI | 无报错，激活成功 | ☐ |
| 2 | 主菜单加载 | 后台 → 点击"Linked3 AI"主菜单 | Dashboard 页面正常显示 | ☐ |
| 3 | 版本号检查 | 后台 → Linked3 AI → 关于 | 显示 v27.6.2 | ☐ |
| 4 | AI 连接测试 | 设置 → AI 配置 → 测试连接 | 返回"连接成功" | ☐ |
| 5 | 关键词生成 | SEO → 关键词工具 → 输入种子词 → 生成 | 返回关键词列表 | ☐ |
| 6 | 文章生成 | 内容写作 → 输入主题 → 生成文章 | 返回完整文章 | ☐ |
| 7 | Genesis Stage1 | Genesis → 输入主题 → Stage1 生成 beats | 返回 beats JSON | ☐ |
| 8 | Genesis Stage2 | Stage1 完成后 → Stage2 生成分镜 | 返回 panels 数组 | ☐ |
| 9 | AJAX Nonce | 浏览器 F12 → Network → 任意 AJAX 请求 | 请求包含 nonce 参数 | ☐ |
| 10 | debug.log 检查 | 查看 wp-content/debug.log | 无 Fatal Error / Warning | ☐ |

### 4.2 模块冒烟测试（按需，20项）

| # | 模块 | 测试项 | 操作 | 预期 | 通过 |
|---|------|--------|------|------|------|
| 11 | 小红书 | 脚本生成 | XHS → 输入主题 → 生成 | 返回多页图文脚本 | ☐ |
| 12 | 漫画 | 脚本生成 | Genesis → 漫画模式 → 生成 | 返回漫画分镜 | ☐ |
| 13 | 视频 | 脚本生成 | Media → 视频脚本 → 生成 | 返回视频分镜 | ☐ |
| 14 | 图表 | 图表生成 | Charts → 输入数据 → 生成 | 返回图表配置 | ☐ |
| 15 | 书籍 | 书籍生成 | BookFactory → 输入主题 → 生成 | 返回书籍大纲 | ☐ |
| 16 | WooCommerce | 商品描述 | WC → 选择商品 → AI 生成描述 | 描述写入商品 | ☐ |
| 17 | 认知系统 | 技能查询 | COS → 查看技能列表 | 显示已结晶技能 | ☐ |
| 18 | 元杠杆 | 杠杆链 | COS → 杠杆链 → 运行 | 返回杠杆 trace | ☐ |
| 19 | 计费 | 配额检查 | Billing → 查看用量 | 显示当前配额 | ☐ |
| 20 | 采集 | 内容采集 | Collect → 输入URL → 采集 | 返回采集内容 | ☐ |
| 21 | SEO推送 | 百度推送 | SEO → 推送 → 百度 | 返回推送结果 | ☐ |
| 22 | 图表 | 4Band模式 | Charts → 4band 模式 → 生成 | 正确渲染4band | ☐ |
| 23 | 图表 | Unified模式 | Charts → 4band-unified → 生成 | 正确渲染unified | ☐ |
| 24 | AutoGPT | 任务创建 | AutoGPT → 创建任务 | 任务保存成功 | ☐ |
| 25 | AutoGPT | 任务执行 | AutoGPT → 运行任务 | 任务执行完成 | ☐ |
| 26 | 向量 | 向量搜索 | Vector → 搜索 | 返回相似文档 | ☐ |
| 27 | 许可证 | 状态检查 | License → 查看状态 | 显示许可证信息 | ☐ |
| 28 | Addons | 状态检查 | Addons → 查看列表 | 显示已安装 addon | ☐ |
| 29 | Agent | 工作流 | Agent → 创建工作流 | 工作流保存 | ☐ |
| 30 | 仪表盘 | 数据加载 | Dashboard → 刷新 | 所有 widget 加载 | ☐ |

### 4.3 安全冒烟测试（必做，5项）

| # | 测试项 | 操作 | 预期 | 通过 |
|---|--------|------|------|------|
| 31 | 未登录拦截 | 退出登录 → 直接访问 AJAX URL | 返回 403 或 wp_die | ☐ |
| 32 | Nonce 篡改 | F12 → 修改 nonce 值 → 发请求 | 返回 403 | ☐ |
| 33 | 权限隔离 | 普通作者账号 → 尝试管理操作 | 返回 403 | ☐ |
| 34 | SQL 注入 | 在输入框输入 `' OR 1=1 --` | 无注入成功 | ☐ |
| 35 | XSS 检查 | 在输入框输入 `<script>alert(1)</script>` | 输出被转义 | ☐ |

---

## 五、回滚方案

### 5.1 快速回滚

```bash
# 1. 禁用插件
wp plugin deactivate linked3-ai

# 2. 恢复备份
rm -rf wp-content/plugins/linked3-ai/
cp -r wp-content/plugins/linked3-ai-backup-YYYYMMDD/ wp-content/plugins/linked3-ai/

# 3. 恢复数据库（如需）
mysql -u root -p wordpress < /tmp/wordpress-backup-YYYYMMDD.sql

# 4. 重新激活
wp plugin activate linked3-ai
```

### 5.2 紧急禁用

```bash
# 临时禁用插件（不删除数据）
mv wp-content/plugins/linked3-ai/ wp-content/plugins/linked3-ai.disabled/
```

---

## 六、监控与日志

### 6.1 日志文件位置

| 日志 | 路径 | 说明 |
|------|------|------|
| WordPress | `wp-content/debug.log` | PHP 错误/警告 |
| Linked3 | `wp-content/uploads/linked3-logs/` | 插件专用日志 |
| AJAX | `wp-content/uploads/linked3-logs/ajax-*.log` | AJAX 请求日志 |
| AI调用 | `wp-content/uploads/linked3-logs/ai-call-*.log` | AI API 调用日志 |

### 6.2 关键监控指标

```bash
# 检查 Fatal Error
grep -c "Fatal Error" wp-content/debug.log

# 检查 AJAX 失败率
grep "AJAX.*failed" wp-content/uploads/linked3-logs/ajax-*.log | wc -l

# 检查 AI 调用延迟
grep "ai_call_duration" wp-content/uploads/linked3-logs/ai-call-*.log | tail -20
```

### 6.3 告警阈值

| 指标 | 阈值 | 处理 |
|------|------|------|
| Fatal Error / 24h | > 0 | 立即排查 |
| AJAX 失败率 | > 5% | 检查 nonce / 权限 |
| AI 调用延迟 | > 30s | 检查网络 / 模型 |
| 内存使用 | > 80% | 提升 memory_limit |
| 磁盘空间 | < 1GB | 清理日志 |

---

## 七、常见问题排查

### Q1: 激活后白屏

```bash
# 检查 PHP 版本
php -v  # 必须 >= 7.4

# 检查 debug.log
tail -50 wp-content/debug.log

# 检查 PHP 内存
php -i | grep memory_limit  # 建议 >= 256M
```

### Q2: AJAX 返回 403

```bash
# 检查 nonce 是否过期
# WordPress nonce 有效期 24 小时

# 检查用户权限
wp cap list <username>  # 需要 edit_posts

# 检查 debug.log
grep "403" wp-content/debug.log
```

### Q3: AI 调用超时

```bash
# 检查 API Key
wp option get linked3_ai_api_key

# 检查网络
curl -I https://api.openai.com

# 提升超时限制
# wp-config.php 添加:
define('WP_HTTP_TIMEOUT', 60);
```

### Q4: Genesis Stage2 失败

```bash
# 检查 beats 数据格式
# F12 → Network → stage2 请求 → 查看 POST body

# 检查 error_log
grep "stage2" wp-content/debug.log

# 确认 FPExtractor / PromptAssembler 类已加载
wp eval "var_dump(class_exists('FPExtractor'));"
```

### Q5: 版本号不一致

```bash
# 检查三处版本号
grep "Version:" wp-content/plugins/linked3-ai/linked3.php
grep "LINKED3_VERSION" wp-content/plugins/linked3-ai/linked3.php
grep "version" wp-content/plugins/linked3-ai/composer.json

# 三处都应为 27.6.2
```

---

## 八、性能优化建议

### 8.1 PHP 层面

```ini
; php.ini 推荐
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=60
realpath_cache_size=4096K
realpath_cache_ttl=600
```

### 8.2 WordPress 层面

```php
// wp-config.php 推荐
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '1024M');
define('CONCATENATE_SCRIPTS', false); // 调试期
define('COMPRESS_SCRIPTS', true);     // 生产期
```

### 8.3 数据库优化

```sql
-- 定期清理 transient
DELETE FROM wp_options WHERE option_name LIKE '_transient_linked3_%';

-- 优化 AI 日志表
OPTIMIZE TABLE wp_linked3_ai_logs;
```

---

## 九、附录

### 9.1 文件结构概览

```
linked3-ai/
├── linked3.php                 # 主入口
├── composer.json               # 依赖声明
├── src/
│   ├── autoload.php            # PSR-4 自动加载
│   ├── Includes/               # 核心工具类
│   ├── Classes/
│   │   ├── Core/               # AI Dispatcher, AJAX Guard
│   │   ├── Dashboard/          # 后台仪表盘
│   │   ├── Genesis/            # 漫画/视频脚本生成
│   │   ├── SEO/                # SEO 优化
│   │   ├── Billing/            # 计费系统
│   │   ├── CognitiveOS/        # 认知操作系统
│   │   ├── MetaLever/          # 元学习杠杆
│   │   ├── AutoGPT/            # 自动化 Agent
│   │   ├── BookFactory/        # 书籍生成
│   │   └── ...                 # 其他模块
│   └── Includes/               # 辅助函数
├── admin/                      # 后台视图/JS/CSS
├── assets/                     # 静态资源
├── languages/                  # i18n 文件
├── docs/                       # 文档
└── tests/                      # 测试
```

### 9.2 关键 Hook 列表

| Hook | 类型 | 说明 |
|------|------|------|
| `linked3/ai_before_call` | action | AI 调用前触发 |
| `linked3/ai_after_call` | action | AI 调用后触发 |
| `linked3/content/after_generate` | action | 内容生成后 |
| `linked3/seo/after_optimize` | action | SEO 优化后 |
| `linked3/billing/before_charge` | action | 计费前 |
| `linked3/billing/after_charge` | action | 计费后 |

### 9.3 紧急联系

- **GitHub 仓库**: https://github.com/komasa/linked3
- **CI/CD**: `.github/workflows/ci.yml`
- **问题反馈**: GitHub Issues
