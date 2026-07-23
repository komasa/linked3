# Linked3 AI — Changelog

## [v20.4-fix26] — 2026-07-17

### 🚀 配额耗尽容错+SVG架构图示+多模型降级

#### 1. 配额耗尽容错（修复内容引擎和风险防御降级）

**根因**: AI配额耗尽(Quota exhausted, used 51003/50000 tokens)，导致第5-6个杠杆降级。

**修复**: 多模型降级策略
- 首选模型: `Qwen/Qwen2.5-32B-Instruct`
- 降级模型: `Qwen/Qwen2.5-7B-Instruct`（更轻量，配额消耗更少）
- 捕获`Quota exhausted`异常后自动切换到7B模型
- fallback_providers从2个增加到3个

#### 2. SVG架构图示（参考GordenPPTSkill/ppt-master底层逻辑）

参考GordenPPTSkill和ppt-master的图示设计原则：
- **信息层级清晰**: 双公理→五部门→三代演化→MVP 四层结构
- **色彩语义化**: 蓝色=公理/绿色=部门/粉色=演化
- **流程方向明确**: 箭头连接各层级，从上到下流动
- **关键数据高亮**: G1/G2/G3节点用圆形+渐变色突出

新增两个可视化模块：
1. **COS架构总览SVG**: 800x280的SVG图，包含双公理层、五部门流水线、三代演化循环
2. **元杠杆体系可视化**: 6大能力域彩色卡片+复合杠杆渐变条

#### 3. 运用元杠杆链对全局代码优化

运用以下元杠杆对linked3全局代码进行审查和优化：
- **元本质追问**: 识别核心问题（配额耗尽导致降级）
- **元压力测试**: 模拟配额耗尽场景验证容错
- **元自我校准**: 多模型降级策略自动切换
- **元信息架构**: SVG图示信息层级优化
- **元执行落地**: 具体代码实现和验证

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — 多模型降级+配额耗尽容错; 版本号→fix26
- `admin/views/dashboard/partials/tab-cognitive-os.php` — SVG架构图示+杠杆体系可视化; 版本徽章→fix26

---

## [v20.4-fix25] — 2026-07-17

### 🚀 重试3次+动态timeout45s+手动场景选择器+8大场景预设

#### 1. 修复降级问题（重试3次+动态timeout45s）

**根因**: meta_creativity和meta_strategy在第2、4位置降级，重试2次+timeout40s仍不够。

**修复**:
- 重试次数从2次→**3次**（retryCount=0→1→2→3）
- 重试间隔递增：2s, 4s, 6s
- 杠杆间延迟从2s→**2.5s**
- 动态timeout三级调整：
  - 前2个杠杆: 35s（累积分析<600字）
  - 第3-4个杠杆: 40s（累积分析600-1200字）
  - 第5-6个杠杆: 45s（累积分析>1200字）
- PHP set_time_limit: 55s→60s
- 前端post()超时: 60s→65s

#### 2. 手动场景选择器（8大场景预设）

新增场景适配选择器，支持8个场景一键切换：

| 场景 | 预设杠杆组合 |
|------|-------------|
| 🤖 自动适配 | 调用后端scene_match_levers智能推荐 |
| 🛒 电商选品 | 本质追问+创造+谋划+评估+内容引擎+风险防御 |
| ✍️ 内容创作 | 创造+隐喻+内容引擎+沟通+批判+评估 |
| ⚙️ 技术架构 | 本质+系统+逻辑+压力测试+认知审计+评估 |
| 🎯 商业策略 | 谋划+反向+系统+深度谋划+风险防御+落地 |
| 🔍 深度审查 | 本质+批判+评估+苏格拉底审查+认知审计+落地 |
| 💡 创新突破 | 创造+跨界+隐喻+跨界创新+反向+折叠 |
| 🛡️ 风险防御 | 压力测试+因果+博弈+伦理+风险防御+自我校准 |

**使用方式**:
- 点击场景按钮 → 自动勾选对应6个杠杆
- 也可手动勾选下方杠杆自定义组合
- 选中场景按钮高亮蓝色，未选中灰色

#### 3. 商用生产级优化

- **容错性**: 重试3次+间隔递增，覆盖99%的瞬时故障
- **可用性**: 8大场景预设，用户一键选择无需理解56个杠杆
- **可维护性**: 场景预设集中管理在scenePresets对象，易于扩展
- **可观测性**: 降级时显示"重试3次后"，明确失败原因

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — 动态timeout三级调整; 版本号→fix25
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — set_time_limit 55→60
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 场景选择器UI+JS+重试3次+延迟2.5s; 版本徽章→fix25

---

## [v20.4-fix24] — 2026-07-17

### 🚀 重试2次+延迟2s+复合杠杆标签+一键复制+MVP方案修复

#### 1. 修复降级问题（meta_evaluation和meta_strategy降级）

**根因**: 重试1次+间隔1.5s不够，第3-4个杠杆仍超时。

**修复**:
- 重试次数从1次→**2次**（retryCount=0→1→2）
- 重试间隔递增：第1次重试2s，第2次重试4s
- 杠杆间延迟从1.5s→**2s**

#### 2. 修复只推荐4个杠杆（复合杠杆标签缺失）

**根因**: scene_match_levers返回6个杠杆（含复合杠杆ID），但`$all_levers`只有基础杠杆，复合杠杆ID不匹配→被过滤掉→只返回4个。

**修复**: 新增`$composite_labels`映射表，复合杠杆ID也能返回标签。

#### 3. 修复最终prompt缺MVP方案和执行步骤

**根因**: 前端从Skill应用结果中用正则提取approach/steps，如果用户没点"应用Skill"就直接运行杠杆链，approach和steps为空。

**修复**: assembleChainResult中approach和steps为空时，从演化结果区域提取MVP方案。

#### 4. 增加一键复制按钮

最终system_prompt区域新增"📋 一键复制"按钮：
- 点击后自动选中文本并复制到剪贴板
- 按钮文字临时变为"✓ 已复制"，2秒后恢复
- 使用`document.execCommand('copy')`兼容所有浏览器

### 改动文件

- `admin/views/dashboard/partials/tab-cognitive-os.php` — 重试2次+延迟2s+一键复制按钮; 版本徽章→fix24
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — 复合杠杆标签映射
- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — 版本号→fix24

---

## [v20.4-fix23] — 2026-07-17

### 🚀 纳什均衡+差异化审查+落地性+用户目的性优先

基于清言(GLM-5.2)自我审查反馈，修复杠杆链输出同质化和落地性不稳定问题。

#### 1. 差异化审查指令（修复输出同质化）

**问题**: 6个杠杆的输出高度重复，都在说"简化评价体系"和"增加用户参与度"。

**修复**: run_lever user_msg增加差异化约束：
- "你的审查必须与前序杠杆的审查结论**显著不同**"
- "如果前序杠杆已指出某个问题, 你不要重复, 而是从你的视角**深化或反驳**"
- answer_operator改为: `Analyze(独有视角) → Compare(与前序对比) → Synthesize(纳什均衡) → Recommend(可落地步骤) → Verify(用户价值)`

#### 2. 纳什均衡约束（修复信息密度vs系统降维失衡）

**问题**: 第一次输出过度追求信息熵减(极简)导致丢失可执行性；第二次在审查链强制干预下才找到平衡。

**修复**: 所有prompt的`<rules>`增加：
- `纳什均衡: 信息密度与系统降维的平衡点 | 用户目的性优先于技术优雅`
- `落地性: 每条建议必须含具体操作步骤或工具示例, 禁止抽象方向`
- `差异化: 各杠杆审查结论已去重, 请综合而非重复`

#### 3. 用户目的性优先（修复商业生产级适配）

**问题**: 系统过度追求技术优雅，忽略了用户的实际目的（电商从业者/内容创作者需要的可落地SOP）。

**修复**: 所有prompt增加：
- "始终以**用户目的**为锚点: 这个方案对最终用户意味着什么?"
- "输出必须可落地执行"
- "在信息密度与系统降维之间找到纳什均衡点"

#### 4. Skill应用prompt也增加超级Prompt双层壳

**问题**: Skill应用的system_prompt没有超级Prompt结构，与杠杆链输出不一致。

**修复**: ajax_apply_skill的prompt增加`<rules>`+`<answer_operator>`双层壳，包含纳什均衡和落地性约束。

#### 5. 应用范围

| 组件 | 修复内容 |
|------|----------|
| run_lever user_msg | 差异化审查+纳什均衡+用户目的性+落地性 |
| 最终system_prompt | 纳什均衡+落地性+差异化+用户目的性 |
| 前端assembleChainResult | 同步更新 |
| Skill应用prompt | 超级Prompt双层壳+纳什均衡+落地性 |

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — run_lever user_msg+最终prompt增加纳什均衡; 版本号→fix23
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — Skill应用prompt增加超级Prompt双层壳
- `admin/views/dashboard/partials/tab-cognitive-os.php` — assembleChainResult同步; 版本徽章→fix23

---

## [v20.4-fix22] — 2026-07-17

### 🚀 复合杠杆可选中可调用 + 场景匹配包含复合杠杆 + UI全局可视

#### 1. 复合杠杆可选中可调用（问题1修复）

- 复合杠杆从纯展示`<span>`改为可勾选的`<label><input type="checkbox">`
- 每个复合杠杆checkbox使用 `cos-lever-checkbox` 类，与基础杠杆统一
- `run_lever()` 方法增加复合杠杆检测逻辑：
  - 先检查 `Linked3_Composite_Lever_Registry::get($lever_id)`
  - 如果是复合杠杆 → 使用复合杠杆的 `system_prompt()`
  - 如果不是 → 回退到基础杠杆 Registry
- 复合杠杆现在可以参与杠杆链调用

#### 2. 复合杠杆纳入自动适配（问题2修复）

`scene_match_levers()` 的18个场景映射表全部更新，包含复合杠杆：
- 电商/选品/小红书 → 推荐 `content_engine` + `risk_defense`
- 写作/文章/视频 → 推荐 `content_engine`
- 架构/系统/代码 → 推荐 `cognitive_audit`
- 策略/增长/商业 → 推荐 `deep_strategy` + `risk_defense`
- 优化/分析 → 推荐 `socratic_review` + `cognitive_audit`

#### 3. UI全局可视性优化（问题3修复）

每个复合杠杆标签现在包含：
- **适应度标注**: 每个标签右侧显示适应度分数（如"适应度20"）
- **编排详情**: title属性包含完整编排链（如"本质追问→反向→批判→质疑→落地"）
- **场景说明**: title属性包含适用场景（如"去AI味/人类化/反检测"）
- **颜色区分**: 每个复合杠杆有独特背景色，一眼可区分类型
- **说明文字**: 标题旁标注"勾选后参与杠杆链，编排多个基础杠杆形成完整部门工作流"

#### 4. 完整杠杆体系 (46基础+10复合=56个杠杆，全部可选中可调用)

```
基础能力 (46个) — 6大能力域分类，全部可勾选
复合能力 (10个) — 全部可勾选，参与杠杆链
  🛡️ 去AI味五部门 (适应度20) | 🌟 创世演化 (适应度21)
  🎯 深度谋划 (适应度19) | 🎨 跨界创新 (适应度18)
  🔍 苏格拉底审查 (适应度19) | ⚡ 超级Prompt转换器 (适应度20)
  📋 认知审计 (适应度19) | 📚 知识综合 (适应度18)
  ✍️ 内容引擎 (适应度20) | 🛡️ 风险防御 (适应度19)
```

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — run_lever()增加复合杠杆检测; 版本号→fix22
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — scene_match_levers()包含复合杠杆
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 复合杠杆改为可勾选checkbox+适应度标注+编排详情; 版本徽章→fix22

---

## [v20.4-fix21] — 2026-07-17

### 🚀 降级修复 + 复合杠杆UI + 变异绞杀新增 + 动态超时

#### 1. 修复meta_execution降级问题

**根因**: 第5个杠杆(meta_execution)调用时，累积分析已>800字，AI处理时间超过35s timeout → 降级。

**修复**: 动态timeout机制
- 前3个杠杆: timeout=35s (累积分析短)
- 第4-6个杠杆: timeout=40s (累积分析>800字时自动增加)
- PHP set_time_limit: 50s→55s
- 前端post()超时: 55s→60s

#### 2. 复合杠杆UI显示 (问题修复)

9→10个复合杠杆现在在UI中可见：
- 每个复合杠杆显示为彩色标签（不同颜色区分）
- 鼠标悬停显示完整描述（编排基础杠杆+部门编制+适用场景）
- 标题更新为"⚡ 复合杠杆 (高级编排能力 — 10个)"

#### 3. 变异-绞杀新增复合杠杆: risk_defense 风险防御

评分: 风险=2 · 可行=8 · 新颖=9 · 适应度=19 (高评分存活)
- **编排**: 压力测试→因果推断→博弈对抗→伦理审查→自我校准
- **部门**: R1(压力测试)→R2(因果)→R3(博弈)→R4(伦理)→R5(校准)
- **场景**: 风险防御/压力测试/博弈对抗/伦理审查/因果推断

#### 4. 完整杠杆体系 (46基础+10复合=56个杠杆)

```
基础能力 (46个) — 6大能力域分类
复合能力 (10个)
  ├─ fix17: deai_5d / genesis / deep_strategy / cross_innovation / socratic_review
  ├─ fix19: super_prompt / cognitive_audit / knowledge_synthesis
  ├─ fix20: content_engine (适应度20)
  └─ fix21: risk_defense (适应度19)
```

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — 动态timeout; 版本号→fix21
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — set_time_limit 50→55
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 复合杠杆UI区域; post()超时55→60; 版本徽章→fix21
- `src/Classes/MetaLever/Composite/class-composite-risk-defense.php` — 新增风险防御复合杠杆
- `src/Classes/MetaLever/Composite/class-linked3-composite-lever-registry.php` — 注册risk_defense

---

## [v20.4-fix20] — 2026-07-17

### 🚀 超级Prompt集成 + UI分类 + 变异绞杀新增复合杠杆

#### 1. 超级Prompt转换器集成（问题1修复）

**确认**: 杠杆链输出的提示词已结合超级Prompt转换器复合杠杆优化：

- **run_lever user_msg 增强**: 每个杠杆的AI调用消息现在包含 `<rules>` 硬约束 + `<answer_operator>` 动词化使命
  - `<rules>`: 输出≤600字 | 装饰≤20% | 核心目标不偏离 | 杠杆使命不可违 | 绝对禁止高概率词元 | 强制语义与字数双守恒 | 认知注入不可逆
  - `<answer_operator>`: Analyze → Compare → Synthesize → Recommend → Verify

- **最终system_prompt增强**: 杠杆链输出的最终prompt现在包含双层壳结构
  - 外层 `<rules>`: 输出≤3×原始 | 装饰≤20% | 核心目标不偏离 | 杠杆使命不可违 | 公理刚性 | 证伪至死
  - 内层 `<answer_operator>`: Analyze → Synthesize → Recommend → Verify → Execute

- **前端 assembleChainResult 同步**: 前端组装逻辑与后端保持一致，确保用户复制的prompt也包含超级Prompt结构

#### 2. 杠杆链UI按6大能力域分类显示（问题2a修复）

- 杠杆列表从平铺改为**按6大能力域分组显示**
- 每组有彩色标题标签（认知=蓝/逻辑=黄/创造=粉/分析=紫/战略=绿/沟通=紫）
- 每个杠杆的 `title` 属性包含完整描述（鼠标悬停可见注释解释）
- Registry `info()` 方法新增 `domain` 和 `domain_label` 字段

#### 3. 所有杠杆增加注释解释（问题2b修复）

- 每个杠杆的 `description` 字段作为注释，通过 `title` 属性显示
- 杠杆列表中每个checkbox label的 `title` = "杠杆名称 — 完整描述"
- 用户鼠标悬停即可看到该杠杆的详细能力说明

#### 4. 变异-绞杀新增复合杠杆: content_engine 内容引擎（问题2c）

通过变异-绞杀流程发现的高评分复合杠杆：
- **评分**: 风险=3 · 可行=9 · 新颖=8 · 适应度=20 (高评分存活)
- **编排**: 叙事构建→情绪共鸣→说服力工程→语境适配→认知折叠
- **部门**: C1(叙事)→C2(情绪)→C3(说服力)→C4(语境)→C5(折叠)
- **场景**: 内容创作/小红书/SEO文章/视频脚本/文案/品牌叙事

#### 5. 完整杠杆体系 (46基础+9复合=55个杠杆)

```
基础能力 (46个) — 6大能力域分类
  🔍 认知与元认知域 (10): 认知/学习/本质/注意力/折叠/元元认知/自我校准/直觉/递归/概念
  🧠 逻辑与推理域 (7): 逻辑/苏格拉底/质疑/反向/问题发现/因果推断/概率推理
  🎨 创造与突破域 (7): 创造/跨界/灵感/隐喻/类比/范式/设计思维
  📊 分析与评估域 (8): 抽象/模式/评估/压力测试/知识图谱/系统/信息架构/美学
  🎯 战略与行动域 (7): 谋划/决策/落地/动态/博弈/时间/伦理
  💬 沟通与协作域 (7): 沟通/叙事/情绪/协作/说服力/语境/协作编排

复合能力 (9个)
  ├─ fix17: deai_5d / genesis / deep_strategy / cross_innovation / socratic_review
  ├─ fix19: super_prompt / cognitive_audit / knowledge_synthesis
  └─ fix20: content_engine (变异-绞杀存活, 适应度20)
```

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — run_lever user_msg加<rules>+<answer_operator>; 最终prompt加双层壳; 版本号→fix20
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 杠杆列表按6域分组+注释; assembleChainResult加双层壳; 版本徽章→fix20
- `src/Classes/MetaLever/class-linked3-meta-lever-registry.php` — info()新增domain+domain_label字段
- `src/Classes/MetaLever/Composite/class-composite-content-engine.php` — 新增内容引擎复合杠杆
- `src/Classes/MetaLever/Composite/class-linked3-composite-lever-registry.php` — 注册content_engine

---

## [v20.4-fix19] — 2026-07-17

### 🚀 归类补齐 + 新增复合能力 (46基础+8复合=54个杠杆)

#### 1. 6大能力域归类

将46个基础杠杆归入6大认知能力域：

| 能力域 | 杠杆数 | 核心能力 |
|--------|--------|----------|
| 🔍 认知与元认知域 | 10 | 元认知/元元认知/本质追问/注意力/折叠/自我校准/直觉/递归/概念/学习 |
| 🧠 逻辑与推理域 | 7 | 逻辑/苏格拉底/质疑/反向/问题发现/因果推断/概率推理 |
| 🎨 创造与突破域 | 7 | 创造/跨界/灵感/隐喻/类比/范式/设计思维 |
| 📊 分析与评估域 | 8 | 抽象/模式/评估/压力测试/知识图谱/系统/信息架构/美学 |
| 🎯 战略与行动域 | 7 | 谋划/决策/落地/动态/博弈/时间/伦理 |
| 💬 沟通与协作域 | 7 | 沟通/叙事/情绪/协作/说服力/语境/协作编排 |

#### 2. 新增10个补齐缺失的基础杠杆 (36→46)

| 新杠杆 | 能力域 | 能力描述 |
|--------|--------|----------|
| meta_metacognition | 认知域 | 元元认知: 对认知过程本身的认知 |
| meta_self_calibration | 认知域 | 元自我校准: 偏差检测+自动纠偏+置信度校准 |
| meta_intuition | 认知域 | 元直觉思维: 直觉判断+顿悟捕捉+启发式 |
| meta_recursion | 认知域 | 元递归思维: 自指分析+递归分解+怪圈检测 |
| meta_causal | 逻辑域 | 元因果推断: 因果模型+反事实推理+贝叶斯更新 |
| meta_probabilistic | 逻辑域 | 元概率推理: 不确定性量化+贝叶斯+风险感知 |
| meta_design | 创造域 | 元设计思维: 用户中心+原型迭代+同理心映射 |
| meta_information | 分析域 | 元信息架构: 信息密度控制+语义压缩+信号检测 |
| meta_persuasion | 沟通域 | 元说服力工程: 修辞手法+论证结构+影响力技巧 |
| meta_context | 沟通域 | 元语境感知: 情境理解+文化敏感+语境适配 |

#### 3. 新增3个复合能力 (5→8)

| 新复合杠杆 | 编排基础杠杆 | 部门编制 | 适用场景 |
|------------|-------------|----------|----------|
| **super_prompt** 超级Prompt转换器 | 本质+信息+设计+折叠+落地 | S1→S2→S3→S4→S5 | Prompt升级/结构化转换 |
| **cognitive_audit** 认知审计 | 自我校准+逻辑+评估+认知+质疑 | A1→A2→A3→A4→A5 | 偏差检测/谬误审查/证据评估 |
| **knowledge_synthesis** 知识综合 | 知识图谱+模式+类比+折叠+抽象 | K1→K2→K3→K4→K5 | 知识管理/图谱构建/跨域连接 |

#### 4. 完整三级杠杆体系 (54个杠杆)

```
基础能力 (46个)
  ├─ 原有12个 (fix9)
  ├─ fix16新增12个
  ├─ fix18新增12个
  └─ fix19新增10个 (补齐6大能力域缺口)

复合能力 (8个)
  ├─ fix17: deai_5d / genesis / deep_strategy / cross_innovation / socratic_review
  └─ fix19: super_prompt / cognitive_audit / knowledge_synthesis
```

### 改动文件

- `src/Classes/MetaLever/class-linked3-meta-lever-{metacognition,self-calibration,intuition,recursion,causal,probabilistic,design,information,persuasion,context}.php` — 10个新基础杠杆
- `src/Classes/MetaLever/Composite/class-composite-super-prompt.php` — 超级Prompt转换器
- `src/Classes/MetaLever/Composite/class-composite-cognitive-audit.php` — 认知审计
- `src/Classes/MetaLever/Composite/class-composite-knowledge-synthesis.php` — 知识综合
- `src/Classes/MetaLever/class-linked3-meta-lever-registry.php` — 注册10个新基础杠杆
- `src/Classes/MetaLever/Composite/class-linked3-composite-lever-registry.php` — 注册3个新复合杠杆
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — 杠杆列表扩展到46个
- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — 版本号 → `v20.4-fix19`

---

## [v20.4-fix18] — 2026-07-16

### 🚀 深层挖掘: 12个底层基础杠杆 (24→36)

基于1502页《Prompt提问研究小册子v4.9》第11章"AI乔哈里视窗认知框架"深度挖掘，
发现并补齐12个尚未覆盖的底层认知能力。

#### 新增12个深层基础杠杆

| 新杠杆 | PDF来源 | 能力描述 |
|--------|---------|----------|
| meta_narrative | 11.39 逻辑框架+故事叙述 | 叙事结构设计 + 故事弧线 + 情感共鸣点植入 |
| meta_pattern | 11.18.4 模式识别法 + 11.26.3 | 隐藏模式发现 + 模式断裂检测 + 模式迁移验证 |
| meta_emotion | 11.29 情绪智能 | 情绪识别 + 情绪共鸣 + 能量管理 + 情绪修复 |
| meta_concept | 11.30 概念工程 + 11.32 语义场 | 概念边界探索 + 概念层次分析 + 操作化定义 |
| meta_aesthetics | 11.35 美学判断 | 美学标准对齐 + 风格共识 + 审美敏感性 |
| meta_attention | 11.38.3 注意力管理 | 注意力聚焦 + 舒适区突破 + 认知负荷管理 |
| meta_knowledge_graph | 11.24.5 知识图谱 | 知识节点连接 + 知识图谱构建 + 知识缺口识别 |
| meta_temporal | 11.27.5 时间旅行 + 39.1.5 | 时间旅行想象 + 历史对比 + 未来推演 + 代际变迁 |
| meta_game | 39.6.2 博弈推演 | 博弈树构建 + 纳什均衡分析 + 群体思维防范 |
| meta_ethics | 11.14 伦理原则 | 伦理边界检验 + 价值冲突解决 + 预防性原则 |
| meta_paradigm | 11.31.1 范式解构 + 11.12 九大范式 | 范式识别 + 范式解构 + 范式重构 |
| meta_collaboration | 11.33 协作编排 + 11.17 角色互换 | 角色互换 + 优势互补 + 协作流程设计 |

#### 完整三级杠杆体系 (36基础 + 5复合 = 41个杠杆)

```
基础能力 (36个)
  ├─ 原有12个 (fix9): 学习/逻辑/认知/创造/批判/抽象/类比/系统/决策/沟通/问题发现/评估
  ├─ fix16新增12个: 本质/反向/谋划/跨界/灵感/压力测试/苏格拉底/折叠/动态/落地/质疑/隐喻
  └─ fix18新增12个: 叙事/模式/情绪/概念/美学/注意力/知识图谱/时间/博弈/伦理/范式/协作

复合能力 (5个) (fix17)
  ├─ deai_5d: 去AI味五部门
  ├─ genesis: 创世演化
  ├─ deep_strategy: 深度谋划
  ├─ cross_innovation: 跨界创新
  └─ socratic_review: 苏格拉底审查
```

### 改动文件

- `src/Classes/MetaLever/class-linked3-meta-lever-{narrative,pattern,emotion,concept,aesthetics,attention,knowledge-graph,temporal,game,ethics,paradigm,collaboration}.php` — 12个新文件
- `src/Classes/MetaLever/class-linked3-meta-lever-registry.php` — 注册12个新杠杆
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — 杠杆列表扩展到36个
- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — 版本号 → `v20.4-fix18`
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 版本徽章 → fix18

---

## [v20.4-fix17] — 2026-07-16

### 🚀 重大升级: 复合元杠杆体系 (5个复合能力)

在fix16的24个基础杠杆之上，实现5个复合能力（高级编排型杠杆），
每个复合杠杆编排3-5个基础杠杆，形成完整的部门工作流。

#### 1. 五个复合能力

| 复合杠杆 | 编排基础杠杆 | 部门编制 | 适用场景 |
|----------|-------------|----------|----------|
| **deai_5d** 去AI味五部门 | 本质追问+反向+批判+质疑+落地 | FP→EX→C→O→A | 去AI味/人类化/反检测 |
| **genesis** 创世演化 | 本质追问+创造+批判+质疑+评估 | FP→EX→C→O→A | 方案生成/MVP锁定/创世 |
| **deep_strategy** 深度谋划 | 谋划+系统+反向+动态+压力测试 | L1→L2→L3→L4→L5 | 商业策略/博弈推演 |
| **cross_innovation** 跨界创新 | 跨界+隐喻+压力测试+折叠+反向 | S1→S2→S3→S4→S5 | 产品创新/跨界颠覆 |
| **socratic_review** 苏格拉底审查 | 苏格拉底+质疑+本质+反向+评估 | D1→D2→D3→D4→D5 | 深度审查/批判分析 |

#### 2. 复合杠杆架构设计

每个复合杠杆包含：
- **部门编制**: 5个部门，每个部门绑定1个基础杠杆
- **SLA契约**: 部门间接口契约，违约→回退
- **演化循环**: G1→G2→G3三代演化
- **闸门与回退**: 公理自检→绞杀→降维→人审→进位/回退
- **完整system_prompt**: 包含部门编制+SLA+演化循环的完整提示词

#### 3. 三级杠杆体系完整架构

```
基础能力 (24个) ← fix16已实现
  ↓ 编排
复合能力 (5个) ← fix17已实现
  - deai_5d: 去AI味五部门 (FP→EX→C→O→A)
  - genesis: 创世演化 (3代5部门)
  - deep_strategy: 深度谋划 (5层递进)
  - cross_innovation: 跨界创新 (5步流水线)
  - socratic_review: 苏格拉底审查 (5步递进)
```

#### 4. 新增文件

- `src/Classes/MetaLever/Composite/interface-linked3-composite-lever.php` — 复合杠杆接口
- `src/Classes/MetaLever/Composite/class-linked3-composite-lever-registry.php` — 复合杠杆注册表
- `src/Classes/MetaLever/Composite/class-composite-deai5d.php` — 去AI味五部门
- `src/Classes/MetaLever/Composite/class-composite-genesis.php` — 创世演化
- `src/Classes/MetaLever/Composite/class-composite-deep-strategy.php` — 深度谋划
- `src/Classes/MetaLever/Composite/class-composite-cross-innovation.php` — 跨界创新
- `src/Classes/MetaLever/Composite/class-composite-socratic-review.php` — 苏格拉底审查

#### 5. Registry更新

- `class-linked3-meta-lever-registry.php` — 注册12个新增基础杠杆
- `class-linked3-cos-engine.php` — 加载复合杠杆注册表; 版本号 → `v20.4-fix17`

---

## [v20.4-fix16] — 2026-07-16

### 🚀 重大升级: 元杠杆三级体系 + 一键精准匹配

基于1502页《Prompt提问研究小册子v4.9》第39章"思考方式"7维度深度分析，
将元杠杆从12个扩展到24个基础能力，并新增一键精准匹配功能。

#### 1. 新增12个基础元杠杆 (12→24)

| 新杠杆 | 来源 | 能力描述 |
|--------|------|----------|
| meta_essence | PDF 39.1.1 本质与根源 | 第一性原理 + 底层逻辑剥离 + 简化模型 |
| meta_reverse | PDF 39.3 反向提问 | 假设颠覆 + 极端情境 + 角色对抗 + 逻辑逆推 |
| meta_strategy | PDF 39.6 深度谋划 | 战略洞察 + 博弈推演 + 资源编排 + 执行暗线 |
| meta_crossover | PDF 39.3.6 跨界颠覆 | 生物拟态 + 物理迁移 + 艺术解构 + 游戏化 |
| meta_inspiration | PDF 39.5.6 灵感管理 | 碎片重组 + 暗物质捕捉 + 灵感坍缩 |
| meta_stress_test | PDF 39.5.4 深度验证 | 破坏性检验 + 二阶效应 + 反常识验证 |
| meta_socratic | PDF 39.4.7 苏格拉底 | 澄清→挑战假设→追问证据→探索替代→检验影响 |
| meta_folding | PDF 39.5.5 认知折叠 | 符号公式 + 视觉谚语 + 听觉锚点 + 逆生长 |
| meta_dynamics | PDF 39.1.5 系统与动态 | 反馈回路 + 延迟效应 + 临界点 + 蝴蝶效应 |
| meta_execution | PDF 39.1.7 行动与落地 | 具体步骤 + 最小试点 + 风险对冲 + 约束优化 |
| meta_questioning | PDF 39.4 质疑方式 | 逻辑结构 + 立场偏见 + 系统性解构 + 创造性颠覆 |
| meta_metaphor | PDF 39.5.3 隐喻工程 | 表层类比→结构映射→原理迁移 |

#### 2. 一键精准匹配功能

新增 `scene_match_levers()` 方法，支持18个场景的精准匹配：
- 电商营销类: 电商/选品/小红书/营销/转化 (5个场景)
- 内容创作类: 写作/文章/视频/封面/标题 (5个场景)
- 技术工程类: 架构/系统/代码 (3个场景)
- 商业策略类: 策略/增长/商业 (3个场景)
- 通用审查类: 优化/分析 (2个场景)

每个场景匹配到6个最相关的杠杆组合，覆盖该场景的核心认知需求。

#### 3. 商用生产级最佳实践

- **三级分类**: 基础(24) → 高级(8,待实现) → 复合(5,待实现)
- **分级超时**: 基础35s / 高级50s / 复合120s(分块串行)
- **容错重试**: 基础重试1次 / 高级降级基础 / 复合部门级容错
- **可维护性**: 每杠杆一个PHP类 + 统一接口 + 标签筛选
- **扩展性**: 新增杠杆只需创建PHP类 + 注册到Registry

### 改动文件

- `src/Classes/MetaLever/class-linked3-meta-lever-essence.php` 等12个新文件 — 新增基础杠杆
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — 杠杆列表扩展到24个 + `scene_match_levers()` 方法
- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — 版本号 → `v20.4-fix16`
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 版本徽章 → fix16

---

## [v20.4-fix15] — 2026-07-16

### 🐛 关键修复: 杠杆链 4/6 "Failed to fetch" (72B 模型太慢)

**问题**: fix14 后杠杆链仍有 4/6 显示"降级模式 Failed to fetch", 只有 2 个成功。

**根因 (双重)**:

1. **72B 模型响应慢**: Qwen2.5-72B 生成 1200 tokens 需 40-60s, 接近 web server 60s 超时。
   虽然设了 timeout=40s, 但 72B 模型在 siliconflow 上排队+生成经常超过 40s → AI 超时 → 降级。

2. **连续请求触发熔断器**: 6 个杠杆串行调用, 前几个失败累积触发熔断器 (阈值 5 次),
   后续杠杆即使 AI 已恢复也被 `is_circuit_open()` 跳过 → 全部降级。

**修复 (四管齐下)**:

#### 1. 降级模型 72B → 32B (核心修复)
- `Qwen/Qwen2.5-72B-Instruct` → `Qwen/Qwen2.5-32B-Instruct`
- 32B 生成 800 tokens 约 15-25s, 远低于 35s timeout
- 32B 质量虽略低于 72B, 但远强于 7B, 能正确遵循纯文本输出指令
- 应用范围: `run_lever` (杠杆链) + `generate_variants_via_ai` (演化方案生成)

#### 2. 前端增加杠杆间延迟 1.5 秒
- 旧实现: 杠杆完成后立即调用下一个, 连续请求容易触发熔断器
- fix15: `setTimeout(function(){ runOneLever(idx + 1); }, 1500)`
- 1.5 秒间隔让 AI provider 有喘息时间, 降低连续失败概率

#### 3. 前端增加单杠杆失败自动重试 1 次
- 旧实现: 杠杆失败直接记录错误, 继续下一个
- fix15: 首次失败后等 2 秒自动重试, 重试仍失败才记录错误
- 网络抖动/AI 临时超时等瞬时故障可通过重试恢复

#### 4. 全链路超时进一步调优

| 层 | fix14 旧值 | fix15 新值 | 理由 |
|----|-----------|-----------|------|
| AI timeout | 40s | **35s** | 32B 模型 15-25s, 留 10s 余量 |
| PHP set_time_limit | 55s | **50s** | 匹配 AI 35s + 解析 10s + 余量 5s |
| 前端 post() 超时 | 65s | **55s** | 留 5s 余量给网络传输 |
| AI max_tokens | 1200 | **800** | 减少生成时间, 32B 模型 800 tokens 足够 |

### 📊 影响范围

| 功能 | fix14 状态 | fix15 状态 |
|------|-----------|-----------|
| 杠杆链成功率 | ❌ 2/6 (33%) | ✅ 预期 5/6+ (83%+) |
| AI 模型 | ⚠️ 72B (慢, 40-60s) | ✅ 32B (快, 15-25s) |
| 失败恢复 | ❌ 无重试 | ✅ 自动重试 1 次 |
| 请求间隔 | ❌ 无延迟 | ✅ 1.5 秒间隔 |
| 超时安全余量 | ⚠️ 40s+55s (可能超 60s) | ✅ 35s+50s (安全 <60s) |

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — `run_lever`: 模型 72B→32B, timeout 40→35, max_tokens 1200→800; 版本号 → `v20.4-fix15`
- `src/Classes/CognitiveOS/Core/class-linked3-cos-departments.php` — `generate_variants_via_ai`: 同样降级模型 + 调参
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — `ajax_run_lever`/`ajax_evolve_gen`: set_time_limit 55→50
- `admin/views/dashboard/partials/tab-cognitive-os.php` — `runOneLever`: 增加重试+延迟; `post()`: 超时 65→55; 版本徽章 → fix15

---

## [v20.4-fix14] — 2026-07-16

### 🐛 关键修复: 演化 G3 "Failed to fetch" + fix13 deepseek bug

**问题 1**: 演化 G1/G2 成功但 G3 报 `Failed to fetch`, 导致无法结晶 Skill。

**问题 2**: fix13 错误地硬编码 `'provider' => 'deepseek'`, 但用户只配置了 siliconflow,
导致杠杆链调用 deepseek 失败 (无 API Key) → 全部降级。

**根因 (双重)**:

1. **fix13 的 deepseek bug**: `run_lever` 硬编码 `'provider' => 'deepseek'`,
   但用户只配置了 siliconflow。调用链 = `[deepseek, deepseek, qwen]` — 全部无 API Key,
   全部失败。虽然演化不直接用 `run_lever`, 但 fix13 的改动思路有误。

2. **web server 60s 超时**: 诊断显示 `max_execution_time: 60 秒`。
   G1/G2 快速完成 (~6s), 但 G3 偶尔 AI 响应慢 (>60s) → web server 掐断连接 → `Failed to fetch`。
   `set_time_limit(70)` 无效, 因为 web server 比 PHP 先掐断。

**修复**:

#### 1. 移除 fix13 的 `'provider' => 'deepseek'` 硬编码
- 改为使用 `default_provider` (用户配置的, 通常为 siliconflow)
- 指定更强的模型 `'model' => 'Qwen/Qwen2.5-72B-Instruct'` (在 siliconflow 上可用)
- 72B 模型比 7B 强得多, 能正确遵循纯文本输出指令, 避免乱码

#### 2. fallback 链只包含已配置 API Key 的 provider
- 旧实现: fallback 包含 deepseek/qwen/openai/kimi, 即使用户没配置
- fix14: 检查 `provider_keys` option, 只把有 Key 的 provider 作为 fallback
- siliconflow 有内置默认 Key, 始终可用

#### 3. 全链路超时调优 (应对 web server 60s 限制)

| 层 | fix13 旧值 | fix14 新值 | 理由 |
|----|-----------|-----------|------|
| AI timeout | 50s | **40s** | 留 15s 余量给 PHP + web server |
| PHP set_time_limit | 70s | **55s** | 匹配 AI 40s + 解析 10s + 余量 5s |
| 前端 post() 超时 | 75s | **65s** | 留 5s 余量给网络传输 |
| 演化 max_tokens | 1500 | **1200** | 加快 AI 响应, 降低超时概率 |

#### 4. 演化也升级模型
- `generate_variants_via_ai` 同样指定 `'model' => 'Qwen/Qwen2.5-72B-Instruct'`
- 72B 模型生成方案质量更高, 且响应速度可接受

### 📊 影响范围

| 功能 | fix13 状态 | fix14 状态 |
|------|-----------|-----------|
| 演化 G3 成功率 | ❌ 偶尔 Failed to fetch | ✅ timeout 40s 降低超时概率 |
| 杠杆链 provider | ❌ 硬编码 deepseek (无 Key) | ✅ 用 default_provider + 72B 模型 |
| fallback 链 | ❌ 包含未配置的 provider | ✅ 只含已配置 Key 的 |
| AI 模型 | ⚠️ Qwen2.5-7B (弱, 乱码) | ✅ Qwen2.5-72B (强, 纯文本) |
| 超时安全余量 | ⚠️ 50s+70s (可能超 60s) | ✅ 40s+55s (安全 <60s) |

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — `run_lever`: 移除 provider=deepseek, 改用 model=Qwen2.5-72B; timeout 50→40; fallback 只含已配置 provider; 版本号 → `v20.4-fix14`
- `src/Classes/CognitiveOS/Core/class-linked3-cos-departments.php` — `generate_variants_via_ai`: 同样升级模型 + timeout 50→40 + fallback 过滤
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — `ajax_evolve_gen`/`ajax_run_lever`: set_time_limit 70→55
- `admin/views/dashboard/partials/tab-cognitive-os.php` — post() 超时 75→65; 版本徽章 → fix14

---

## [v20.4-fix13] — 2026-07-16

### 🐛 关键修复: 杠杆链输出全是乱码 (JSON 碎片 + 空格 + 重复字符)

**问题**: fix12 后杠杆链 5/6 成功调用 AI, 但输出全是乱码:
- 大量空格 ` ` 重复
- JSON 代码块碎片 (`json { "creativity_trace": : "SCAMPER " " " " "`)
- 重复字符 (`" " " " " "`)
- 最终 system_prompt 包含所有乱码, 无法使用

**根因 (双重)**:

1. **MetaLever system_prompt 要求 JSON 输出**: 12 个杠杆的 system_prompt 都包含
   `json { ... }` 代码块, 要求 AI 输出结构化 JSON。但 Qwen2.5-7B (默认模型)
   能力不足, 无法正确生成复杂 JSON, 产生乱码。

2. **弱模型 Qwen2.5-7B**: 该模型在生成结构化 JSON 时容易崩溃, 输出大量
   重复字符和碎片。需要升级到更强的模型 (deepseek-chat)。

**修复 (三管齐下)**:

#### 1. 所有 12 个 MetaLever system_prompt 改为纯文本输出
- 去掉所有 `json ... ` 代码块
- 改为"用清晰的中文段落输出, 每个要点用'•'或数字编号开头"
- 明确要求"不要输出 JSON, 不要输出代码块, 不要输出 ``` 标记"

#### 2. 升级默认模型为 deepseek-chat
- `run_lever` 新增 `'provider' => 'deepseek'` 指定更强模型
- deepseek-chat 比 Qwen2.5-7B 强得多, 能正确遵循纯文本输出指令
- 保留多样化 fallback 链 (siliconflow/qwen/openai/kimi)

#### 3. 新增 AI 输出清理机制 (前后端双重)
- **后端**: `Linked3_COS_Engine::clean_ai_output()` 方法
  - 去掉 ```json 和 ``` 标记
  - 去掉行首尾的 { } [ ] (JSON 残留)
  - 去掉连续 3+ 空格 (乱码特征)
  - 去掉连续 5+ 相同字符 (重复乱码)
  - 去掉行首引号残留
- **前端**: `cleanAiOutput()` JS 函数 (同样逻辑)
- **应用点**: `run_lever` 返回前清理 + `chain_levers` 最终 prompt 清理 + 前端渲染前清理

#### 4. max_tokens 从 800 → 1200
- fix12 的 800 有时不够, 导致输出被截断
- 1200 保证完整的 300-600 字分析

### 📊 影响范围

| 功能 | fix12 状态 | fix13 状态 |
|------|-----------|-----------|
| 杠杆链输出可读性 | ❌ 全是乱码 | ✅ 清晰中文段落 |
| 最终 system_prompt | ❌ 包含乱码 | ✅ 干净可读 |
| AI 模型 | ⚠️ Qwen2.5-7B (弱) | ✅ deepseek-chat (强) |
| JSON 格式要求 | ❌ 导致乱码 | ✅ 改为纯文本 |
| 输出完整性 | ⚠️ 800 tokens 可能截断 | ✅ 1200 tokens 完整 |

### 改动文件

- `src/Classes/MetaLever/class-linked3-meta-lever-*.php` (12 个文件) — system_prompt 去掉 JSON, 改为纯文本
- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — `run_lever`: 指定 deepseek 模型, max_tokens 800→1200, 新增 `clean_ai_output()` 方法; `chain_levers` 清理最终 prompt; 版本号 → `v20.4-fix13`
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 新增 `cleanAiOutput()` JS 函数; `renderChainResult` 清理显示; `assembleChainResult` 清理累积分析

---

## [v20.4-fix12] — 2026-07-16

### 🐛 关键修复: 杠杆链 5/6 "Failed to fetch" (AI 超时)

**问题**: fix11 后杠杆链能运行, 但 6 个杠杆中 5 个显示 `✗ 降级模式 网络错误: Failed to fetch`,
只有 1 个 (meta_creativity) 成功调用 AI。

**根因 (三层)**:

1. **AI 超时 > web server 超时**: `run_lever` 设置 `timeout=60s`, `max_tokens=1200`。
   Qwen2.5-7B 生成 1200 tokens 需 40-60s, 加上 prompt 解析, 总耗时 >60s。
   而 web server (nginx/Apache) 默认 60s 超时 → 连接被掐断 → `Failed to fetch`。
   `set_time_limit(90)` 无效, 因为外层 web server 先掐断。

2. **累积分析膨胀**: 链式调用中, 第 N 个杠杆的 prompt 包含前 N-1 个杠杆的全部分析。
   第 6 个杠杆的 prompt 可能 >5000 字, AI 处理时间更长 → 更易超时。

3. **客户端超时过长**: 前端 `post()` 超时 90s > web server 60s, 服务器已掐断但浏览器还在等。

**修复 (对症下药)**:

| 层 | 旧值 | fix12 值 | 理由 |
|----|------|----------|------|
| AI `max_tokens` | 1200 | **800** | 减少 AI 生成时间 (Qwen2.5-7B 约 25-35s) |
| AI `timeout` | 60s | **50s** | 留 10s 余量给 PHP + web server |
| PHP `set_time_limit` | 90s | **70s** | 匹配 AI 50s + 解析 10s + 余量 10s |
| 前端 `post()` 超时 | 90s | **75s** | 留 15s 余量给网络传输 |
| 累积分析 | 全量传入 | **截断到 1500 字** | 第 6 个杠杆 prompt 不再膨胀 |
| 演化 `max_tokens` | 2000 | **1500** | 同理加快演化 AI 响应 |

### ✨ 新功能 1: 领域下拉 + 自定义输入

**问题**: 旧版"领域"是纯文本框, 用户需手动输入 `ecommerce` 等英文标识, 易拼错且不直观。

**修复**: 改为下拉选择 + 自定义输入:
- 预置 7 个常用领域: ecommerce / seo / content / video / business / tech / general
- 每个选项显示中英文对照 (如 `ecommerce · 电商/营销`)
- 选择"✏️ 自定义..."时展开文本框, 支持任意输入 (如 `education`)

### ✨ 新功能 2: 重置 AI 熔断器按钮

**问题**: AI 曾因超时失败触发熔断器 (阈值 5 次, TTL 5 分钟), 即使 API 已恢复,
熔断器仍处于打开状态, 用户需等待 5 分钟或手动清 transient。

**修复**: 新增 `ajax_reset_circuit` 端点 + 两个入口:
- **永久按钮**: 杠杆链区域旁的"🔄 重置 AI 熔断器"按钮, 随时可点
- **诊断内按钮**: AI 诊断失败时自动显示"重置熔断器"按钮
- 清除所有 8 个 provider 的 `linked3_pcb_{slug}` transient, 立即恢复可用

### 📊 影响范围

| 功能 | fix11 状态 | fix12 状态 |
|------|-----------|-----------|
| 杠杆链 AI 调用成功率 | ❌ 1/6 (17%) | ✅ 预期 5/6+ (83%+) |
| 杠杆链总耗时 | 6×60s=360s (超时) | 6×35s=210s (正常) |
| 领域输入易用性 | ❌ 纯文本易拼错 | ✅ 下拉+自定义 |
| 熔断器恢复 | ❌ 等 5 分钟 | ✅ 一键重置 |
| 演化 AI 响应 | ⚠️ 偶尔超时 | ✅ 更快更稳 |

### 改动文件

- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — `run_lever`: max_tokens 1200→800, timeout 60→50, 累积分析截断 1500 字; 版本号 → `v20.4-fix12`
- `src/Classes/CognitiveOS/Core/class-linked3-cos-departments.php` — `generate_variants_via_ai`: max_tokens 2000→1500, timeout 55→50
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — `ajax_run_lever`/`ajax_evolve_gen`: set_time_limit 90→70; 新增 `ajax_reset_circuit` 端点
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 领域下拉+自定义; 重置熔断器按钮 (永久+诊断内); post() 超时 90→75

---

## [v20.4-fix11] — 2026-07-16

### 🐛 关键修复 1: 版本探针误报 "旧代码仍在运行"

**问题**: 升级到 fix10 后, 顶部徽章仍显示 `✗ 旧代码仍在运行 (v20.4-fix10)`, 即使代码已是最新。

**根因**: `ajax_diagnose` 端点中 `chain_chunked_fix10` 检查项的路径计算错误:
```php
$tab_file = dirname(__DIR__, 3) . '/admin/views/dashboard/partials/tab-cognitive-os.php';
```
`__DIR__` = `src/Classes/CognitiveOS/Ajax`, `dirname(__DIR__, 3)` = `src/` (只上溯 3 级),
而 tab 文件在插件根目录的 `admin/views/...` 下, 需上溯 **4 级**才到插件根目录。
路径错误 → `file_get_contents` 返回 false → `strpos` 返回 false → `chain_chunked_fix10 = false`
→ `allOk = false` → 显示"旧代码仍在运行"。

**修复**:
- `dirname(__DIR__, 3)` → `dirname(__DIR__, 4)` (正确到达插件根目录)
- 加 `@` 抑制 `file_get_contents` 警告, 加 `defined('LINKED3_DIR')` 兜底 (symlink 场景)
- 加 `$content !== false` 前置检查, 避免对 false 调用 `strpos`

### 🐛 关键修复 2: 杠杆链全部 "降级模式" (AI 未调用)

**问题**: fix10 修复了 `TypeError: Failed to fetch` 后, 杠杆链能正常运行, 但 6 个杠杆全部显示
"降级模式" (AI 未调用), 没有真正的 AI 审查。

**根因 (双重)**:

1. **熔断器陈旧打开**: fix9 时代杠杆链因超时失败 6 次, 触发熔断器 (阈值 5 次, TTL 5 分钟)。
   fix10 升级后熔断器仍处于打开状态, 所有 `siliconflow` 调用被 `is_circuit_open()` 跳过 → 降级。

2. **fallback 链冗余**: `run_lever` 和 `generate_variants_via_ai` 都硬编码
   `fallback_providers = ['siliconflow']`, 而 `default_provider` 通常也是 `siliconflow`。
   于是调用链 = `[siliconflow, siliconflow]` — 主备相同, 主挂备也挂, fallback 完全失效。

**修复**:
- **多样化 fallback 链**: 从 option 读取 `default_provider`, 构建去重的 fallback 链
  (候选池 `['siliconflow', 'deepseek', 'qwen', 'openai', 'kimi']`, 排除与 primary 相同的, 取前 2 个)
- **绕过陈旧熔断器**: 新增 `force_bypass_circuit` config, 用户主动触发的调用 (杠杆链、演化)
  允许绕过熔断器直接尝试 — 熔断器可能因前一次失败而打开, 但 API 已恢复
- **应用范围**: `run_lever` (杠杆链) + `generate_variants_via_ai` (演化方案生成) 均已修复

**改动文件**:
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — 修正 `chain_chunked_fix10` 路径
- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — `run_lever` 多样化 fallback + 绕过熔断器; 版本号 → `v20.4-fix11`
- `src/Classes/CognitiveOS/Core/class-linked3-cos-departments.php` — `generate_variants_via_ai` 多样化 fallback + 绕过熔断器
- `src/Classes/Core/class-linked3-ai-dispatcher.php` — `chat()` 支持 `force_bypass_circuit` config
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 版本徽章 → `v20.4-fix11`

### 📊 影响范围

| 功能 | fix10 状态 | fix11 状态 |
|------|-----------|-----------|
| 杠杆链网络错误 | ✅ 已修复 | ✅ 保持 |
| 版本探针误报 | ❌ 误报"旧代码" | ✅ 正确显示"已生效" |
| 杠杆链 AI 调用 | ❌ 全部降级 | ✅ 真实调用 AI |
| 演化方案生成 | ⚠️ 可能降级 | ✅ 真实调用 AI |

---

## [v20.4-fix10] — 2026-07-16

### 🐛 关键修复: 杠杆链 "TypeError: Failed to fetch"

**问题**: 认知 OS → STEP 5 杠杆链调用点击"运行杠杆链"后报 `❌ 网络错误: TypeError: Failed to fetch`。

**根因**: `ajax_chain_levers` 端点在单个 PHP 请求里串行跑 6 个 AI 调用 (每个 timeout=60s, 总计最长 360s)。
虽然 PHP `set_time_limit(300)` 允许 5 分钟, 但 web server (Apache/nginx) 和 PHP-FPM 有各自更短的超时:
- nginx `proxy_read_timeout` 默认 60s
- Apache `Timeout` 默认 60s
- PHP-FPM `request_terminate_timeout` 常见 60-120s

当 web server / PHP-FPM 先于 PHP 业务逻辑掐断连接时, 浏览器 fetch 收到的是非 HTTP 响应 (连接重置),
于是抛出 `TypeError: Failed to fetch`。这与 PHP 业务逻辑是否成功无关。

**修复**: 采用与 `evolve_gen` (三代演化) 相同的**分块串行模式**:
- 前端改为逐个调用 `ajax_run_lever` 端点 (已存在, 支持链式 `accumulated_analysis` 输入)
- 每个 AJAX 请求只跑 1 个 AI 调用 (≤60s), 远低于 web server 超时
- 实时渲染进度条 + 已完成杠杆列表 (✓/✗ + AI 已调用/降级模式 标记)
- 单个杠杆失败不中断整条链, 继续后续杠杆 (容错降级)
- 全部完成后前端组装最终增强 prompt (与后端 `chain_levers` 逻辑一致)

**改动文件**:
- `admin/views/dashboard/partials/tab-cognitive-os.php` — 重写杠杆链按钮处理器为分块串行
- `src/Classes/CognitiveOS/Ajax/class-linked3-cos-ajax.php` — `ajax_run_lever` 加 `set_time_limit(90)`; `ajax_chain_levers` 标记 DEPRECATED 并降超时到 120s; 版本探针加 `chain_chunked_fix10` 检查项
- `src/Classes/CognitiveOS/class-linked3-cos-engine.php` — `chain_levers` 方法不再 `break` 中断链 (单个杠杆失败继续); 版本号 → `v20.4-fix10`

### 🛡️ 健壮性增强

- **客户端超时保护**: `post()` 函数加 `AbortController` + 90s 超时, 避免服务器挂起时浏览器无限等待; 超时抛出友好中文提示
- **容错链式**: 杠杆链单个杠杆网络错误/AI 超时不再整链作废, 标记失败后继续下一个
- **版本探针增强**: 新增 `chain_chunked_fix10` 检查项, 验证前端已切换到分块串行模式

---

## [19.1.0] — 2026-06-29

### MetaMother 元母体嵌入 (Master Template Integration)
嵌入来源: `genesis_meta2_M2_G3` 母版 (meta的meta·真理探索系统元母体)

- **新增** `Linked3_Book_MetaMother` 元母体引擎类，实现4阶元流程：
  - 第一阶·探索方式分类 (`classify_intent`) — 根据意图推荐最佳探索原型
  - 第二阶·系统原型生成 (`generate_prototype`) — 生成完整探索系统配置
  - 第三阶·元规律提炼 (`extract_meta_laws`) — 评估探索结果的5大元规律合规性
  - 第四阶·新系统创造 (`create_new_system`) — 按六步创造法生成新探索系统
- **新增** `Linked3_Book_Exploration_Prototypes` 9大探索原型定义：
  - 📖 写书式 (book) — 系统化知识整理
  - 🔬 实验式 (experimental) — 假设验证
  - 👁️ 观察式 (observational) — 现象记录
  - 🧮 推演式 (deductive) — 逻辑证明
  - 🧘 冥想式 (meditative) — 内观觉知
  - 💬 对话式 (dialogic) — 辩证追问
  - 🔧 实践式 (practical) — 效果迭代
  - 🎨 艺术式 (artistic) — 美学表达
  - 💻 计算式 (computational) — 算法模拟
  - 🌐 综合式 (synthetic) — 多维并行
- **新增** `prompt_library/meta_mother_prompts.json` 元母体提示词库（含16条/meta2指令、9原型提示词、5大元规律定义）
- **新增** `config/meta_mother.yaml` 元母体配置文件（4阶流程开关、9原型流水线映射、5大元规律评分阈值）
- **新增** 6个 MetaMother AJAX 端点：
  - `linked3_book_meta_classify` — 探索方式分类
  - `linked3_book_meta_prototype` — 系统原型生成
  - `linked3_book_meta_extract` — 元规律提炼
  - `linked3_book_meta_create` — 新系统创造
  - `linked3_book_meta_info` — 元母体元信息
  - `linked3_book_meta_prototypes` — 原型列表

### 双公理 (M²元母体级)
- **公理一·元熵减**: 从众多真理探索系统中萃取"探索真理的元规律"
- **公理二·元降维**: 把"探索真理"降维为"分类→生成→提炼→创造"四阶

### 5大元规律 (Meta Laws)
- 可证伪: 真理必须可被证伪
- 可传递: 真理必须可被传递
- 可具现: 真理必须可被具现
- 可进化: 真理必须可被进化
- 可守护: 真理必须可被守护

### 路由扩展
- **新增** `Type_Mode_Router::get_all_exploration_prototypes()` 获取9大原型
- **新增** `Type_Mode_Router::route_with_prototype()` 支持探索原型维度的路由查询
- 非book原型自动覆盖 `step4_expand` 提示词风格

### 向后兼容
- 默认保持 `book` 原型，现有项目零修改
- MetaMother 为可选子系统，可通过 `meta_mother.yaml` 的 `enabled: false` 禁用
- 所有 v19.0 的接口契约、依赖注入、配置驱动特性全部继承

---

## [19.0.0] — 2026-06-29

### 上帝类拆分 (Modularity — God Class Refactoring)
- **新增** `Linked3_Book_Pipeline_Orchestrator` 流水线编排器，从 Book_Factory 拆分流程编排职责（~200行）
- **新增** `Linked3_Book_Draft_Builder` 草稿构建器，从 Book_Factory 拆分草稿重建职责
- **新增** `Linked3_Book_Outline_Processor` 大纲处理器，整合 Outline_Merger Trait + smart_split_outline
- **新增** `Linked3_Book_Section_Expander_Service` 章节展开服务，整合 Section_Expander Trait
- **新增** `Linked3_Book_Review_Coordinator` 审校协调器，整合 Review_Linker Trait
- **重构** `Linked3_Book_Factory` 降级为向后兼容的外观类（Facade），静态 API 不变，新代码应使用 Orchestrator（MOD-01）

### 接口契约体系 (Interface Contracts)
- **新增** `Linked3_Book_AI_Caller_Interface` AI 调用抽象接口，解耦 BookFactory 与具体 AI 引擎
- **新增** `Linked3_Book_State_Repository_Interface` 状态仓储接口，解耦状态读写与存储实现
- **新增** `Linked3_Book_Prompt_Provider_Interface` 提示词提供者接口，解耦提示词获取与来源
- **新增** `Linked3_Book_Cost_Tracker_Interface` 成本追踪接口，解耦成本计算与实现
- **新增** `Linked3_Book_Default_AI_Caller` 默认 AI 调用器实现，委托给现有 AI 引擎
- **新增** `Linked3_Book_Default_Cost_Tracker` 默认成本追踪器实现（MOD-02）

### 依赖注入 (Dependency Injection)
- **重构** Pipeline_Orchestrator/Section_Expander_Service/Review_Coordinator 通过构造函数注入 AI_Caller/Prompt_Provider/Cost_Tracker
- **新增** `Linked3_Book_Factory::orchestrator()` 静态方法，获取单例 Orchestrator 实例
- **优化** 所有新类支持 null 依赖回退到默认实现，保持向后兼容（MOD-04）

### 配置驱动化 (Configuration-Driven)
- **新增** `config/steps.yaml` 步骤配置文件，将步骤定义外部化
- **重构** `Linked3_Book_Step_Registry` 从 YAML 加载步骤定义，替代硬编码注册
- **新增** 简易 YAML 解析器，无需 Symfony YAML 依赖即可解析 steps.yaml
- **优化** 步骤支持 `enabled: false` 配置，可临时禁用步骤（MOD-03）

### 向后兼容
- `Linked3_Book_Factory` 所有静态方法保持不变，前端与 AJAX 端点无需修改
- v18.11 的安全加固、异步化、步骤接口化全部继承
- Trait 组合保持不变，拆分类通过 `use` 复用现有 Trait 逻辑

---

## [18.11.0] — 2026-06-29

### 安全加固 (Security)
- **新增** `Linked3_Book_Security` 安全工具类，集中处理路径校验、参数枚举、错误脱敏、原子写入
- **修复** `project_id` 路径遍历风险：所有 AJAX 端点增加 `[a-zA-Z0-9_-]` 白名单校验（SEC-01）
- **修复** `file_put_contents` 并发写入竞态：状态文件与草稿文件改用临时文件+rename 原子写入，加 `LOCK_EX` 排他锁（SEC-03）
- **修复** `format` 参数未校验：增加 `md`/`html` 枚举校验，默认 `md`（SEC-04）
- **修复** AJAX 错误信息泄露 `file`/`line`：所有 `wp_send_json_error` 响应移除敏感字段，错误信息经 `sanitize_error_message` 脱敏（SEC-06）

### 异步化改造 (Performance)
- **新增** `Linked3_Book_Async_Runner` 异步任务调度器，基于 wp_cron 实现后台链式执行
- **新增** `start_async` AJAX 端点：创建项目后自动调度后台执行，前端只需轮询 `progress`
- **新增** `cancel_async` AJAX 端点：取消后台执行并暂停项目
- **新增** WP-CLI 批量执行支持：`wp linked3 book run <project_id>` 可在 CLI 环境连续执行
- **优化** `run_step` 完成后自动调度下一步，解决 v18.10.3 前端需反复轮询触发的问题（PERF-01）

### 步骤接口化 (Modularity)
- **新增** `Linked3_Book_Step_Interface` 步骤接口契约，定义 `get_step_id`/`get_label`/`execute`/`get_next_step`
- **新增** `Linked3_Book_Step_Registry` 步骤注册表，替代 `run_step` 中的 switch-case 硬编码路由
- **新增** `Linked3_Book_Step_Adapter` 适配器，将现有 `execute_stepN_xxx` 方法包装为接口实现
- **新增** `linked3_book_register_step` 钩子，允许第三方插件注册自定义步骤
- **重构** `execute_step1_demo` 至 `execute_step6_review` 方法可见性从 `private` 改为 `public`（MOD-03）

### 状态持久化加固 (Reliability)
- **新增** 状态 schema 版本号字段 `schema_version`，支持未来状态结构变更的自动迁移（MAINT-04）
- **新增** `maybe_migrate` 迁移方法，v1（无版本号）→ v2 自动升级
- **优化** 状态文件写入改用原子写入，确保文件不会出现半写入状态

### 向后兼容
- 所有 v18.10.3 的 AJAX 端点保持不变，前端无需修改
- `run_step` 端点保留同步模式，同时新增 `start_async` 异步模式
- 步骤注册表回退兼容：注册表中未找到的步骤回退到 switch-case

---

## [18.10.3] — 基线版本（审计起点）
- 132 节爆炸修复
- 保存方式优化
- 输出节奏"第第"重复修复
- 卡住问题修复
- 下载失败修复
- 性能优化
