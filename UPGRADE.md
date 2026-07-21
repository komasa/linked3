# Linked3 AI v19.1 升级指南

## 从 v19.0 升级到 v19.1

### 升级前准备

1. **备份现有数据**
   - 备份 WordPress 数据库
   - 备份 `wp-content/uploads/linked3-books/` 目录（包含所有书稿项目）
   - 备份当前插件目录 `wp-content/plugins/linked3-ai/`

2. **检查环境要求**
   - PHP ≥ 7.4（推荐 8.0+）
   - WordPress ≥ 6.0
   - 确保 `wp-content/uploads/` 可写

### 升级步骤

#### 方法一：WordPress 后台上传（推荐）

1. 进入 WordPress 后台 → 插件 → 安装插件 → 上传插件
2. 选择 `linked3-ai-v19.0.0.zip`
3. 点击"立即安装"
4. 安装完成后点击"启用"（如果之前已启用，会自动替换）

#### 方法二：FTP 手动上传

1. 解压 `linked3-ai-v19.0.0.zip`
2. 将 `linked3-ai` 目录上传到 `wp-content/plugins/`
3. 覆盖旧版本文件
4. 进入 WordPress 后台 → 插件，确认插件已启用

### 升级后验证

1. **检查插件版本**：后台 → 插件 → 已安装插件，确认版本号为 19.0.0
2. **检查现有项目**：进入写书工厂页面，确认之前的项目仍然可见
3. **测试新建项目**：创建一个测试书稿，验证全流程正常
4. **检查异步执行**：新建项目后观察是否自动执行（无需手动点击"下一步"）

### 状态迁移说明

v19.0 引入了状态 schema 版本号（`schema_version`）。升级后，旧项目的状态文件会自动迁移：
- v1（无版本号）→ v2：自动添加 `schema_version` 字段
- 迁移在首次加载项目时自动完成，无需手动操作

### 新功能使用

#### 异步执行模式（v18.11+）

前端可通过新的 `start_async` 端点启动项目，后台自动链式执行：

```javascript
// 启动异步项目
$.post(ajaxurl, {
    action: 'linked3_book_factory_start_async',
    nonce: linked3BookFactory.nonce,
    book_title: '我的新书',
    type: 'book',
    mode: 'ai'
}, function(response) {
    if (response.success) {
        var projectId = response.data.project_id;
        // 轮询进度
        pollProgress(projectId, response.data.progress_nonce);
    }
});

// 轮询进度
function pollProgress(projectId, nonce) {
    $.get(ajaxurl, {
        action: 'linked3_book_factory_progress',
        project_id: projectId,
        nonce: nonce
    }, function(response) {
        if (response.success) {
            var data = response.data;
            console.log('进度: ' + data.completed + '/' + data.total);
            if (data.status === 'running') {
                setTimeout(function() {
                    pollProgress(projectId, nonce);
                }, 3000);
            }
        }
    });
}
```

#### 自定义步骤注册（v18.11+）

第三方插件可注册自定义步骤：

```php
add_action('linked3_book_register_step', function($registry_class) {
    // 注册自定义步骤
    $registry_class::register(new My_Custom_Step());
});

class My_Custom_Step implements Linked3_Book_Step_Interface {
    public function get_step_id() {
        return 'my_custom_step';
    }
    public function get_label() {
        return '自定义步骤';
    }
    public function execute($state, $factory) {
        // 执行逻辑
        return array('done' => true);
    }
    public function get_next_step($state) {
        return 'step5_complete';
    }
}
```

#### 使用 Pipeline_Orchestrator（v19.0+）

新代码推荐使用 `Pipeline_Orchestrator` 替代 `Book_Factory`：

```php
// 旧代码 (v18.x)
$result = Linked3_Book_Factory::create_book($args);
$result = Linked3_Book_Factory::run_step($project_id);

// 新代码 (v19.0) — 支持依赖注入与单元测试
$orchestrator = Linked3_Book_Factory::orchestrator();
// 或自定义依赖:
// $orchestrator = new Linked3_Book_Pipeline_Orchestrator(
//     new My_Custom_AI_Caller(),
//     new My_Custom_Prompt_Provider(),
//     new My_Custom_Cost_Tracker()
// );

$result = $orchestrator->create_book($args);
$result = $orchestrator->run_step($project_id);
```

#### 使用 MetaMother 元母体（v19.1+）

v19.1 嵌入了 `meta的meta·真理探索系统元母体`，支持9大探索原型与4阶元流程：

```php
// 第一阶: 探索方式分类 — 根据意图推荐最佳原型
$mother = new Linked3_Book_MetaMother();
$result = $mother->classify_exploration('我想通过实验验证某个假设');
// 返回: array('recommended' => 'experimental', 'alternatives' => [...], 'reasoning' => '...')

// 第二阶: 系统原型生成 — 获取原型完整配置
$prototype = Linked3_Book_Exploration_Prototypes::get('meditative');
// 返回: array('key' => 'meditative', 'name' => '冥想式探索', 'process' => [...], ...)

// 第三阶: 元规律提炼 — 评估探索结果的5大元规律合规性
$assessment = $mother->extract_meta_laws($exploration_result_text);
// 返回: array('laws' => [...], 'overall_score' => 85, 'suggestions' => '...')

// 第四阶: 新系统创造 — 按六步创造法生成新探索系统
$new_system = $mother->create_new_system('量子意识探索系统', '量子力学+意识研究');
// 返回: array('system_name' => '...', 'base_prototype' => '...', 'process_steps' => [...], ...)
```

前端 AJAX 调用示例：

```javascript
// 探索方式分类
$.post(ajaxurl, {
    action: 'linked3_book_meta_classify',
    nonce: linked3BookFactory.nonce,
    intent: '我想探索意识的本质'
}, function(response) {
    if (response.success) {
        console.log('推荐原型:', response.data.recommended);
        console.log('备选原型:', response.data.alternatives);
    }
});

// 获取所有探索原型
$.post(ajaxurl, {
    action: 'linked3_book_meta_prototypes',
    nonce: linked3BookFactory.nonce
}, function(response) {
    if (response.success) {
        var prototypes = response.data.prototypes;
        // 渲染原型选择器...
    }
});
```

### 回滚方案

如果升级后出现问题，可回滚到 v19.0 或 v18.10.3：

1. 停用 v19.0 插件
2. 删除 `wp-content/plugins/linked3-ai/` 目录
3. 上传 v18.10.3 的 `linked3-ai` 目录
4. 启用插件

状态文件向后兼容，回滚不会丢失项目数据。

### 常见问题

**Q: 升级后异步执行不工作？**
A: 检查 WordPress cron 是否正常。v19.0 依赖 wp_cron 进行后台调度。如果 cron 被禁用，可在 `wp-config.php` 中移除 `define('DISABLE_WP_CRON', true);`，或使用 WP-CLI 手动触发：`wp cron event run linked3_book_async_run_step`

**Q: 升级后旧项目状态丢失？**
A: 不会丢失。v19.0 引入了状态 schema 迁移机制，旧项目状态会自动升级。如果项目确实不可见，检查 `wp-content/uploads/linked3-books/` 目录下的 JSON 文件是否完好。

**Q: 第三方插件扩展的步骤不生效？**
A: 确保第三方插件在 `linked3_book_register_step` 钩子中注册步骤，且步骤 ID 不与内置步骤冲突。

### 技术支持

如遇升级问题，请提供以下信息以便排查：
1. WordPress 版本
2. PHP 版本
3. 错误日志（`wp-content/debug.log`）
4. 升级前后的插件版本号
