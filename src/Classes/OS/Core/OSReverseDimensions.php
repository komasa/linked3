<?php

declare(strict_types=1);
/**
 * Linked3 Reverse Dimensions Registry v12.0.0
 *
 * 逆向8维度通用框架 + 专属增量维度注册表
 *
 * 来源: V18 道篇2.3「逆向的逆向」+ 术篇8.2「逆向8维度通用框架」
 *
 * 8维度通用框架 (所有逆向工程师共用):
 *   D1_整体风格 / D2_角色DNA / D3_色彩系统 / D4_构图镜头
 *   D5_文字元素 / D6_场景背景 / D7_文化符号 / D8_META标签
 *
 * 专属增量维度 (按逆向对象类型不同):
 *   视觉类13种 / 音视频类5种 / 品牌六要素6种 / 工程5种 / 4Band4种
 *   方法论5种 / 三层架构8种 / Motion7种 / 操作符8种 / 产品商业4种
 *   文本创作8种(T1-T8) / 质量5种
 *
 * @package Linked3\Reverse
 * @since 12.0.0
 * @version 12.0.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Reverse Dimensions (逆向维度)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/class-linked3-reverse-dimensions.php
 * Original class: Linked3_Reverse_Dimensions
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSReverseDimensions {

    /**
     * 8维度通用框架定义
     * 来源: V18 术篇8.2
     */
    const UNIVERSAL_DIMENSIONS = [
        'D1' => [
            'key' => 'D1',
            'name' => '整体风格',
            'name_en' => 'overall_style',
            'fields' => '画风流派/线条特征/渲染方式/质感/氛围情绪(5-8个关键词)',
            'maps_to' => 'META.Mood+Style',
        ],
        'D2' => [
            'key' => 'D2',
            'name' => '角色DNA',
            'name_en' => 'character_dna',
            'fields' => '性别年龄/发型发色(hex)/面部特征/体型/服装(颜色hex)/配饰道具/体态手势/表情/特殊标记',
            'maps_to' => 'CharacterSeed',
        ],
        'D3' => [
            'key' => 'D3',
            'name' => '色彩系统',
            'name_en' => 'color_system',
            'fields' => '主色(hex+占比+用途)/辅色/点缀色/背景色/光影类型/阴影深度',
            'maps_to' => 'META.Color',
        ],
        'D4' => [
            'key' => 'D4',
            'name' => '构图镜头',
            'name_en' => 'composition_camera',
            'fields' => '景别(远全中近特)/视角(平仰俯鸟瞰)/构图法/分镜格子数/格子排列',
            'maps_to' => 'Script.Layout',
        ],
        'D5' => [
            'key' => 'D5',
            'name' => '文字元素',
            'name_en' => 'text_elements',
            'fields' => '标题字体/标题颜色(hex)/对白气泡/旁白框/拟声词',
            'maps_to' => 'Script.Dialogue',
        ],
        'D6' => [
            'key' => 'D6',
            'name' => '场景背景',
            'name_en' => 'scene_background',
            'fields' => '地点/建筑元素/自然元素/道具/氛围元素(雾雨光烟)/时间/天气',
            'maps_to' => 'SceneSeed',
        ],
        'D7' => [
            'key' => 'D7',
            'name' => '文化符号',
            'name_en' => 'cultural_symbols',
            'fields' => '宗教/民俗/品牌符号/图标意象/传统纹样/神秘符号',
            'maps_to' => 'Culture+Symbol',
        ],
        'D8' => [
            'key' => 'D8',
            'name' => 'META标签',
            'name_en' => 'meta_tags',
            'fields' => '15-20个逗号分隔的可复用DNA关键词，用于AI生图prompt',
            'maps_to' => 'InfoSeed.ChartDNA',
        ],
    ];

    /**
     * 专属增量维度定义 (按类型)
     * 来源: V18 附录A.1
     */
    const PROPRIETARY_DIMENSIONS = [
        // 视觉类 (13种)
        'visual_original_quality' => ['label' => '原画质量', 'fields' => '作画精度/线条质量/色彩层次/构图完成度'],
        'visual_camera_lens' => ['label' => '机身镜头', 'fields' => '相机型号/焦距/光圈/ISO/快门'],
        'visual_lighting_setup' => ['label' => '布光方案', 'fields' => '主光/辅光/轮廓光/背景光/光比'],
        'visual_skin_detail' => ['label' => '肤质细节', 'fields' => '皮肤纹理/毛孔/油光/瑕疵'],
        'visual_post_processing' => ['label' => '后期处理', 'fields' => '调色/磨皮/合成/特效'],
        'visual_garment_structure' => ['label' => '款式结构', 'fields' => '款式/版型/剪裁/结构'],
        'visual_fabric_material' => ['label' => '面料材质', 'fields' => '面料/纹理/垂感/光泽'],
        'visual_color_matching' => ['label' => '色彩搭配', 'fields' => '主辅点缀/对比/调和/节奏'],
        'visual_detail_craft' => ['label' => '细节工艺', 'fields' => '缝制/装饰/五金/辅料'],
        'visual_chart_type' => ['label' => '图表类型', 'fields' => '柱状/折线/饼图/散点/雷达'],
        'visual_data_mapping' => ['label' => '数据映射', 'fields' => '维度映射/颜色编码/大小编码'],
        'visual_interaction_design' => ['label' => '交互设计', 'fields' => '悬停/点击/筛选/联动'],
        'visual_visual_encoding' => ['label' => '视觉编码', 'fields' => '位置/长度/角度/面积/颜色'],

        // 音视频类 (5种)
        'av_audio_spectrum' => ['label' => '音频频谱', 'fields' => '频段分布/动态范围/信噪比'],
        'av_rhythm_bpm' => ['label' => '节奏BPM', 'fields' => 'BPM值/节拍类型/节奏型'],
        'av_dynamic_range' => ['label' => '动态范围', 'fields' => '最大电平/最小电平/动态压缩'],
        'av_spatial_sense' => ['label' => '空间感', 'fields' => '声场宽度/深度/高度/混响'],
        'av_melody_harmony' => ['label' => '旋律和声', 'fields' => '旋律线/和声进行/编曲层次/混音平衡/曲式结构'],

        // 品牌六要素 (6种)
        'brand_brand' => ['label' => 'Brand', 'fields' => '品牌名/Logo描述/字体风格/品牌人格/品牌Slogan'],
        'brand_signature' => ['label' => 'Signature', 'fields' => '视觉签名描述/构图特征/标志性元素/识别度关键词'],
        'brand_color' => ['label' => 'Color', 'fields' => '主色hex+占比/辅色/点缀色/中性色/色彩情绪映射'],
        'brand_mood' => ['label' => 'Mood', 'fields' => '主Mood/副Mood/情绪配比'],
        'brand_culture' => ['label' => 'Culture', 'fields' => '目标文化圈/年龄层/价值观'],
        'brand_platform' => ['label' => 'Platform', 'fields' => '平台尺寸/密度/产品类型'],

        // 工程类 (5种)
        'eng_architecture_pattern' => ['label' => '架构模式', 'fields' => '分层/微服务/事件驱动/插件化'],
        'eng_data_flow' => ['label' => '数据流', 'fields' => '输入→处理→输出→存储'],
        'eng_extension_points' => ['label' => '扩展点', 'fields' => '插件接口/Hook/事件'],
        'eng_performance_strategy' => ['label' => '性能策略', 'fields' => '缓存/限流/异步/批处理'],
        'eng_error_handling' => ['label' => '错误处理', 'fields' => '异常捕获/降级/重试/熔断'],

        // 4Band结构 (4种)
        'fourband_hook' => ['label' => 'Hook', 'fields' => '开头钩子/痛点/悬念/反差/3秒抓住注意力'],
        'fourband_body' => ['label' => 'Body', 'fields' => '正文展开/故事/数据/案例/价值传递'],
        'fourband_proof' => ['label' => 'Proof', 'fields' => '信任背书/数据/引用/对比/消除疑虑'],
        'fourband_cta' => ['label' => 'CTA', 'fields' => '行动号召/引导互动/转化/明确下一步'],

        // 方法论类 (5种)
        'method_core_premise' => ['label' => '核心前提', 'fields' => '方法论成立的根本假设'],
        'method_axiom_list' => ['label' => '公理清单', 'fields' => '不可证明但自明的命题列表'],
        'method_first_principle' => ['label' => '第一性原则', 'fields' => '从零推导的根本原则'],
        'method_execution_flow' => ['label' => '执行流程', 'fields' => '从输入到输出的完整步骤'],
        'method_quality_gate' => ['label' => '质量门禁', 'fields' => '每个阶段的通过标准'],

        // 三层架构 (8种)
        'three_layer_meta' => ['label' => 'META视觉定义层', 'fields' => '6要素锚点:Brand/Signature/Color/Mood/Culture/Platform'],
        'three_layer_script' => ['label' => '剧本控制层', 'fields' => '5维度:Arc/Dialogue/EmotionMap/Transition/Pacing'],
        'three_layer_validation' => ['label' => '验证校验层', 'fields' => '4维一致性:角色/场景/光色/风格'],
        'three_layer_compiler' => ['label' => '三层编译器', 'fields' => 'META+Script+Validation→Prompt'],
        'three_layer_compressor' => ['label' => 'Prompt压缩器', 'fields' => '≤4500字符压缩'],
        'three_layer_keyword' => ['label' => '关键词提炼', 'fields' => '5法+四字黄金'],
        'three_layer_fit_check' => ['label' => '图文咬合校验', 'fields' => '量化校验'],
        'three_layer_loop' => ['label' => 'Loop迭代法', 'fields' => '7步闭环/8种断裂模式'],

        // Motion (7种)
        'motion_image_seen' => ['label' => '图片已见原则', 'fields' => 'AI能看到输入图,只描述变化'],
        'motion_concise' => ['label' => '简洁优先', 'fields' => '50-200字符,聚焦2-3核心元素'],
        'motion_concrete_action' => ['label' => '具体动作原则', 'fields' => '用物理动作,避免抽象'],
        'motion_movement_limit' => ['label' => '运动限制', 'fields' => '镜头≤2种,主体≤2种'],
        'motion_camera_lib' => ['label' => '镜头运动库', 'fields' => 'push_in/pull_out/pan/tilt/orbit/tracking/zoom/static'],
        'motion_subject_lib' => ['label' => '主体动作库', 'fields' => '转头/对视/走路/伸手/起身/微笑/流泪等17种'],
        'motion_beat_derive' => ['label' => 'beat_text动态推导', 'fields' => '动作关键词→walking/场景关键词→push_in/速度关键词→fast/氛围关键词→tense'],

        // 操作符 (8种)
        'operator_shot' => ['label' => '景别操作符', 'fields' => 'extreme_wide/wide/medium_wide/medium/medium_close/close_up/extreme_close_up/macro'],
        'operator_angle' => ['label' => '视角操作符', 'fields' => 'eye_level/low_angle/high_angle/bird_eye/dutch_angle/worm_eye'],
        'operator_light' => ['label' => '光影操作符', 'fields' => 'natural/side_light/back_light/rim_light_blue/rim_light_warm/hard_shadow/soft_diffused/volumetric/practical_warm/neon_glow'],
        'operator_mood' => ['label' => '情绪操作符', 'fields' => 'calm/tense/melancholy/rage/mysterious/hopeful/desperate/serene/horror/epic'],
        'operator_composition' => ['label' => '构图操作符', 'fields' => 'rule_of_thirds/diagonal/center/symmetric/leading_lines/golden_ratio/frame_within_frame'],
        'operator_focus' => ['label' => '焦点操作符', 'fields' => 'subject_face/subject_eyes/subject_hands/environment/silhouette/detail_prop'],
        'operator_particle' => ['label' => '粒子操作符', 'fields' => 'rain/snow/dust/embers/petals/fog_wisps/none'],
        'operator_time' => ['label' => '时间操作符', 'fields' => 'dawn/morning/noon/afternoon/dusk/night/midnight/void'],

        // 产品商业 (4种)
        'product_value_proposition' => ['label' => '价值主张', 'fields' => '解决什么问题/创造什么价值'],
        'product_revenue_model' => ['label' => '收入模型', 'fields' => '订阅/交易/广告/佣金/SaaS'],
        'product_growth_engine' => ['label' => '增长引擎', 'fields' => '病毒式/付费式/粘性式/网络效应'],
        'product_competitive_moat' => ['label' => '竞争壁垒', 'fields' => '技术/品牌/网络效应/规模/转换成本'],

        // 文本创作 (8种 T1-T8)
        'text_t1_theme' => ['label' => 'T1_题材', 'fields' => '题材类型/领域/目标读者'],
        'text_t2_structure' => ['label' => 'T2_结构', 'fields' => '叙事结构/章节划分/逻辑链'],
        'text_t3_character' => ['label' => 'T3_角色', 'fields' => '主角/配角/反派/角色弧线'],
        'text_t4_language' => ['label' => 'T4_语言', 'fields' => '语言风格/语调/修辞/节奏'],
        'text_t5_pacing' => ['label' => 'T5_节奏', 'fields' => '快慢/张弛/信息密度'],
        'text_t6_hook' => ['label' => 'T6_爽点', 'fields' => '痛点解决/利益点/情感共鸣'],
        'text_t7_foreshadow' => ['label' => 'T7_伏笔', 'fields' => '埋伏/呼应/反转'],
        'text_t8_quality' => ['label' => 'T8_质量', 'fields' => '转化路径/可测试性/合规性'],

        // 质量门禁 (5种)
        'quality_core_premise' => ['label' => '核心前提', 'fields' => '质量体系成立的根本假设'],
        'quality_axiom_list' => ['label' => '公理清单', 'fields' => '质量公理列表'],
        'quality_first_principle' => ['label' => '第一性原则', 'fields' => '质量第一性原则'],
        'quality_execution_flow' => ['label' => '执行流程', 'fields' => '质量保证流程'],
        'quality_quality_gate' => ['label' => '质量门禁', 'fields' => '质量门禁标准'],
    ];

    /**
     * 逆向工程师类型 → 专属维度映射
     * 来源: V18 附录A.1
     */
    const ENGINEER_TYPE_MAP = [
        'visual_system' => ['visual_original_quality', 'visual_camera_lens', 'visual_lighting_setup', 'visual_skin_detail', 'visual_post_processing'],
        'visual_garment' => ['visual_garment_structure', 'visual_fabric_material', 'visual_color_matching', 'visual_detail_craft'],
        'visual_chart' => ['visual_chart_type', 'visual_data_mapping', 'visual_interaction_design', 'visual_visual_encoding'],
        'av_music' => ['av_audio_spectrum', 'av_rhythm_bpm', 'av_dynamic_range', 'av_spatial_sense', 'av_melody_harmony'],
        'av_video' => ['av_audio_spectrum', 'av_rhythm_bpm', 'av_dynamic_range', 'av_spatial_sense'],
        'brand_six_elements' => ['brand_brand', 'brand_signature', 'brand_color', 'brand_mood', 'brand_culture', 'brand_platform'],
        'engineering' => ['eng_architecture_pattern', 'eng_data_flow', 'eng_extension_points', 'eng_performance_strategy', 'eng_error_handling'],
        'fourband' => ['fourband_hook', 'fourband_body', 'fourband_proof', 'fourband_cta'],
        'methodology' => ['method_core_premise', 'method_axiom_list', 'method_first_principle', 'method_execution_flow', 'method_quality_gate'],
        'three_layer_arch' => ['three_layer_meta', 'three_layer_script', 'three_layer_validation', 'three_layer_compiler', 'three_layer_compressor', 'three_layer_keyword', 'three_layer_fit_check', 'three_layer_loop'],
        'motion_prompt' => ['motion_image_seen', 'motion_concise', 'motion_concrete_action', 'motion_movement_limit', 'motion_camera_lib', 'motion_subject_lib', 'motion_beat_derive'],
        'operator' => ['operator_shot', 'operator_angle', 'operator_light', 'operator_mood', 'operator_composition', 'operator_focus', 'operator_particle', 'operator_time'],
        'product_business' => ['product_value_proposition', 'product_revenue_model', 'product_growth_engine', 'product_competitive_moat'],
        'text_creation' => ['text_t1_theme', 'text_t2_structure', 'text_t3_character', 'text_t4_language', 'text_t5_pacing', 'text_t6_hook', 'text_t7_foreshadow', 'text_t8_quality'],
        'quality_gate' => ['quality_core_premise', 'quality_axiom_list', 'quality_first_principle', 'quality_execution_flow', 'quality_quality_gate'],
    ];

    /**
     * 获取8维度通用框架
     */
    public static function get_universal_dimensions(): array {
        return self::UNIVERSAL_DIMENSIONS;
    }

    /**
     * 获取专属维度
     */
    public static function get_proprietary_dimensions(): array {
        return self::PROPRIETARY_DIMENSIONS;
    }

    /**
     * 按工程师类型获取专属维度
     */
    public static function get_dimensions_by_type(string $engineer_type): array {
        $keys = self::ENGINEER_TYPE_MAP[$engineer_type] ?? [];
        $result = [];
        foreach ($keys as $key) {
            if (isset(self::PROPRIETARY_DIMENSIONS[$key])) {
                $result[$key] = self::PROPRIETARY_DIMENSIONS[$key];
            }
        }
        return $result;
    }

    /**
     * 获取所有工程师类型
     */
    public static function get_engineer_types(): array {
        return array_keys(self::ENGINEER_TYPE_MAP);
    }

    /**
     * 构建完整的逆向Prompt
     * 来源: V18 术篇8.2 逆向8维度通用框架
     */
    public static function build_reverse_prompt(string $engineer_type, string $target_description): string {
        $universal = self::get_universal_dimensions();
        $proprietary = self::get_dimensions_by_type($engineer_type);

        $prompt = "你是专业{$engineer_type}逆向工程师。请对以下对象进行深度拆解。\n\n";
        $prompt .= "【目标对象】\n{$target_description}\n\n";
        $prompt .= "【8维度通用框架定义】\n\n";

        foreach ($universal as $dim) {
            $prompt .= "{$dim['key']}_{$dim['name']}：{$dim['fields']}\n\n";
        }

        if (!empty($proprietary)) {
            $prompt .= "【专属增量维度】\n\n";
            foreach ($proprietary as $dim) {
                $prompt .= "{$dim['label']}({$dim['fields']})\n\n";
            }
        }

        $prompt .= "输出纯JSON，所有字段必填。直接输出JSON，不要markdown代码块，不要任何解释。";

        return $prompt;
    }

    /**
     * 获取维度映射关系 (逆向维度 → 正向字段)
     */
    public static function get_dimension_mapping(): array {
        $mapping = [];
        foreach (self::UNIVERSAL_DIMENSIONS as $dim) {
            $mapping[$dim['key']] = [
                'reverse_name' => $dim['name'],
                'forward_field' => $dim['maps_to'],
            ];
        }
        return $mapping;
    }
}
