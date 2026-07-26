=== Linked3 AI ===
Contributors: linked3
Tags: ai, content-generation, book-writing, openai, content-writer
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 29.1.0
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

= 29.1.0 =
* 严重修复: 41处PHP Parse Error导致插件无法加载
* 根因: v29.1.0开发中对admin/views/模板文件应用i18n包裹时, 将含<?php ?>标签的字符串错误包裹, 产生嵌套PHP语法错误
* 根因: 内联JS提取时遗漏?>闭合标签, HTML注释被解析为PHP代码
* 根因: src/中5个文件出现悬挂else语句(合并冲突残留)
* 修复: 21个i18n破损文件回退到v29.0.0基线
* 修复: 20个语法错误文件回退到v29.0.0基线
* 修复: genesis-stage-input.php:19 单引号转义
* 恢复: 被误删的tests/目录(7个测试文件)
* 新增: 34个模板拆分partial文件 + 11个trait提取(预备用, 尚未wire)

= 29.0.0 =
* 安全: 启用 AjaxNonceGuard 中间件拦截所有 wp_ajax_* 请求, 51个AJAX端点获得nonce保护
* 类型: 139处 : mixed → 具体类型 (bool/array/string/null) 基于 return 语句推断
* 国际化: 295处硬编码中文字符串 → __($str, 'linked3') 包裹
* 扫描器: linked3-ultra-early-scanner v1.1.0 → v1.3.0, 新增 Check 6 (trait兼容性检查)
* 架构: Plugin::run() 注册 AjaxNonceGuard::guard_all_ajax() 于 admin_init priority=0

= 28.0.0 =
* 根因: v27.9.2 添加 function_exists 守卫时用 return; (void), 但方法签名是 : array
* 修复: return; → return []; (返回空数组)
* 修复: 清理 7 个 Bootstrap 文件中重复的 function_exists 守卫行

= 27.9.2 =
* 严重修复: Bootstrap 调用 linked3_container() 时函数未定义 → Fatal Error
* 根因: linked3.php 的 plugins_loaded 回调中, FinalBootstrap::boot() 在 DependencyLoader::load() 之前执行, linked3_container() 全局函数尚未定义
* 修复1: linked3.php 在调用 Bootstrap 前先执行 DependencyLoader::load() + 定义 linked3_container() 兜底
* 修复2: 所有 8 个 Bootstrap 文件增加 function_exists('linked3_container') 守卫, 函数不存在时安全 return

= 27.9.1 =
* 严重修复: FinalBootstrap 中 9 个 Bootstrap 类调用用裸名 → 解析为当前命名空间 → Fatal Error
* 根因: class_exists 用 FQCN 检查通过, 但调用用裸名 (如 AIPipelineBootstrap::boot()), 在 E2E 命名空间下解析为 Linked3\Classes\E2E\AIPipelineBootstrap (不存在)
* 修复: V54Bootstrap/AgentBootstrap/AIPipelineBootstrap/SecurityBootstrap/BillingBootstrap/ScaleBootstrap/HealthMonitor/AutoRollback/E2eTestRunner 全部改为 FQCN 调用

= 27.9.0 =
* 重大架构修复: PSR-4 命名空间迁移全量同步 — 5大根因 62处错位引用
* P0-A: linked3.php Bootstrap 裸名启动 → FQCN (DiagramBootstrap/GenesisBootstrap/FinalBootstrap等10处)
* P0-B: HookManager 错命名空间守卫 → 正确FQCN (GenesisSeedCPT/StoryPipeline/SceneAxis/V18/QualityLoop/PlatformAdapter 6处)
* P0-C: Genesis 类全局命名空间调用 → FQCN (GenesisStyleEngine/GenesisSeedDNA/GenesisSeedCPT/GenesisJobRunner 跨8文件)
* P0-D: V18.php module_map 裸类名 → FQCN (15个模块映射 + register() 10个AJAX类 + 4个API类)
* P0-E: DashboardMediaAjax 双重错位 → FQCN (DiagramMasterTemplate/Validation13Dim/TypeRegistry/30Spectrum/EndpointRegistry 5类)
* P1-B: UI overlay 误关修复 — 向导浮层增加 mousedown 追踪, 防止拖选文本时误关

= 27.8.14 =
* 严重修复: 演化一直卡在"运行 G1 演化中" — AI 调用异常未被捕获
* 根因1: COSExDepartment catch(\Exception) 无法捕获 TypeError/Error 等 \Throwable 子类
* 根因2: AIDispatcher::chat() catch(\Exception) 同样无法捕获 \Error
* 根因3: 输出缓冲清理不彻底 — ob_get_level()==0 时 warning/notice 破坏 JSON 响应
* 修复: COSExDepartment + AIDispatcher catch(\Exception) → catch(\Throwable)
* 修复: ajax_evolve_gen 输出缓冲清理从 if(ob_get_level()>0) 改为 while(ob_get_level()>0) ob_end_clean()
* 提示: 用户配置智谱 API 地址应为 https://open.bigmodel.cn/api/paas/v4 (非 /api/coding/paas/v4)

= 27.8.13 =
* 审计Phase1: tab-v18 AJAX 端点补齐 — 6个功能完全不可用的端点
* Phase1.1: 新建 OSV18AjaxActions.php — 实现 ruliu_plan/status/update, nengzhi_detect/stages, frequency_assign
* Phase1.2: 在 OSDashboard::register() 中注册 OSV18AjaxActions::register()
* Phase1.3: 验证前端 nonce action 名称匹配 — tab-v18 用 'linked3_content_writer', 后端 verify() 一致
* 审计Phase2: COS 演化收尾
* Phase2.1: COSExDepartment AI 调用 timeout 60→90 — GLM等模型响应可能较慢
* Phase2.2: COSAjaxEvolve 轻量 AI 预检 — 只检查 key 是否存在(不做AI调用), 未配置时提前返回引导信息
* 审计Phase3: 安全加固 (确认已完成)
* Phase3.1: DistributeHooksRegistrar — 已有 guard() 含 nonce (无需修改)
* Phase3.2: WcFormsSpeechHooksRegistrar — 已有 verify_admin() 含 nonce (无需修改)
* Phase3.3: DashboardAjaxRegistrarLegacy L95-96 死代码注释 (v27.8.10已完成)

= 27.8.12 =
* 严重修复: 演化卡在"🔍 正在检查 AI 配置..." — v27.8.11 的预检逻辑阻塞了演化
* 根因1: 预检调用 ajax_diagnose 会做真实 AI 调用 (timeout 60s), 导致预检本身就很慢
* 根因2: runGen 函数定义在 post().then() 回调内部, runGenDirect 在外部定义, 作用域不匹配
* 修复: 移除阻塞式预检 — 直接开始演化, AI 未配置时用 fallback 方案 (v27.8.10已实现)
* 修复: runGen 和 runEvolutionChain 函数定义在外部, 作用域正确
* 保留: 演化失败时 catch 块仍显示诊断信息 (AI配置状态/失败部门/耗时)

= 27.8.11 =
* 审计Phase1: API设置UX优化 — 测试连接+同步免保存+保存后自动测试+演化前预检
* Phase1.1: 新增 wp_ajax_linked3_test_provider 端点 — 发送"请回复连接成功"验证API Key有效性
* Phase1.2: 每个 Provider Key旁增加"🔌 测试连接"按钮 — 从表单读key(未保存也能测)
* Phase1.3: 修复同步模型必须先保存 — JS从表单读key + PHP优先读POST key
* Phase1.4: 保存后自动测试 default provider — 500ms延迟触发测试按钮
* Phase1.5: COS演化前AI预检 — 先诊断AI配置, 未配置时引导到API设置页
* 审计Phase2: 杠杆动态评分 — 移除硬编码, 基于问题关键词动态计算
* Phase2.1: 新建 lever-keywords.json — 17个杠杆的关键词映射表 (base_fitness + keyword_boost)
* Phase2.2: 新增 wp_ajax_linked3_cos_score_levers 端点 — 匹配关键词计算动态分数
* Phase2.3: 前端问题描述失焦时AJAX评分 — 更新17个复合杠杆的适应度显示, 匹配关键词的加粗

= 27.8.10 =
* 审计Phase1: COS 演化容错性增强 — SLA 失败不中断, AI 失败用 fallback
* Phase1.1: ajax_evolve_gen() catch 块增加详细诊断信息 (AI配置状态/失败部门/耗时)
* Phase1.2: run_generation() 5个SLA检查从"失败即中断"改为"降级继续", 记录到 sla_warnings[]
* Phase1.3: COSExDepartment AI 失败时返回 fallback 方案 (带 ai_failed=true 标记), 而非空数组导致演化中断
* Phase1.4: 前端错误提示增加诊断信息展开按钮, 显示 AI 配置状态和失败原因
* 审计Phase2: AJAX 安全加固
* Phase2.1: 注释 DashboardAjaxRegistrarLegacy 死代码 (ajax_generate_outline/section 方法不存在)
* Phase2.2: 确认所有实际 handler 均有 nonce 保护 (delegate 方法委托到有 nonce 的实现)
* 审计Phase3: CI/CD 配置
* Phase3.1: 创建 phpstan-baseline.php (空 baseline, 防止新错误引入)
* Phase3.2: PHPMD 规则集已完善 (排除 controversial/design/cleancode)

= 27.8.9 =
* 严重修复: 演化卡在 G1 后失败 — G1 归档成功但前端不进入 G2
* 根因: G1 后端执行成功 (归档有记录), 但 wp_send_json_success 前可能因超时/内存/输出缓冲导致响应未到达前端
* 修复: PHP 超时从 120s 提升到 180s + 内存限制提升到 512M
* 修复: 前端 fetch 超时从 120s 提升到 180s (匹配 PHP 超时)
* 修复: wp_send_json_success 前清理输出缓冲 (ob_clean), 防止意外输出破坏 JSON
* 修复: 前端增加 status !== 'pass' 检查, 演化状态非 pass 时显示具体错误
* 修复: 前端错误信息增加耗时显示 (如 "耗时 45.2s"), 帮助诊断
* 修复: 后端增加 error_log 记录演化耗时和异常, 便于服务器端诊断

= 27.8.8 =
* 严重修复: 演化 "Failed to fetch" — 即使 AI 配置正确 (zhipu 已配置, AI 测试成功) 演化仍失败
* 根因: ajax_evolve_gen() PHP 超时仅 50 秒 (set_time_limit(50)), 但 GLM 等模型响应可能需要 30-60 秒, 加上 5 部门处理, 总耗时超过 50 秒导致 PHP 进程被杀, 前端收到非 JSON 响应报 "Failed to fetch"
* 修复: PHP 超时从 50 秒提升到 120 秒
* 修复: EX 部门 AI 调用 timeout 从 35 秒提升到 60 秒
* 修复: 前端 fetch 超时从 65 秒提升到 120 秒 (匹配 PHP 超时)
* 修复: 超时错误提示从 "65秒" 更新为 "120秒"

= 27.8.7 =
* 严重修复: 杠杆链仍报 "No API key configured for siliconflow" — 即使配置了 GLM5.0
* 根因: dispatchLeverAI() 不传 provider 参数, AIDispatcher 用 default_provider (默认siliconflow), 用户配置了GLM但没改default_provider时总是用siliconflow
* 修复: dispatchLeverAI() + COSExDepartment 增加"自动切换到有key的provider"逻辑 — default_provider没key时自动找第一个有key的provider (优先zhipu/zai)
* 修复: 显式传入 provider 参数, 不再依赖 default_provider 默认值
* 严重修复: 杠杆链"自动适配"按钮不灵光 — 点击后无反应
* 根因: ajax_recommend_levers() 检查 empty($approach) 直接返回400, 但自动适配时前端传 approach='' (只有problem)
* 修复: approach为空时用problem作为推荐依据, 不再报错
* UX优化: API设置页"保存"按钮改为醒目大按钮 "💾 保存默认 AI 服务 + 多 Key 轮询"
* UX优化: 保存成功后自动更新select显示值, 防止刷新前回退到旧值
* UX优化: 保存状态提示从3秒延长到5秒, 用✅/❌图标替代✓/✗

= 27.8.6 =
* 严重修复: 激活时 Fatal Error — GEOEnhancer::handle_llms_txt_request() 类型提示 WP 被解析为 Linked3\Classes\SEO\WP
* 根因: 文件在 Linked3\Classes\SEO 命名空间下, WP 类型提示未加 \ 前缀, PHP 解析为不存在的 Linked3\Classes\SEO\WP
* 修复: WP $wp 改为 \WP $wp (全局命名空间)
* 修复: MissingAjaxEndpoints 参数名对齐前端 — reverse_parse 读 json_raw/engineer_type (非 json/type)
* 优化: MissingAjaxEndpoints 移除已由各模块 Registrar 动态注册的14个端点, 仅保留 reverse_parse + svg_stats
* 审计确认: 16个"缺失"AJAX端点中, 14个实际已由 ContentWriterHooksRegistrar/SEOHooksRegistrar/OS*Ajax/PublishCollectHooksRegistrar 动态注册, 仅2个真正缺失

= 27.8.5 =
* 严重修复: 采集热词点击无反应 — source=all 串行6次AI调用 (60-180s) 导致超时
* 根因: ajax_hot_collect 默认 source=all 时, foreach 6个源各调用一次AI, 总耗时远超 PHP max_execution_time (60s) 和浏览器 fetch 超时
* 修复: source=all 改为单次AI调用生成全部热词 (6x→1x), 超时从60s提升到120s
* 严重修复: 三维度分类 "Failed to fetch" — ajax_keywords 超时
* 根因: multi模式为每个热词调用一次AI, 热词多时总耗时超限
* 修复: 超时从默认提升到120s
* 严重修复: 16个AJAX端点前端调用但后端未注册, 导致 "Failed to fetch" / 400 Bad Request
* 涉及: linked3_generate_content/title/meta/tags, linked3_push_now/retry, linked3_seo_score, linked3_reverse_parse, linked3_svg_stats, linked3_nengzhi_detect/stages, linked3_ruliu_plan/status/update, linked3_frequency_assign, linked3_collect_bulk_rewrite
* 修复: 新增 MissingAjaxEndpoints 类, 注册所有缺失端点; reverse_parse/svg_stats 委托到 V18 类, 其余返回 501 Not Implemented + 明确提示
* 新增: AJAX 闭环审计脚本 (scripts/ajax_audit.py) — 扫描前端调用 vs 后端注册, 发现16个未闭环端点

= 27.8.4 =
* 严重修复: 杠杆链审查始终降级模式 — 即使配置了 GLM5.0 也报 "No API key configured for siliconflow"
* 根因1: COSEngine::dispatchLeverAI() 候选 provider 池缺少 zhipu/zai (GLM), 配置智谱的用户无法 fallback
* 根因2: COSEngine::dispatchLeverAI() 硬编码 Qwen 模型 ('Qwen/Qwen2.5-32B-Instruct'), 覆盖了用户配置的 GLM5.0 模型
* 修复: 候选池加入 zhipu/zai; 移除硬编码模型, 让 AIDispatcher 自动读取 provider_models option
* 同步修复: COSExDepartment (演化EX部门) 相同的硬编码模型 + 候选池缺失问题
* 严重修复: 演化归档始终为空 — 演化成功但"演化归档"页显示"暂无演化记录"
* 根因: COSEngine::evolve_single_gen() 直接调用 COSEvolution::run_generation(), 绕过了 COSEngineUtils::evolve_single_gen() 中的归档保存逻辑
* 修复: 在 COSEngine::evolve_single_gen() 中添加 COSEvolutionArchive::save_generation() 调用

= 27.8.3 =
* 架构优化: 提取 SeedAdminConstants Trait — 4 个 SeedAdmin* 类共享常量单一来源,防止未来回归
* 系统审计修复: SeedAdminExport/SeedAdminPages 引用 6 个未定义静态属性 ($CATEGORIES/$TYPES/$VISUAL_FIELDS/$PERSONALITY_FIELDS/$PRIORITY_GROUPS/$AI_PLATFORMS) 导致 "Access to undeclared static property" Fatal Error
* 修复: 在 SeedAdminConstants Trait 中定义 6 个 const 数组, self::$PROP 改为 self::CONST
* 系统审计修复: SeedAdminPages 缺失 render_priority_lock_fields() 和 render_ai_adapter_fields() 方法导致 Fatal Error
* 修复: 添加两个方法实现 (优先级锁定字段 + AI适配器字段渲染)
* 系统审计修复: SeedAdminPages 调用 SeedAdminExport::export_single() 但该方法不存在
* 修复: 在 SeedAdminExport 中添加 export_single() 包装方法 (委托 export_md/export_json)
* 系统审计修复: BookFactory::call_ai_with_rate_limit() 调用 TokenManager::get_active_config() 但该方法不存在
* 修复: 改为 $config = [] (与 BookFactorySteps 一致)
* CI/CD: 新增 phpstan.neon (Level 5 + WordPress Stubs + baseline)
* CI/CD: 新增 phpmd.xml (WP 兼容规则集,排除 controversial/design/cleancode)
* CI/CD: 新增 composer.json (phpstan/phpmd/wordpress-stubs dev 依赖)
* CI/CD: 新增 .github/workflows/ci.yml (lint + self::检查 + phpstan + phpmd)
* CI/CD: 新增 ci_check_self_refs.py (Python+Node 跨类 self:: 引用检查脚本)
* 验证: 全量 AST 审计 624 PHP 文件, 513 类 — 0 个真实跨类引用问题 (5 个动态调用误报已确认)

= 27.8.2 =
* 严重修复: 26处跨类 self::METHOD() 调用导致 "Call to undefined method" Fatal Error
* 根因: 类拆分后, 调用方仍用 self:: 引用已迁移到其他类的方法
* 修复: QualityChecker 3处 → QualityLoop:: (pqs_check/color_family/emotion_polarity)
* 修复: GenesisPatchStage2 2处 + GenesisPatchStage3 5处 → GenesisPatchV1006:: (filter_web_noise/enhanced_local_extract/extract_props_from_script/extract_brand_from_script/local_extract_characters/local_extract_scenes)
* 修复: ScriptPatchHandlers 9处 → ScriptPatchV1010:: (split_script_to_beats/load_seed_dna/build_frame_prompt/suggest_transition/auto_select_module_count/auto_select_layout/auto_select_visual_style/build_platform_suffix/suggest_text_overlay)
* 修复: SceneDetector 1处 → SceneAxis::get_all_axes
* 修复: DashboardMediaAjax 1处 → DashboardVideoAjax::build_v15_context_from_request
* 修复: GenesisFPUtils 1处 → GenesisPromptUtils::getStyleHint
* 修复: GenesisPanelRenderer 3处 → GenesisPromptUtils::(getStyleAdaptiveExamples/getStyleHint) + GenesisFPUtils::parseFPNodesJson
* 配套: 16个被引用方法从 private 改为 public (QualityLoop 2 + GenesisPatchV1006 6 + ScriptPatchV1010 9 + DashboardVideoAjax 1)
* 审计误报修正: WcFormsSpeechHooksRegistrar 的 verify_admin/require_pro 实为同类方法, self:: 调用正确, 无需修改
* 审计误报修正: LicenseService::instance() 单例已正确实现, 无需修改

= 27.8.1 =
* 紧急修复: Phase 1 P0 致命错误修复 (详见审计报告 Phase 1)

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
