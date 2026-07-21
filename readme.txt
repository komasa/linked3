=== Linked3 AI ===
Contributors: linked3
Tags: ai, content-generation, book-writing, openai, content-writer
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 27.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI驱动的WordPress内容生成与写书式写作系统，支持六步流水线、断点续作、成本追踪与多模型适配。

== Description ==

Linked3 AI 是一个功能强大的AI内容生成插件，集成了写书式写作系统（BookFactory），支持从大纲到完整书稿的自动化生成。

= 核心功能 =

* **写书式写作系统** — 六步流水线（演示→探索→大纲→扩写→拼接→审阅），支持断点续作
* **多类型多模式** — 支持book/tutorial/case_study等多种类型，ai/human/ghost多种模式
* **提示词管理** — 三级回退机制（DB→JSON→硬编码），支持后台编辑与版本管理
* **成本追踪** — 实时统计AI调用成本与Token用量
* **异步执行** — 后台自动链式执行，前端只需轮询进度
* **步骤可扩展** — 通过接口与注册表，第三方插件可注册自定义步骤

= v19.0 新特性 =

* 上帝类拆分：Book_Factory 1420行拆分为7个职责单一类
* 接口契约体系：AI_Caller/State_Repository/Prompt_Provider/Cost_Tracker 四大接口
* 依赖注入：所有新类通过构造函数注入依赖，支持单元测试
* 配置驱动化：步骤定义外部化为 steps.yaml

== Installation ==

1. 上传 `linked3-ai` 目录到 `/wp-content/plugins/` 目录
2. 在WordPress后台"插件"菜单中启用插件
3. 在设置页面配置AI API密钥
4. 开始使用写书工厂创建内容

== Frequently Asked Questions ==

= 升级到v19.0会丢失现有项目吗？ =

不会。v19.0引入了状态schema迁移机制，旧项目状态会自动升级。

= 异步执行不工作怎么办？ =

检查WordPress cron是否正常。如果cron被禁用，请移除wp-config.php中的DISABLE_WP_CRON定义，或使用WP-CLI手动触发。

= 如何注册自定义步骤？ =

使用 `linked3_book_register_step` 钩子注册实现 `Linked3_Book_Step_Interface` 的步骤类。

== Changelog ==

= 27.3.3 =
* 严重修复: 17处文件作用域 __CLASS__ (OS模块16处 + Seed_Unified 1处) 导致 "class __CLASS__ not found" Fatal Error
* 根因: 之前的扫描器花括号计数器被 if 块干扰, 漏检类体外的 __CLASS__
* 修复: 用字符串感知的花括号追踪重新扫描, 修复全部17处

= 27.3.2 =
* 严重修复: Linked3_Meta_Lever_Data_Driven 缺少3个接口方法 (tags/applicable_tasks/trace_field) 导致 Fatal Error
* 修复: JSON 数据文件补全 tags/applicable_tasks/trace_field 字段
* 增强: 扫描器新增 UnimplementedMethod 检测 — 类声明时即批量显示未实现方法

= 27.3.1 =
* 修复: 扫描器新增 FileScopeMagicConst 检测 — 文件作用域 __CLASS__ 导致 Fatal Error
* 修复: 扫描器新增 BareClassRef 检测 — 文件作用域裸类名 add_action 导致 autoloader 失败
* 修复: 6处文件作用域 __CLASS__ 改用 FQCN 字符串
* 修复: zip 格式改为手工构造 (version=20, method=deflate, no flags, no extra, no data-desc)

= 27.3.0 =
* 架构重构: MetaLever 62→17 PHP文件 (-73%) — 45个独立杠杆类合并为1个数据驱动类 + JSON
* 架构重构: God Class拆分 — Dashboard Legacy 1953→1595行, AIConfig方法迁移到Action类
* 严重修复: 6处文件作用域 __CLASS__ 导致 "class __CLASS__ not found" Fatal Error

= 27.2.1 =
* 严重修复: 6处文件作用域 __CLASS__ 在类体外部不解析导致 "class __CLASS__ not found" Fatal Error
* 修复: 改用 FQCN 字符串代替 __CLASS__ 在文件作用域的 add_action 调用

= 27.2.0 =
* 新功能: 统一创作中心 — 文章/漫画/图示统一入口 (ContentPipeline_Interface)
* 架构: 删除 V18 模块 (35文件), OS 模块为唯一实现
* 架构: 697处命名空间引用修复 + 扫描器bug修复
* 安全: cURL SSRF修复 + wp_unslash + error_reporting restore

= 27.1.3 =
* 严重修复: 63 处 add_action/add_filter 使用裸类名字符串导致 autoloader 无法加载 — 改用 __CLASS__
* 严重修复: 579 处 class_exists/method_exists 使用裸类名导致永远返回 false — 改用 FQCN (\NS\Class)
* 严重修复: 55 处跨命名空间静态调用/实例化未加 \ 前缀 — 补全 FQCN
* 根因: 命名空间内 add_action('Linked3_Foo', ...) 在全局执行时 autoloader 只处理 Linked3\* 前缀
* 根因: class_exists('Linked3_Foo') 检查全局类, 实际类在 NSLinked3_Foo — 永远 false

= 27.1.2 =
* 修复: zip 包含非 ASCII 文件名 (中文 JSON seed) 导致 PclZip BAD_FORMAT — 重命名为拼音 ASCII
* 修复: zip 包含 Unix extra fields 导致 PclZip 解析失败 — 使用 -X 标志剥离
* 修复: 扫描器 resolve_fqcn() 先 ltrim 后检查 \ 前缀的逻辑错误 (27.1.1 已修, 此版本确认)

= 27.1.1 =
* 安全审计: 修复扫描器 resolve_fqcn() bug — ltrim 在检查前剥掉 \ 前缀导致全局类误判
* 安全修复: 21 处闭包 use 被扫描器误判为 trait 引用 — 改用静态属性/箭头函数/命名方法
* 安全修复: 5 处 extends 内建类 (Exception/WP_Widget) 缺少 \ 前缀导致命名空间解析错误
* 安全修复: 2 处 instanceof Exception/Throwable 无 \ 前缀 — 异常类型推断静默失效
* 安全修复: keyword-manager.php CURLOPT_SSL_VERIFYPEER=false SSRF 漏洞 → wp_remote_get
* 安全修复: 16 处 $_POST 未 wp_unslash — WordPress 转义规范违规
* 安全修复: 5 处 wp_verify_nonce 参数未 wp_unslash
* 安全修复: 6 处 error_reporting 运行时全局覆盖 — 改为 save/finally restore
* 架构重构: God Class dashboard-ajax-registrar-legacy.php 5384→1953 行 (-63%)
  - 提取 Linked3_Genesis_Processor (2563 行, 35 方法) 为独立类
  - 提取 Linked3_Genesis_V9_Processor (840 行, 3 方法) 为独立类
  - 3 组 Action 类完整迁移 (Template/Queue/Keyword)
  - 7 组 Action 类转发壳建立, 调用链完整
* 版本号统一: linked3.php / readme.txt / composer.json 全部对齐 27.1.1

= 20.4 =
* 重大修复: COS 认知操作系统从"模拟演化"升级为"真实 AI 演化"
  - EX 部: 用真实 AI 调用 (Linked3_AI_Dispatcher) 替代 rand() 占位评分, 方案携带真实 approach 文本和执行步骤
  - A  部: 从 MVP 提取真实固化规则 (rules), 不再是空数组或占位文本
  - Skill 库: system_prompt 注入完整方案 + 执行步骤 + 固化规则, 不再是空壳
  - 杠杆链: 从"只输出 trace 字段"升级为"真实调用 AI 做认知审查", 前一杠杆输出作为后一杠杆输入, 形成链式增强
  - 新增 chain_levers AJAX 端点, 支持一次调用多个杠杆并返回增强后的 system_prompt
  - AI 不可用时降级为结构化模板, 保证流水线不中断
  - 新增 generations_summary 代际摘要, Skill 存储完整演化谱系
* 修复: Skill 应用后 system_prompt 为空 — 根因是 EX 部用 rand() 生成占位 approach
* 修复: 杠杆链只显示 trace 字段无实际分析 — 根因是 run_lever 未调用 AI
* 修复: G2/G3 变异未使用 G1 结晶基线 — 传入 baseline 参数

= 20.3 =
* 重大重构: COS UI 从"技术展示"改为"引导式工作流 SOP"
  - 新增 5 步引导: ① 提出问题 → ② 启动演化 → ③ 查看结晶 Skill → ④ 应用 Skill → ⑤ 杠杆链审查
  - 每个区块都有"这是什么"+"怎么用"+"下一步"说明
  - 新增"应用 Skill"功能 — 演化结晶的 Skill 转化为 system_prompt, 可复制到生成器使用
  - 新增 SOP 引导条 (顶部步骤导航)
* 修复: 演化归档显示"暂无记录" — AJAX 返回键名 archive→recent 不匹配
* 修复: Skill 名字乱码 — 改用领域+短哈希命名 (如 ecommerce_skill_a3f2b1)
* 新增: 2 个 AJAX 端点 (apply_skill / get_sop)
* 新增: Skill 库 increment_usage() 方法
* 新增: 杠杆链说明区 — 解释什么是杠杆链、何时使用、调用后做什么

= 20.2 =
* 修复: 杠杆链 ID 不匹配 — UI 发送 logic/critique 等短 ID, 后端实际 ID 是 meta_logic/meta_critique 等
* 修复: Skill 不持久化 — 中文问题用 sanitize_title() 返回空字符串, 改用 md5 哈希
* 修复: AJAX context 解码 — 前端发送 JSON.stringify, 后端改用 json_decode
* 新增: 演化成功后自动刷新仪表盘统计

= 20.1 =
* 审计完善: 全系统 5 层生产就绪审计通过 (436 文件 0 语法错误, 0 安全问题)
* 修复: COS Engine 中 sanitize_title() 调用增加 function_exists 防御
* 验证: COS 三代演化端到端测试通过 (G1→G2→G3 全部 pass, MVP 锁定)
* 验证: COS UI Tab 渲染测试通过 (25656 字节, 6 个关键区块全部存在)
* 验证: 6 个 COS AJAX 端点全部注册成功
* 验证: MetaLever 桥接测试通过 (12 个杠杆全部可调用)

= 20.0 =
* 新增: 认知操作系统 (Cognitive Operating System / COS) — 四层架构子系统
  - 核心引擎层: 双公理系统 (信息熵减 + 系统降维) + 五部门引擎 (FP/EX/C/O/A) + SLA 契约 + 三代演化 (G1→G2→G3)
  - 存储层: Skill 库 (固化的认知能力, 越用越强) + 演化归档 (每代快照与回溯)
  - 接口层: AJAX 端点 (演化/仪表盘/Skill/归档/杠杆链)
* 新增: 🧠 认知OS 顶级 Tab — 全新 UI 仪表盘, 可视化双公理/五部门/三代演化/Skill 库/演化归档/杠杆链
* 变异-绞杀流程: COS 引擎作为新子系统嵌入, 逐步吸收 MetaLever 的决策路径 (向后兼容)
* 新增 9 个文件: CognitiveOS/Core (4) + Storage (2) + Engine + Ajax + UI Tab

= 19.56 =
* 修复致命错误: 移除9个文件中的非法反斜杠转义 \$result (Parse error: unexpected token "\")
* 修复Diagram_Structure_Registry中重复定义的suggest_text()和get_zones()方法
* 受影响文件: AI_Form_Manager, Generate_Excerpt/Meta/Tags_Action, WC_AI_Generator, Rest_Controller, Keyword_Manager, AI_Dispatcher, Diagram_Structure_Registry

= 19.55 =
* 修复致命错误: 移除6个文件中的PHP 8.0+ match()表达式, 改为switch语句 (插件声明Requires PHP 7.4)
* 修复Dependency_Loader中未声明的$loaded_files静态属性
* 受影响文件: Platform_Adapter, Prompt_Assembler, Dashboard_Ajax_Registrar, Diagram_Production, Genesis_Engine_V7, Genesis_Seed_CPT

= 19.1.0 =
* 嵌入meta的meta元母体: 9大探索原型(写书/实验/观察/推演/冥想/对话/实践/艺术/计算/综合)
* 4阶元流程: 探索方式分类→系统原型生成→元规律提炼→新系统创造
* 5大元规律: 可证伪/可传递/可具现/可进化/可守护
* 6个MetaMother AJAX端点: classify/prototype/extract/create/info/prototypes
* 16条/meta2指令系统

= 19.0.0 =
* 上帝类拆分：Book_Factory拆分为Pipeline_Orchestrator/Draft_Builder/Outline_Processor等7个类
* 接口契约体系：AI_Caller/State_Repository/Prompt_Provider/Cost_Tracker四大接口
* 依赖注入：所有新类通过构造函数注入依赖
* 配置驱动化：步骤定义外部化为steps.yaml

= 18.11.0 =
* 安全加固：project_id路径白名单校验、原子写入、错误信息脱敏
* 异步化改造：后台自动链式执行，解决PHP超时问题
* 步骤接口化：Linked3_Book_Step_Interface + 注册表，替代switch-case硬编码
* 状态持久化加固：schema版本号、原子写入、迁移机制

= 18.10.3 =
* 修复132节爆炸问题
* 保存方式优化
* 输出节奏"第第"重复修复
* 性能优化

== Upgrade Notice ==

= 19.0.0 =
重大架构升级：上帝类拆分+接口契约+依赖注入+配置驱动。完全向后兼容，现有项目自动迁移。详见UPGRADE.md。
