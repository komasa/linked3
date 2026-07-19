# Linked3 AI v18.5 — 写书工厂最佳实践手册

> 版本: 18.5.0 | 日期: 2026-06-28
> 基于 5D 演化框架三代凝结 (G1→G2→G3)

---

## 一、安装与激活

### 1.1 安装步骤

1. **上传插件**: WordPress后台 → 插件 → 安装新插件 → 上传 → 选择 `linked3-ai-v18.5.zip`
2. **激活插件**: 安装完成后点击"启用"
3. **验证版本**: 插件列表中确认版本号为 `18.5.0`

### 1.2 环境要求

| 项目 | 要求 |
|------|------|
| WordPress | ≥ 6.0 |
| PHP | ≥ 7.4 (推荐 8.0+) |
| PHP扩展 | yaml (用于解析book.yaml) |
| 内存限制 | ≥ 256MB |
| 最大执行时间 | ≥ 120秒 (写书是长流程) |

### 1.3 首次配置

1. 进入 **Linked3 AI → 设置 → AI模型配置**
2. 配置至少一个AI模型 (推荐 GPT-4o 或 Claude 3.5)
3. 进入 **Linked3 AI → 写书式学习** 标签页
4. 确认看到"🚀 写书工厂 v18.5"控制台

---

## 二、工厂化三大原则

### 原则1: YAML即流程

6步流程定义在 `src/Classes/Genesis/pipelines/book.yaml`，代码只执行，不硬编码流程顺序。

**修改流程**: 编辑 `book.yaml`，无需改代码。例如关闭step1演示:
```yaml
- id: step1_demo
  optional: true  # 改为 false 则强制执行
```

### 原则2: 状态即真相

`Linked3_Book_Project_State` 是唯一真相源。所有状态读写通过它:
- 持久化: WP transient (7天) + JSON文件 (永久)
- 中断恢复: 自动从断点继续
- 多项目隔离: 每个项目独立 project_id

### 原则3: 复用即降维

不重写基座，只在其上扩展:
- `Long_Form_Writer` → 复用其分段生成能力
- `Pipeline_Engine` → 复用其YAML驱动+中断恢复
- `Agent_Orchestrator` → 复用其状态机

---

## 三、工厂化五大约束

### 约束1: 每步必须可中断恢复

复用 `Pipeline_Engine::resume()` + `Book_Project_State` 持久化。用户关浏览器后重访，自动恢复进度。

### 约束2: 每步必须触发进度事件

```php
do_action('linked3/book/step_start', $project_id, $step_id);
// ... 执行步骤 ...
do_action('linked3/book/step_complete', $project_id, $step_id, $result);
```

前端2秒轮询 `linked3_book_factory_progress` 端点，实时更新UI。

### 约束3: 每步必须可选

`book.yaml` 中 `optional: true` 的步骤可跳过。用户可在控制台勾选跳过演示/探索/审阅。

### 约束4: 每步必须可降级

handler方法不存在时，记录警告并跳过，不中断整个管线。

### 约束5: 每步必须可审计

`Book_Project_State::log_step()` 记录每步开始/结束时间、token消耗、成本。

---

## 四、G2/G3 三大优化 + 一大透明化

### 优化1: 实时同步 (S11)

State变更 → 前端2秒轮询 → UI自动刷新。用户无需手动刷新页面。

### 优化2: 上下文缓存 (S12)

`final_outline` 缓存在State中，step4扩写直接读取，避免每章重新加载大纲。每本书节省约12次AI调用。

### 优化3: 增量拼接 (S13)

审阅后仅重拼受影响章节，非全量重拼。12章×5节=60节 → 仅1节/次审阅。

### 透明化: 成本看板 (S20)

每步记录token消耗，实时显示:
- 80%预算时黄色警告
- 100%预算时硬停止
- 默认预算 $5.00 (可在 `book.yaml` 调整)

---

## 五、用户操作流程

```
1. 进入 "Linked3 AI → 写书式学习"
2. 在工厂控制台输入:
   - 书名 (如《AI产品经理实战手册》)
   - 类型 (图书/论文/剧本/手册/教材/白皮书)
   - 模式 (手工写作/语音写作/AI写书)
   - 档位 (快速1次/标准3次/深度10次大纲迭代)
3. 点击 "🚀 一键启动写书工厂"
4. 观察实时进度面板:
   - 进度条 + 当前步骤 + 章节进度
   - 成本看板 (已用$/Token/耗时)
   - 实时日志流
5. 完成后选择下载格式:
   - 📄 Markdown
   - 🌐 HTML
   - 📋 复制到剪贴板
6. (可选) 章节级重生成 / 大纲版本回退
```

**用户操作维度**: 3次选择 + 1次点击 = **4维** (从v17.2的180维降维97.8%)

---

## 六、扩展指南

### 6.1 扩展新类型

编辑 `class-linked3-type-mode-router.php`:

```php
'novel_ai' => array(
    'type_unit'        => '部',
    'yaml_config'      => array( 'max_outline_iterations' => 5 ),
    'output_template'  => 'novel_default',
    'prompt_overrides' => array(
        'step4_expand' => '请以小说叙事风格扩写，注重人物对话与场景描写',
    ),
),
```

### 6.2 扩展新步骤

1. 在 `book.yaml` 添加步骤定义
2. 在 `class-linked3-book-factory.php` 添加 handler:

```php
public function pipeline_step7_polish( $context, $params, $prev ) {
    // 润色逻辑
    $this->state->set('status', 'polishing')->save_state();
    // ... 调用AI ...
    $this->state->log_step('step7_polish', 'complete', $result);
    return $result;
}
```

### 6.3 调整预算上限

编辑 `book.yaml`:
```yaml
cost_tracking:
  budget_hard_limit: 10.00  # 从 $5 调整为 $10
```

或在代码中动态设置:
```php
$state->set('budget_total', 10.00)->save_state();
```

---

## 七、故障排查

### 问题1: 工厂启动后无进度更新

**检查**:
1. AI模型是否配置正确 (设置 → AI模型配置)
2. PHP yaml扩展是否安装 (`php -m | grep yaml`)
3. 查看 `wp-content/uploads/linked3-book-projects/{project_id}.json` 是否生成

### 问题2: 中断后无法恢复

**检查**:
1. WP transient 是否被过早清理 (某些缓存插件会清理transient)
2. JSON备份文件是否存在: `wp-content/uploads/linked3-book-projects/`
3. 手动恢复: 访问 `wp-admin/admin.php?page=linked3-ai-dashboard&book_project={project_id}`

### 问题3: 成本看板不更新

**检查**:
1. AI响应是否包含 `usage` 字段 (部分模型不返回token统计)
2. `cost_log` 是否在State中正确记录

### 问题4: PHP超时

**对策**: 工厂已支持wp_cron分步执行。若仍超时，调整 `php.ini`:
```ini
max_execution_time = 300
memory_limit = 512M
```

---

## 八、回退预案

若 v18.5 上线后出现问题，可回退至 v17.2:

1. 停用 v18.5 插件
2. 上传 v17.2 版本插件zip
3. 激活 v17.2

**数据兼容**: v18.5 的写书项目数据存储在 `wp-content/uploads/linked3-book-projects/`，不影响v17.2的任何数据。

---

## 九、版本演进路线

| 版本 | 代际 | 核心变更 |
|------|------|---------|
| v17.2 | 基线 | 提示词生成器 (手动模式) |
| v18.5 | G1+G2+G3 | YAML工厂 + 状态机 + 路由 + 拼接 + 实时同步 + 成本透明 |
| v19.0 | 未来 | 多人协作 + 版本对比 + AI风格学习 |

---

## 十、核心指标对比

| 指标 | v17.2 (基线) | v18.5 (终版) | 改善 |
|------|-------------|-------------|------|
| 用户操作维度 | 180维 | 4维 | ↓97.8% |
| 信息熵 H(User) | 11.55 bits | 5.75 bits | ↓50% |
| 完成一本12章×5节书 | 不可行 | ~30分钟 | ✅ |
| 中断恢复 | ❌ | ✅ | 新增 |
| 成本透明 | ❌ | ✅ 实时看板 | 新增 |
| 输出格式 | 仅提示词 | Markdown+HTML+剪贴板 | ↑3种 |
| 章节级重生成 | ❌ | ✅ | 新增 |
| 大纲版本回退 | ❌ | ✅ | 新增 |

---

*Linked3 AI v18.5 最佳实践手册 · A部契约官终版归档*
