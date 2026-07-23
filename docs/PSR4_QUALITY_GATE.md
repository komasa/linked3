# PSR-4 迁移质量门槛 Checklist

> 标准：行业领域最高规格规范标准的商业生产级
> 每个模块迁移完成后，必须逐项打勾确认。任一项未通过，不得进入下一模块。

---

## 一、迁移正确性

### 1.1 类名与文件名
- [ ] 类名已从 `Linked3_XXX_YYY` 转换为 PascalCase
- [ ] 文件名与类名一致（PSR-4 规范：`FooBar.php` 对应 `class FooBar`）
- [ ] 文件路径与命名空间一一对应（`Linked3\Classes\AI` → `src/Classes/AI/`）
- [ ] 命名空间声明与目录结构一致

### 1.2 命名设计审查
- [ ] 类名不与命名空间重复（反模式：`Linked3\AI\Pipeline\AIPipelineBootstrap`）
- [ ] 类名语义清晰，可独立理解（`Bootstrap` 优于 `AIPipelineBootstrap` 当命名空间已含 `AI\Pipeline`）
- [ ] 无缩写歧义（`SttManager` 可接受，`M` 不可接受）

### 1.3 引用更新
- [ ] PHP 文件中的 `use` 语句已更新
- [ ] PHP 文件中的 `new`、`::method`、`::class` 调用已更新
- [ ] PHP 文件中的 `class_exists()` 检查已更新
- [ ] **hook 注册中的字符串类名已更新**（`add_action('init', ['Linked3_Foo', 'bar'])`）
- [ ] **`call_user_func` 中的字符串类名已更新**
- [ ] YAML 配置中的 `handler: "Linked3_Foo::method"` 已更新
- [ ] JSON 配置中的 `"class_name": "Linked3_Foo"` 已更新
- [ ] admin/ 模板文件中的引用已更新
- [ ] lib/ 文件中的引用已更新

### 1.4 use 语句注入
- [ ] 裸引用（无 use 语句）已补充 `use` 声明
- [ ] use 语句的命名空间路径正确
- [ ] 无重复 use 语句
- [ ] 同命名空间下的类不需要 use（确认是否已正确处理）

### 1.5 declare(strict_types=1)
- [ ] 每个迁移后的文件都包含 `declare(strict_types=1)`
- [ ] 位置在 `<?php` 之后、命名空间声明之前

---

## 二、功能等价性

### 2.1 静态分析
- [ ] `php -l`（语法检查）通过，零错误
- [ ] PHPStan level 6+ 通过（或记录已知 baseline）
- [ ] PHPCS PSR-4 检查通过
- [ ] 无新增 warning

### 2.2 自动化测试
- [ ] 已有测试全部通过（`phpunit` / `pest`）
- [ ] 迁移模块的核心入口有测试覆盖（无测试则标注「待补」）
- [ ] 新增 characterization test（行为快照）验证迁移前后行为一致

### 2.3 运行时验证
- [ ] autoload 能正确加载迁移后的类（手动 `class_exists()` 验证）
- [ ] 动态分发入口（YAML/JSON handler）能正确调用迁移后的类
- [ ] hook 注册能正确触发迁移后的类方法
- [ ] AJAX endpoint 能正确路由到迁移后的类

### 2.4 依赖链验证
- [ ] 上游依赖方（引用本模块的文件）已同步更新
- [ ] 下游依赖（本模块引用的其他类）仍可用
- [ ] 跨模块引用无断链

---

## 三、回滚可执行性

### 3.1 版本控制
- [ ] 迁移前已打 tag：`pre-psr4-p{X}`
- [ ] 迁移后已打 tag：`post-psr4-p{X}`
- [ ] 每个 commit 只包含一个模块的迁移（可独立 revert）
- [ ] commit message 格式：`refactor({Module}): migrate to PSR-4 naming + strict types`

### 3.2 回滚验证
- [ ] `git diff pre-psr4-p{X}..post-psr4-p{X}` 可清晰展示变更范围
- [ ] 回滚后 `git reset --hard pre-psr4-p{X}` 可恢复到迁移前状态
- [ ] 回滚后 autoload 兼容旧类名（autoload.php 有 fallback）

### 3.3 部署安全
- [ ] 部署脚本包含 `opcache_reset()`
- [ ] 部署在维护模式下执行
- [ ] 部署后清空 object cache（`wp cache flush`）
- [ ] 数据库序列化数据迁移脚本已准备（如有需要）

---

## 四、商业生产级附加项

### 4.1 代码质量
- [ ] 无 God Class（单个类 > 500 行或 > 20 方法需标注拆分计划）
- [ ] 类型声明补全（参数类型 100%，返回类型 100%）
- [ ] 无 `@SuppressWarnings` 掩盖问题
- [ ] 无 `TODO`/`FIXME` 新增（已有需标注）

### 4.2 文档
- [ ] 迁移后的类有 PHPDoc `@package` 标签
- [ ] UPGRADE.md 已记录命名变更映射表
- [ ] CHANGELOG.md 已记录本次迁移
- [ ] 旧类名 → 新类名映射表已生成

### 4.3 CI/CD
- [ ] CI 流水线包含 `php -l` 全量检查
- [ ] CI 流水线包含 PHPStan level 6+
- [ ] CI 流水线包含 PHPCS PSR-4 检查
- [ ] CI 流水线阻断机制：任一检查失败则不允许合并

---

## 五、签名确认

| 模块 | 迁移人 | 审核人 | 完成时间 | 备注 |
|------|--------|--------|----------|------|
| P0: License/Admin/Templates/E2E/Addons/AIForms/Rest/Scale | 乐观拥抱者 | | | ✅ 已完成 |
| P1: AI/Chat/Collect/ContentWriter/Distribute/Media/OS/Publish/SEO | | | | |
| P2: Billing/BookFactory/CognitiveOS/Core/Dashboard/Diagram/Genesis/MetaLever | | | | |
| P3: Agent/Genesis(剩余)/Pipeline/Security/STT/Templates/Traits/V15 | | | | |

---

## 待决策点

1. **类名截断策略**：`Linked3_AI_Pipeline_Bootstrap` → `AIPipelineBootstrap` 还是 `Bootstrap`？
   - 前者：FQCN 重复但类名自解释
   - 后者：PSR-4 干净但依赖 use 语句理解上下文
   - **建议**：取中间值，去掉 namespace 已包含的前缀 → `Bootstrap`（namespace `Linked3\Classes\AI\Pipeline` 已提供上下文）

2. **数据库迁移**：wp_options/postmeta/usermeta/transient 中可能有序列化的旧类名，需单独 WP-CLI 命令处理

3. **既有 bug 处理**：部分文件存在裸引用且无 use 语句（如 Billing 中的 `Linked3_Token_Meter::instance()`），迁移时不恶化但不修复。是否在本次一并修复？
