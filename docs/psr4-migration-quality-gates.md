# PSR-4 迁移 — 质量门槛清单

> 每个模块迁移完成后，必须逐项检查通过，才能标记为 done。

## 通用门槛（所有模块）

| # | 检查项 | 命令/方法 | 通过标准 |
|---|--------|-----------|----------|
| Q1 | PHP语法检查 | `find <module> -name "*.php" -exec php -l {} \;` | 零错误 |
| Q2 | PHPCS (PSR-12+WPCS) | `phpcs --standard=phpcs.xml <module>/` | 零error，warning≤3 |
| Q3 | PHPStan Level 8 | `phpstan analyse <module>/ --level=8` | 零error |
| Q4 | 旧类名残留扫描 | `rg "Linked3_<OldPattern>" <module>/` | 零命中 |
| Q5 | 旧文件名残留扫描 | `find <module>/ -name "class-linked3-*"` | 零命中 |
| Q6 | autoload可加载 | PHP脚本: `class_exists(<NewClass>::class)` | 所有类返回true |
| Q7 | strict_types声明 | `rg "declare(strict_types=1)" <module>/*.php` | 每个文件首行有 |
| Q8 | namespace声明 | `rg "^namespace " <module>/*.php` | 每个文件有正确命名空间 |

## 模块专属门槛

### P0 — 基础设施 (autoload, composer.json)
- [ ] `composer dump-autoload` 无报错
- [ ] 所有PSR-4映射在 `vendor/composer/autoload_psr4.php` 中可见
- [ ] 旧autoload文件保留但标记 `@deprecated`

### P1 — 模板与admin入口
- [ ] WordPress后台页面可正常加载（无Fatal）
- [ ] 所有菜单项可点击跳转
- [ ] AJAX请求返回200（至少不报class not found）

### P2 — 核心业务类 (AI Pipeline, Publish, Genesis)
- [ ] 关键类实例化测试通过（PHPUnit critical suite）
- [ ] AI Pipeline完整跑通一次（手动或集成测试）
- [ ] 发布流程冒烟测试通过

### P3 — OS模块及其他
- [ ] alias layer仍有效（旧类名可被class_alias解析）
- [ ] DB序列化数据反序列化不产生 `__PHP_Incomplete_Class`
- [ ] 旧类名引用触发 `E_USER_DEPRECATED` 但不Fatal

## 中间态验证（混合命名期）

> 17个模块串行迁移，每个模块迁移完到下一个模块迁移前，系统处于"部分新命名+部分旧命名"的混合态。

- [ ] 每个模块迁移后，运行全量回归测试（不仅测本模块）
- [ ] 如无全量测试套件，至少手动触发跨模块核心流程
- [ ] 确认pipeline实际执行了所有step（不只是看有没有报错）

## 数据库序列化数据迁移（P0必做）

> WordPress序列化数据里类名是硬编码字符串，代码迁移但DB没同步会导致unserialize失败或静默失效。

- [ ] 扫描脚本：扫出 wp_options/postmeta/usermeta/transient 中所有含旧类名的序列化数据
- [ ] 分批迁移脚本：每批有限事务边界，失败可重试（避免超时）
- [ ] 迁移后验证脚本：确认无旧类名残留
- [ ] 回滚脚本：把新类名改回旧类名（如需回滚代码）

**注意：** 序列化数据里 `s:27:"Linked3_AI_Pipeline_Bootstrap"` 这种长度也要跟着改，光替换字符串不够。

## 回滚验证

- [ ] 回滚后必须执行数据库序列化数据扫描，确认无新类名残留
- [ ] 回滚方式用 `git revert` 不用 `git reset --hard`（保护remote历史）
- [ ] 每个模块迁移完成后单独打tag（如 `pre-psr4-p1-module-X`），不只阶段边界

## 维护模式时间跨度

> 维护模式必须覆盖代码迁移→验证→DB迁移→验证全程，不是跑完脚本就解除。

- [ ] 代码迁移开始前进维护模式
- [ ] 清cache（顺序：先维护模式，再清cache，最后跑迁移）
- [ ] 代码迁移→验证→DB迁移→验证 全程不开放访问
- [ ] 串行方案下维护模式分段：每个P阶段完成后短暂开放验证，再进下一阶段

## 回滚锚点

| 阶段 | Tag | 用途 |
|------|-----|------|
| P1开始前 | `pre-psr4-p1` | 已打 |
| P1完成后 | `psr4-p1-done` | 已打 |
| P2完成后 | `psr4-p2-done` | 待打 |
| P3完成后 | `psr4-p3-done` | 待打 |
| 每个模块 | `pre-psr4-p<N>-module-<X>` | 待打 |

## 签字流程

1. 乐观拥抱者完成模块迁移后，自行跑Q1-Q8通用门槛
2. 开发老王复核专属门槛 + CI全绿
3. 风险排雷兵抽查Q4/Q5 + 回滚tag验证 + DB序列化数据扫描
4. 三方确认后，打tag，进入下一阶段
