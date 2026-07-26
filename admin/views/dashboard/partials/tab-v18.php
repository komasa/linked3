<?php
/**
 * 拆解OS Tab 面板 (v16.0.20)
 *
 * v18复审重构 [公理α: H↓] [公理β: dim↓]:
 *   - E1修复: 100天计划新增输入表单(职业/赛道/目标/平台/当前天数) → 定制化输出
 *   - I1/S2修复: 统一 renderV18Result() 渲染器, JSON→卡片化(保留JSON切换)
 *   - I2/I4修复: 模块总览增加"用途说明+使用入口"列
 *   - I5修复: 逆向拆解输入框补示例占位
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

$v18_nonce = wp_create_nonce('linked3_content_writer');
$v18_ajax_url = admin_url('admin-ajax.php');

// 获取V18模块状态
$v18_health = [];
if (class_exists('V18')) {
    $v18_health = V18::health_check();
}
$v18_info = [];
if (class_exists('V18')) {
    $v18_info = V18::get_facade_info();
}

// v18复审 I2/I4: 模块用途说明映射
$v18_module_usage = [
    'reverse' => ['用途' => __('逆向拆解AI作品/JSON，提取8维度DNA', 'linked3'), '入口' => __('下方"逆向拆解操作"面板', 'linked3')],
    'svg_stats' => ['用途' => __('统计SVG图示的原子级meta(矩形/路径/文本数等)', 'linked3'), '入口' => __('下方"SVG统计基线"面板', 'linked3')],
    'ruliu' => ['用途' => __('100天起号全流程追踪(看见→相信→承担→放大)', 'linked3'), '入口' => __('下方"入流四状态追踪"面板，输入职业/赛道生成定制计划', 'linked3')],
    'neng_suo' => ['用途' => __('能所结构约束AI生成方向(能知/所知/能所合一)', 'linked3'), '入口' => __('V18核心类，由逆向引擎自动调用', 'linked3')],
    'three_layer' => ['用途' => __('三层能观(纯粹/逻辑/时空)映射视觉频率HF/MF/LF', 'linked3'), '入口' => __('V18核心类，由图示引擎自动调用', 'linked3')],
    'neng_zhi' => ['用途' => __('能知三阶(时空/逻辑/纯粹)映射认知层级R/A/E', 'linked3'), '入口' => __('V18核心类，由内容引擎自动调用', 'linked3')],
    'hong_liu' => ['用途' => __('洪流公式(时代之势×人的能知×行动)工程化为出图飞轮', 'linked3'), '入口' => __('V18核心类，由生产管线自动调用', 'linked3')],
];
?>

<div class="wrap linked3-v18-wrap">
    <h2><?php echo esc_html__('🔮 拆解OS — 逆向思维×李善友方法论×SVG统计', 'linked3'); ?></h2>
    <p><?php echo esc_html__('V18子系统提供逆向拆解、能所结构、SVG统计、三层能观、入流追踪等10大核心能力。下方各面板均可直接操作，输入条件后点击按钮即可获得结果。', 'linked3'); ?></p>

    <!-- v16.0.12: V18功能生态概览 -->
    <div class="linked3-v18-ecosystem-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin:20px 0;">
        <h3 style="margin-top:0;color:#1B3A5C;"><?php echo esc_html__('🌐 V18功能生态', 'linked3'); ?></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;">
            <div style="padding:12px;background:#FAFAFA;border-radius:6px;">
                <h4 style="margin:0 0 8px;font-size:14px;"><?php echo esc_html__('🔍 逆向拆解引擎', 'linked3'); ?></h4>
                <p style="margin:0;font-size:12px;color:#71717A;"><?php echo esc_html__('输入AI返回的JSON或作品描述，8维度逆向拆解为可复用DNA', 'linked3'); ?></p>
            </div>
            <div style="padding:12px;background:#FAFAFA;border-radius:6px;">
                <h4 style="margin:0 0 8px;font-size:14px;"><?php echo esc_html__('📈 SVG统计基线', 'linked3'); ?></h4>
                <p style="margin:0;font-size:12px;color:#71717A;"><?php echo esc_html__('1297个SVG×39维meta统计，提供设计基线参考', 'linked3'); ?></p>
            </div>
            <div style="padding:12px;background:#FAFAFA;border-radius:6px;">
                <h4 style="margin:0 0 8px;font-size:14px;"><?php echo esc_html__('🌊 入流四状态追踪', 'linked3'); ?></h4>
                <p style="margin:0;font-size:12px;color:#71717A;"><?php echo esc_html__('看见→相信→承担→放大，输入职业/赛道生成定制100天计划', 'linked3'); ?></p>
            </div>
            <div style="padding:12px;background:#FAFAFA;border-radius:6px;">
                <h4 style="margin:0 0 8px;font-size:14px;"><?php echo esc_html__('🧠 三层能观/能知三阶', 'linked3'); ?></h4>
                <p style="margin:0;font-size:12px;color:#71717A;"><?php echo esc_html__('能知、能所、能指三层意识结构，约束AI生成方向', 'linked3'); ?></p>
            </div>
        </div>
    </div>

    <!-- v18复审 I2/I4: 模块状况总览(增加用途说明+使用入口) -->
    <div class="linked3-v18-health-card">
        <h3><?php echo esc_html__('📋 模块状况总览', 'linked3'); ?></h3>
        <p style="color:#71717A;font-size:13px;"><?php echo esc_html__('下表显示V18各子模块的加载状态与使用方式。未加载的模块需检查对应类文件是否完整。', 'linked3'); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:18%;"><?php echo esc_html__('模块', 'linked3'); ?></th>
                    <th style="width:10%;"><?php echo esc_html__('状态', 'linked3'); ?></th>
                    <th style="width:32%;"><?php echo esc_html__('用途说明', 'linked3'); ?></th>
                    <th style="width:25%;"><?php echo esc_html__('使用入口', 'linked3'); ?></th>
                    <th style="width:15%;"><?php echo esc_html__('版本', 'linked3'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $module_labels = [
                'reverse' => __('🔍 逆向拆解引擎', 'linked3'),
                'svg_stats' => __('📈 SVG统计基线', 'linked3'),
                'ruliu' => __('🌊 入流四状态追踪', 'linked3'),
                'neng_suo' => __('🧠 能所结构', 'linked3'),
                'three_layer' => __('👁️ 三层能观', 'linked3'),
                'neng_zhi' => __('🎓 能知三阶', 'linked3'),
                'hong_liu' => __('🌊 洪流飞轮', 'linked3'),
            ];
            if (!empty($v18_health)) :
                foreach ($v18_health as $mod_key => $mod_info) :
                    $label = $module_labels[$mod_key] ?? $mod_key;
                    $usage = $v18_module_usage[$mod_key] ?? ['用途' => '—', '入口' => '—'];
                    $loaded = !empty($mod_info['loaded']);
                    $version = $mod_info['version'] ?? '—';
            ?>
                <tr>
                    <td><strong><?php echo esc_html($label); ?></strong></td>
                    <td><?php echo $loaded ? '<span style="color:#16a34a;">✅ 已加载</span>' : '<span style="color:#DC2626;">❌ 未加载</span>'; ?></td>
                    <td style="font-size:12px;color:#52525B;"><?php echo esc_html($usage['用途']); ?></td>
                    <td style="font-size:12px;"><?php echo esc_html($usage['入口']); ?></td>
                    <td style="font-size:12px;color:#71717A;"><?php echo esc_html($version); ?></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="5"><?php echo esc_html__('V18模块信息不可用，请确认 V18 类已加载。', 'linked3'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 逆向拆解操作面板 -->
    <div class="linked3-v18-reverse-card">
        <h3><?php echo esc_html__('🔍 逆向拆解操作', 'linked3'); ?></h3>
        <p><?php echo esc_html__('输入AI返回的JSON或作品描述，选择工程师类型，进行8维度逆向拆解。', 'linked3'); ?></p>
        <textarea id="v18-reverse-input" rows="6" style="width:100%;font-family:monospace;font-size:12px;" placeholder="<?php echo esc_attr__('示例输入：&#10;{&quot;style&quot;:&quot;赛博朋克&quot;,&quot;color&quot;:&quot;#FF00FF&quot;,&quot;character&quot;:&quot;黑客，短发，黑色风衣&quot;}&#10;&#10;或粘贴一段作品描述文字，如：&#10;一张赛博朋克风格的插画，霓虹灯光下，一个穿黑色风衣的黑客站在雨夜的街道上...', 'linked3'); ?>"></textarea>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <select id="v18-reverse-type" style="min-width:180px;">
                <option value="visual"><?php echo esc_html__('视觉系统逆向', 'linked3'); ?></option>
                <option value="brand"><?php echo esc_html__('品牌六要素逆向', 'linked3'); ?></option>
                <option value="motion"><?php echo esc_html__('Motion动态逆向', 'linked3'); ?></option>
                <option value="text"><?php echo esc_html__('文本创作逆向', 'linked3'); ?></option>
            </select>
            <button type="button" class="button button-primary" id="v18-reverse-btn"><?php echo esc_html__('🔍 开始逆向拆解', 'linked3'); ?></button>
            <label style="font-size:12px;color:#71717A;"><input type="checkbox" id="v18-reverse-view-json"><?php echo esc_html__('显示原始JSON', 'linked3'); ?></label>
        </div>
        <div id="v18-reverse-result" style="margin-top:15px;"></div>
    </div>

    <!-- SVG统计面板 -->
    <div class="linked3-v18-svg-card">
        <h3><?php echo esc_html__('📈 SVG统计基线', 'linked3'); ?></h3>
        <p><?php echo esc_html__('获取1297个SVG的原子级meta统计基线（矩形/路径/文本/节点/颜色/渐变/滤镜等39维）。', 'linked3'); ?></p>
        <button type="button" class="button" id="v18-svg-stats-btn"><?php echo esc_html__('📊 获取统计基线', 'linked3'); ?></button>
        <label style="margin-left:10px;font-size:12px;color:#71717A;"><input type="checkbox" id="v18-svg-view-json"><?php echo esc_html__('显示原始JSON', 'linked3'); ?></label>
        <div id="v18-svg-stats-result" style="margin-top:15px;"></div>
    </div>

    <!-- v18复审 E1/S1: 入流追踪面板(新增输入表单) -->
    <div class="linked3-v18-ruliu-card">
        <h3><?php echo esc_html__('🌊 入流四状态追踪 — 定制化100天计划', 'linked3'); ?></h3>
        <p><?php echo esc_html__('输入你的职业、赛道和起号目标，生成专属100天起号计划（看见→相信→承担→放大四阶段，含每周排期）。', 'linked3'); ?></p>

        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px;margin-bottom:14px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#18181B;display:block;margin-bottom:4px;"><?php echo esc_html__('👤 你的职业', 'linked3'); ?></label>
                    <input type="text" id="v18-ruliu-profession" class="regular-text" placeholder="<?php echo esc_attr__('如：律师/设计师/程序员', 'linked3'); ?>" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#18181B;display:block;margin-bottom:4px;"><?php echo esc_html__('🎯 内容赛道', 'linked3'); ?></label>
                    <input type="text" id="v18-ruliu-track" class="regular-text" placeholder="<?php echo esc_attr__('如：法律科普/UI教程/Python入门', 'linked3'); ?>" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#18181B;display:block;margin-bottom:4px;"><?php echo esc_html__('🏆 起号目标', 'linked3'); ?></label>
                    <input type="text" id="v18-ruliu-goal" class="regular-text" placeholder="<?php echo esc_attr__('如：100天1万粉/月入5000', 'linked3'); ?>" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#18181B;display:block;margin-bottom:4px;"><?php echo esc_html__('📱 主平台', 'linked3'); ?></label>
                    <select id="v18-ruliu-platform" style="width:100%;">
                        <option value="<?php echo esc_attr__('公众号', 'linked3'); ?>">公众号</option>
                        <option value="<?php echo esc_attr__('小红书', 'linked3'); ?>">小红书</option>
                        <option value="<?php echo esc_attr__('抖音', 'linked3'); ?>">抖音</option>
                        <option value="<?php echo esc_attr__('B站', 'linked3'); ?>">B站</option>
                        <option value="<?php echo esc_attr__('知乎', 'linked3'); ?>">知乎</option>
                        <option value="<?php echo esc_attr__('视频号', 'linked3'); ?>">视频号</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#18181B;display:block;margin-bottom:4px;"><?php echo esc_html__('📅 当前第几天', 'linked3'); ?></label>
                    <input type="number" id="v18-ruliu-day" min="1" max="100" value="1" style="width:100%;">
                </div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <button type="button" class="button button-primary" id="v18-ruliu-btn"><?php echo esc_html__('🌊 生成我的100天计划', 'linked3'); ?></button>
                <label style="font-size:12px;color:#71717A;"><input type="checkbox" id="v18-ruliu-view-json"><?php echo esc_html__('显示原始JSON', 'linked3'); ?></label>
                <span style="font-size:11px;color:#A1A1AA;"><?php echo esc_html__('💡 至少填写职业或赛道，计划会据此个性化', 'linked3'); ?></span>
            </div>
        </div>

        <div id="v18-ruliu-result" style="margin-top:15px;"></div>

        <!-- v18复审: 进度更新区 (功能化: 不只生成计划, 还能更新当前天数) -->
        <div style="margin-top:16px;padding-top:14px;border-top:1px dashed #E4E4E7;">
            <h4 style="margin:0 0 8px;font-size:13px;color:#18181B;"><?php echo esc_html__('📅 更新我的进度', 'linked3'); ?></h4>
            <p style="font-size:12px;color:#71717A;margin:0 0 8px;"><?php echo esc_html__('起号过程中，随时更新当前天数，系统会告诉你处于哪个阶段、下一步该做什么。', 'linked3'); ?></p>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <label style="font-size:12px;color:#52525B;"><?php echo esc_html__('当前第', 'linked3'); ?></label>
                <input type="number" id="v18-ruliu-update-day" min="1" max="100" value="1" style="width:70px;">
                <label style="font-size:12px;color:#52525B;"><?php echo esc_html__('天', 'linked3'); ?></label>
                <button type="button" class="button" id="v18-ruliu-update-btn"><?php echo esc_html__('更新进度', 'linked3'); ?></button>
                <button type="button" class="button" id="v18-ruliu-status-btn"><?php echo esc_html__('查看当前状态', 'linked3'); ?></button>
            </div>
            <div id="v18-ruliu-status-result" style="margin-top:10px;"></div>
        </div>
    </div>

    <!-- v18复审: 三层能观功能化面板 (输入内容模块→输出HF/MF/LF频率标注) -->
    <div class="linked3-v18-consciousness-card">
        <h3><?php echo esc_html__('🧠 三层能观 — 视觉频率标注', 'linked3'); ?></h3>
        <p><?php echo esc_html__('输入内容模块类型或描述，系统分配 [HF]高频/[MF]中频/[LF]低频 频率标注，并校验全图分布是否递进。', 'linked3'); ?></p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;align-items:center;">
            <select id="v18-consciousness-module-type" style="min-width:180px;">
                <option value="insight"><?php echo esc_html__('洞察/结论 (建议HF)', 'linked3'); ?></option>
                <option value="golden_quote"><?php echo esc_html__('金句 (建议HF)', 'linked3'); ?></option>
                <option value="method"><?php echo esc_html__('方法论/框架 (建议MF)', 'linked3'); ?></option>
                <option value="steps"><?php echo esc_html__('步骤/流程 (建议MF)', 'linked3'); ?></option>
                <option value="data"><?php echo esc_html__('数据/事实 (建议LF)', 'linked3'); ?></option>
                <option value="details"><?php echo esc_html__('细节/背景 (建议LF)', 'linked3'); ?></option>
            </select>
            <input type="text" id="v18-consciousness-content" class="regular-text" placeholder="<?php echo esc_attr__('模块内容描述（可选，用于辅助判断）', 'linked3'); ?>" style="flex:1;min-width:200px;">
            <button type="button" class="button button-primary" id="v18-consciousness-assign-btn"><?php echo esc_html__('分配频率', 'linked3'); ?></button>
            <label style="font-size:12px;color:#71717A;"><input type="checkbox" id="v18-consciousness-view-json"><?php echo esc_html__('显示原始JSON', 'linked3'); ?></label>
        </div>
        <div id="v18-consciousness-result" style="margin-top:12px;"></div>
    </div>

    <!-- v18复审: 能知三阶功能化面板 (输入内容→自动检测认知层级R/A/E) -->
    <div class="linked3-v18-nengzhi-card">
        <h3><?php echo esc_html__('🧠 能知三阶 — 认知层级检测', 'linked3'); ?></h3>
        <p><?php echo esc_html__('输入你的内容文本，系统自动检测属于一阶(入门R)/二阶(进阶A)/三阶(专家E)，并给出内容适配建议。', 'linked3'); ?></p>
        <div style="margin-bottom:10px;">
            <textarea id="v18-nengzhi-content" rows="4" style="width:100%;font-size:13px;" placeholder="<?php echo esc_attr__('粘贴一段你的内容文本，系统会检测它适合哪类读者...&#10;&#10;例如：离婚时这三笔钱一定要分清楚：1. 婚前存款 2. 婚后工资 3. 房产增值', 'linked3'); ?>"></textarea>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <button type="button" class="button button-primary" id="v18-nengzhi-detect-btn"><?php echo esc_html__('检测认知层级', 'linked3'); ?></button>
            <button type="button" class="button" id="v18-nengzhi-stages-btn"><?php echo esc_html__('查看三阶说明', 'linked3'); ?></button>
            <label style="font-size:12px;color:#71717A;"><input type="checkbox" id="v18-nengzhi-view-json"><?php echo esc_html__('显示原始JSON', 'linked3'); ?></label>
        </div>
        <div id="v18-nengzhi-result" style="margin-top:12px;"></div>
    </div>

    <div class="linked3-v18-facade-card">
        <h3><?php echo esc_html__('🔧 V18集成信息', 'linked3'); ?></h3>
        <pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;font-size:12px;"><?php echo esc_html(wp_json_encode($v18_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-v18.js ?>

