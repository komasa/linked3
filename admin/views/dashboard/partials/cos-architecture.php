<?php
/**
 * Partial: cos-architecture
 * Extracted from: tab-cognitive-os.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- ═══════════════════════════════════════════════════════════════
         双公理 + 五部门 + 三代演化 — 架构说明 (折叠)
    ═══════════════════════════════════════════════════════════════ -->
    <details style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
        <summary style="font-size: 14px; font-weight: 600; color: #1f2937; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">📐</span> COS 架构说明 (双公理 · 五部门 · 三代演化) — 点击展开
        </summary>

        <!-- v20.4-fix26: 参考GordenPPTSkill/ppt-master的图示底层逻辑, 用SVG可视化架构 -->
        <!-- 设计原则: 1)信息层级清晰 2)色彩语义化 3)流程方向明确 4)关键数据高亮 -->

        <!-- 架构总览SVG: 双公理→五部门→三代演化→MVP -->
        <div style="margin-top: 16px; background: #fafafa; border-radius: 8px; padding: 16px; overflow-x: auto;">
            <div style="font-size: 12px; font-weight: 600; color: #1f2937; margin-bottom: 12px; text-align: center;"><?php echo esc_html__('📊 COS 认知操作系统架构图', 'linked3'); ?></div>
            <svg width="100%" height="280" viewBox="0 0 800 280" xmlns="http://www.w3.org/2000/svg" style="max-width: 800px; margin: 0 auto; display: block;">
                <!-- 定义渐变和箭头 -->
                <defs>
                    <linearGradient id="axiomGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#dbeafe;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#bfdbfe;stop-opacity:1" />
                    </linearGradient>
                    <linearGradient id="deptGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#dcfce7;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#bbf7d0;stop-opacity:1" />
                    </linearGradient>
                    <linearGradient id="evoGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#fce7f3;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#fbcfe8;stop-opacity:1" />
                    </linearGradient>
                    <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                        <polygon points="0 0, 10 3.5, 0 7" fill="#6b7280" />
                    </marker>
                </defs>

                <!-- 第一层: 双公理 -->
                <rect x="50" y="20" width="700" height="50" rx="8" fill="url(#axiomGrad)" stroke="#3b82f6" stroke-width="1.5"/>
                <text x="400" y="42" text-anchor="middle" font-size="13" font-weight="600" fill="#1e40af"><?php echo esc_html__('⚖️ 双公理系统', 'linked3'); ?></text>
                <text x="200" y="60" text-anchor="middle" font-size="10" fill="#374151"><?php echo esc_html__('公理一: 信息熵减', 'linked3'); ?></text>
                <text x="600" y="60" text-anchor="middle" font-size="10" fill="#374151"><?php echo esc_html__('公理二: 系统降维', 'linked3'); ?></text>

                <!-- 箭头: 公理→部门 -->
                <line x1="400" y1="70" x2="400" y2="95" stroke="#6b7280" stroke-width="2" marker-end="url(#arrowhead)"/>

                <!-- 第二层: 五部门流水线 -->
                <rect x="50" y="100" width="700" height="60" rx="8" fill="url(#deptGrad)" stroke="#10b981" stroke-width="1.5"/>
                <text x="400" y="120" text-anchor="middle" font-size="13" font-weight="600" fill="#065f46"><?php echo esc_html__('🏛️ 五部门协同流水线', 'linked3'); ?></text>
                <!-- 五个部门节点 -->
                <g>
                    <rect x="70" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="130" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46"><?php echo esc_html__('FP 溯源', 'linked3'); ?></text>
                </g>
                <g>
                    <rect x="210" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="270" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46"><?php echo esc_html__('EX 变异', 'linked3'); ?></text>
                </g>
                <g>
                    <rect x="350" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="410" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46"><?php echo esc_html__('C 绞杀', 'linked3'); ?></text>
                </g>
                <g>
                    <rect x="490" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="550" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46"><?php echo esc_html__('O 降维', 'linked3'); ?></text>
                </g>
                <g>
                    <rect x="630" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="690" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46"><?php echo esc_html__('A 结晶', 'linked3'); ?></text>
                </g>
                <!-- 部门间箭头 -->
                <line x1="190" y1="142" x2="210" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>
                <line x1="330" y1="142" x2="350" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>
                <line x1="470" y1="142" x2="490" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>
                <line x1="610" y1="142" x2="630" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>

                <!-- 箭头: 部门→演化 -->
                <line x1="400" y1="160" x2="400" y2="185" stroke="#6b7280" stroke-width="2" marker-end="url(#arrowhead)"/>

                <!-- 第三层: 三代演化 -->
                <rect x="50" y="190" width="700" height="70" rx="8" fill="url(#evoGrad)" stroke="#ec4899" stroke-width="1.5"/>
                <text x="400" y="210" text-anchor="middle" font-size="13" font-weight="600" fill="#9f1239"><?php echo esc_html__('🔄 三代演化循环', 'linked3'); ?></text>
                <!-- G1 G2 G3 节点 -->
                <g>
                    <circle cx="180" cy="235" r="18" fill="#3b82f6" stroke="#fff" stroke-width="2"/>
                    <text x="180" y="240" text-anchor="middle" font-size="11" font-weight="700" fill="#fff">G1</text>
                    <text x="180" y="262" text-anchor="middle" font-size="9" fill="#374151"><?php echo esc_html__('初代涌现', 'linked3'); ?></text>
                </g>
                <g>
                    <circle cx="400" cy="235" r="18" fill="#8b5cf6" stroke="#fff" stroke-width="2"/>
                    <text x="400" y="240" text-anchor="middle" font-size="11" font-weight="700" fill="#fff">G2</text>
                    <text x="400" y="262" text-anchor="middle" font-size="9" fill="#374151"><?php echo esc_html__('重组变异', 'linked3'); ?></text>
                </g>
                <g>
                    <circle cx="620" cy="235" r="18" fill="#ec4899" stroke="#fff" stroke-width="2"/>
                    <text x="620" y="240" text-anchor="middle" font-size="11" font-weight="700" fill="#fff">G3</text>
                    <text x="620" y="262" text-anchor="middle" font-size="9" fill="#374151"><?php echo esc_html__('终极坍缩', 'linked3'); ?></text>
                </g>
                <!-- 演化箭头 -->
                <line x1="198" y1="235" x2="382" y2="235" stroke="#8b5cf6" stroke-width="2" marker-end="url(#arrowhead)"/>
                <line x1="418" y1="235" x2="602" y2="235" stroke="#ec4899" stroke-width="2" marker-end="url(#arrowhead)"/>
                <!-- MVP标记 -->
                <text x="680" y="240" font-size="10" font-weight="600" fill="#9f1239">→ MVP</text>
            </svg>
        </div>

        <!-- 杠杆体系可视化: 6大能力域 -->
        <div style="margin-top: 16px; background: #fafafa; border-radius: 8px; padding: 16px;">
            <div style="font-size: 12px; font-weight: 600; color: #1f2937; margin-bottom: 12px; text-align: center;"><?php echo esc_html__('🧠 元杠杆体系 (24基础+17复合=41个)', 'linked3'); ?></div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px;">
                <div style="background: #e0f2fe; padding: 10px; border-radius: 6px; border-left: 3px solid #0284c7;">
                    <div style="font-size: 11px; font-weight: 600; color: #0c4a6e;"><?php echo esc_html__('🔍 认知与元认知 (10)', 'linked3'); ?></div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;"><?php echo esc_html__('元认知/本质/注意力/折叠/直觉/递归...', 'linked3'); ?></div>
                </div>
                <div style="background: #fef3c7; padding: 10px; border-radius: 6px; border-left: 3px solid #d97706;">
                    <div style="font-size: 11px; font-weight: 600; color: #78350f;"><?php echo esc_html__('🧠 逻辑与推理 (7)', 'linked3'); ?></div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;"><?php echo esc_html__('逻辑/苏格拉底/质疑/反向/因果...', 'linked3'); ?></div>
                </div>
                <div style="background: #fce7f3; padding: 10px; border-radius: 6px; border-left: 3px solid #db2777;">
                    <div style="font-size: 11px; font-weight: 600; color: #831843;"><?php echo esc_html__('🎨 创造与突破 (7)', 'linked3'); ?></div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;"><?php echo esc_html__('创造/跨界/灵感/隐喻/范式/设计...', 'linked3'); ?></div>
                </div>
                <div style="background: #e0e7ff; padding: 10px; border-radius: 6px; border-left: 3px solid #4f46e5;">
                    <div style="font-size: 11px; font-weight: 600; color: #312e81;"><?php echo esc_html__('📊 分析与评估 (8)', 'linked3'); ?></div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;"><?php echo esc_html__('抽象/模式/评估/压力测试/系统...', 'linked3'); ?></div>
                </div>
                <div style="background: #dcfce7; padding: 10px; border-radius: 6px; border-left: 3px solid #16a34a;">
                    <div style="font-size: 11px; font-weight: 600; color: #14532d;"><?php echo esc_html__('🎯 战略与行动 (7)', 'linked3'); ?></div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;"><?php echo esc_html__('谋划/决策/落地/动态/博弈/伦理...', 'linked3'); ?></div>
                </div>
                <div style="background: #f3e8ff; padding: 10px; border-radius: 6px; border-left: 3px solid #9333ea;">
                    <div style="font-size: 11px; font-weight: 600; color: #581c87;"><?php echo esc_html__('💬 沟通与协作 (7)', 'linked3'); ?></div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;"><?php echo esc_html__('沟通/叙事/情绪/协作/说服力/语境', 'linked3'); ?></div>
                </div>
            </div>
            <!-- 复合杠杆条 -->
            <div style="margin-top: 10px; padding: 8px; background: linear-gradient(90deg, #fef3c7, #dbeafe, #dcfce7, #fce7f3, #e0e7ff, #f3e8ff); border-radius: 6px; text-align: center;">
                <div style="font-size: 11px; font-weight: 600; color: #1f2937;"><?php echo esc_html__('⚡ 复合杠杆 (17个高级编排能力)', 'linked3'); ?></div>
                <div style="font-size: 9px; color: #6b7280; margin-top: 2px;"><?php echo esc_html__('去AI味五部门 · 创世演化 · 深度谋划 · 跨界创新 · 苏格拉底审查 · 超级Prompt · 认知审计 · 知识综合 · 内容引擎 · 风险防御 · 代码优化器 · 创意引擎 · 意图解码器 · 质量关卡 · 种子重组器 · 通用三件套 · 写作深度', 'linked3'); ?></div>
            </div>
        </div>

        <!-- 原始三栏说明保留 -->
        <div style="margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <!-- 双公理 -->
            <div style="background: #f0f4ff; padding: 14px; border-radius: 8px;">
                <h3 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #1e40af;"><?php echo esc_html__('⚖️ 双公理系统', 'linked3'); ?></h3>
                <div style="font-size: 11px; color: #374151; margin-bottom: 6px;"><strong><?php echo esc_html__('公理一 · 信息熵减:', 'linked3'); ?></strong><?php echo esc_html__('操作后任务空间不确定性必须降低', 'linked3'); ?></div>
                <div style="font-size: 11px; color: #374151; margin-bottom: 8px;"><strong><?php echo esc_html__('公理二 · 系统降维:', 'linked3'); ?></strong><?php echo esc_html__('高维概念降维为可操作循环', 'linked3'); ?></div>
                <div style="font-size: 10px; color: #ef4444; background: #fef2f2; padding: 4px 6px; border-radius: 4px;"><?php echo esc_html__('⚠️ 公理刚性 · 证伪至死 · 任一违反即抹杀', 'linked3'); ?></div>
            </div>
            <!-- 五部门 -->
            <div style="background: #f0fdf4; padding: 14px; border-radius: 8px;">
                <h3 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #065f46;"><?php echo esc_html__('🏛️ 五部门协同', 'linked3'); ?></h3>
                <div style="font-size: 11px; color: #374151; line-height: 1.6;">
                    <strong>FP</strong><?php echo esc_html__('定义公理和信息核 →', 'linked3'); ?><strong>EX</strong><?php echo esc_html__('生成方案种群(10个) →', 'linked3'); ?><strong>C</strong> 绞杀弱者(风险><?php echo esc_html__('8或可行', 'linked3'); ?><4) → <strong>O</strong><?php echo esc_html__('检测盲区与幻觉 →', 'linked3'); ?><strong>A</strong> 结晶锁定MVP
                </div>
            </div>
            <!-- 三代演化 -->
            <div style="background: #fdf2f8; padding: 14px; border-radius: 8px;">
                <h3 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #9f1239;"><?php echo esc_html__('🔄 三代演化循环', 'linked3'); ?></h3>
                <div style="font-size: 11px; color: #374151; line-height: 1.6;">
                    <strong style="color: #3b82f6;">G1</strong> 初代涌现(<span id="cos-gen-g1-count"><?php echo esc_html((string) (($cos_overview['by_generation']['G1'] ?? 0))); ?></span>) → <strong style="color: #8b5cf6;">G2</strong> 重组变异(<span id="cos-gen-g2-count"><?php echo esc_html((string) (($cos_overview['by_generation']['G2'] ?? 0))); ?></span>) → <strong style="color: #ec4899;">G3</strong> 终极坍缩(<span id="cos-gen-g3-count"><?php echo esc_html((string) (($cos_overview['by_generation']['G3'] ?? 0))); ?></span>)
                </div>
                <div style="font-size: 10px; color: #6b7280; margin-top: 4px;"><?php echo esc_html__('每代结晶后物理归档, 作为下一代变异基线', 'linked3'); ?></div>
            </div>
        </div>
    </details>

</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-cognitive-os.js ?>

