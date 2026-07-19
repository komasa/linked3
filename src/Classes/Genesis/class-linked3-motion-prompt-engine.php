<?php
/**
 * Linked3 Motion Prompt Engine v10.1.0 — 视频动态提示词引擎
 *
 * 吸取 feicai4.0 Motion Prompt Methodology 精华:
 *   - 图片已见原则: AI能"看到"输入图, 只描述"变化"
 *   - 简洁优先原则: 50-200字符, 聚焦2-3个核心元素
 *   - 具体动作原则: 用物理动作, 避免抽象概念
 *   - 运动限制原则: 镜头运动≤2种, 主体运动≤2种
 *
 * 智谱清言视频生成适配:
 *   - 首尾帧模式: 2张图 + 1个Motion Prompt → 5-10秒视频
 *   - 多段模式: N组(首帧+尾帧+Motion) → 中短视频
 *   - SEED连续性: 角色SEED确保跨帧一致
 *
 * @package Linked3\Genesis
 * @version 10.1.0
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_Motion_Prompt_Engine {

    /** @var array 镜头运动词汇库 (吸取feicai4.0) */
    const CAMERA_MOVEMENTS = [
        'push_in'   => ['label' => '推近', 'en' => 'dolly in / push in', 'mood' => '聚焦/紧张/亲密'],
        'pull_out'  => ['label' => '拉远', 'en' => 'dolly out / pull back', 'mood' => '揭示/疏离/结束'],
        'pan'       => ['label' => '横摇', 'en' => 'pan left / pan right', 'mood' => '扫视/展示'],
        'tilt'      => ['label' => '纵摇', 'en' => 'tilt up / tilt down', 'mood' => '仰视/俯视'],
        'orbit'     => ['label' => '环绕', 'en' => 'orbit around', 'mood' => '立体/动感'],
        'tracking'  => ['label' => '跟随', 'en' => 'tracking shot', 'mood' => '跟随/沉浸'],
        'zoom'      => ['label' => '变焦', 'en' => 'zoom in / zoom out', 'mood' => '心理冲击'],
        'static'    => ['label' => '静止', 'en' => 'locked camera', 'mood' => '稳定/观察'],
    ];

    /** @var array 主体动作词汇库 */
    const SUBJECT_ACTIONS = [
        'head_turn'     => ['label' => '转头', 'en' => 'turns head toward {target}'],
        'eye_contact'   => ['label' => '对视', 'en' => 'makes eye contact with camera'],
        'stand_up'      => ['label' => '站起', 'en' => 'stands up slowly'],
        'walk_forward'  => ['label' => '走向', 'en' => 'walks forward toward {target}'],
        'reach_hand'    => ['label' => '伸手', 'en' => 'reaches out hand'],
        'hand_raise'    => ['label' => '举手', 'en' => 'raises hand'],
        'lean_forward'  => ['label' => '前倾', 'en' => 'leans forward'],
        'smile'         => ['label' => '微笑', 'en' => 'smiles gently'],
        'look_away'     => ['label' => '移开视线', 'en' => 'looks away'],
        'breathe'       => ['label' => '呼吸', 'en' => 'shoulders rise and fall with breathing'],
    ];

    /** @var array 环境动态词汇库 */
    const ENVIRONMENT_DYNAMICS = [
        'hair_wind'     => ['label' => '发随风动', 'en' => 'hair flowing in wind'],
        'clothes_rustle'=> ['label' => '衣飘', 'en' => 'clothes rustling'],
        'water_splash'  => ['label' => '水花', 'en' => 'water splashing'],
        'leaves_fall'   => ['label' => '落叶', 'en' => 'leaves falling'],
        'smoke_rise'    => ['label' => '烟升', 'en' => 'smoke rising'],
        'dust_float'    => ['label' => '尘埃', 'en' => 'dust particles floating'],
        'light_flicker' => ['label' => '光闪', 'en' => 'light flickering'],
        'cloud_drift'   => ['label' => '云飘', 'en' => 'clouds drifting'],
    ];

    /** @var array 速度修饰词 */
    const SPEED_MODIFIERS = [
        'slow'      => ['label' => '缓慢', 'en' => 'slowly, gently, gradually'],
        'medium'    => ['label' => '中等', 'en' => 'smoothly, steadily, naturally'],
        'fast'      => ['label' => '快速', 'en' => 'quickly, rapidly, suddenly'],
        'dramatic'  => ['label' => '戏剧', 'en' => 'dramatically, boldly, powerfully'],
    ];

    /** @var array 氛围风格词汇 */
    const ATMOSPHERE_STYLES = [
        'cinematic' => ['label' => '电影感', 'en' => 'cinematic, filmic, 24fps'],
        'dreamy'    => ['label' => '梦幻', 'en' => 'dreamy, ethereal, soft focus'],
        'tense'     => ['label' => '紧张', 'en' => 'tense, suspenseful, dramatic'],
        'warm'      => ['label' => '温暖', 'en' => 'warm, golden hour, nostalgic'],
        'cold'      => ['label' => '冷峻', 'en' => 'cold, blue tones, stark'],
        'epic'      => ['label' => '史诗', 'en' => 'epic, grand, sweeping'],
    ];

    /**
     * 生成Motion Prompt (核心方法)
     *
     * @param array $params {
     *   camera_movement: string  镜头运动key
     *   subject_action:  string  主体动作key
     *   environment:     string  环境动态key (可选)
     *   speed:           string  速度修饰key
     *   atmosphere:      string  氛围key
     *   target:          string  动作目标 (如door/camera/window)
     * }
     * @return string Motion Prompt (50-200字符)
     */
    public static function generate(array $params): string {
        $camera = self::CAMERA_MOVEMENTS[$params['camera_movement'] ?? 'push_in']['en'] ?? 'dolly in';
        $action = self::SUBJECT_ACTIONS[$params['subject_action'] ?? 'head_turn']['en'] ?? 'turns head';
        $target = $params['target'] ?? 'camera';
        $action = str_replace('{target}', $target, $action);

        $speed = self::SPEED_MODIFIERS[$params['speed'] ?? 'slow']['en'] ?? 'slowly';
        $atmo = self::ATMOSPHERE_STYLES[$params['atmosphere'] ?? 'cinematic']['en'] ?? 'cinematic';

        $parts = [];
        // 镜头运动 (带速度)
        $parts[] = ucfirst($speed) . ' ' . $camera;
        // 主体动作
        $parts[] = 'subject ' . $action;
        // 环境动态 (可选)
        if (!empty($params['environment'])) {
            $env = self::ENVIRONMENT_DYNAMICS[$params['environment']]['en'] ?? '';
            if ($env) $parts[] = $env;
        }
        // 氛围
        $parts[] = $atmo;

        $prompt = implode(', ', $parts);

        // 限制200字符
        if (strlen($prompt) > 200) {
            $prompt = substr($prompt, 0, 197) . '...';
        }

        return $prompt;
    }

    /**
     * 从情绪自动推导Motion Prompt参数
     *
     * @param string $emotion 中文情绪词
     * @param string $arc_position 开场|发展|高潮|收尾
     * @return array Motion Prompt参数
     */
    public static function derive_from_emotion(string $emotion, string $arc_position = '发展'): array {
        $emotionMap = [
            '振奋' => ['camera' => 'push_in', 'speed' => 'fast', 'atmosphere' => 'epic', 'action' => 'hand_raise'],
            '期待' => ['camera' => 'push_in', 'speed' => 'slow', 'atmosphere' => 'dreamy', 'action' => 'eye_contact'],
            '专注' => ['camera' => 'push_in', 'speed' => 'slow', 'atmosphere' => 'tense', 'action' => 'eye_contact'],
            '温情' => ['camera' => 'static', 'speed' => 'slow', 'atmosphere' => 'warm', 'action' => 'smile'],
            '希望' => ['camera' => 'pull_out', 'speed' => 'medium', 'atmosphere' => 'warm', 'action' => 'look_away'],
            '决心' => ['camera' => 'push_in', 'speed' => 'medium', 'atmosphere' => 'tense', 'action' => 'lean_forward'],
            '紧张' => ['camera' => 'push_in', 'speed' => 'fast', 'atmosphere' => 'tense', 'action' => 'eye_contact'],
            '悲伤' => ['camera' => 'pull_out', 'speed' => 'slow', 'atmosphere' => 'cold', 'action' => 'look_away'],
            '愤怒' => ['camera' => 'push_in', 'speed' => 'fast', 'atmosphere' => 'tense', 'action' => 'stand_up'],
            '释然' => ['camera' => 'pull_out', 'speed' => 'slow', 'atmosphere' => 'warm', 'action' => 'breathe'],
            '惊讶' => ['camera' => 'push_in', 'speed' => 'fast', 'atmosphere' => 'dramatic', 'action' => 'head_turn'],
        ];

        $params = $emotionMap[$emotion] ?? ['camera' => 'push_in', 'speed' => 'medium', 'atmosphere' => 'cinematic', 'action' => 'head_turn'];

        // arc_position微调
        if ($arc_position === '开场') {
            $params['camera'] = 'pull_out'; // 开场揭示
        } elseif ($arc_position === '高潮') {
            $params['speed'] = 'fast';
            $params['atmosphere'] = 'dramatic';
        } elseif ($arc_position === '收尾') {
            $params['camera'] = 'pull_out'; // 收尾拉远
            $params['speed'] = 'slow';
        }

        return [
            'camera_movement' => $params['camera'],
            'subject_action' => $params['action'],
            'speed' => $params['speed'],
            'atmosphere' => $params['atmosphere'],
            'target' => 'camera',
        ];
    }

    /**
     * v11.3.0 #2: 根据beat_text动态推导Motion参数
     * 分析beat_text中的动作关键词, 推导更精准的镜头/动作/速度
     *
     * @param string $beatText 分镜文本
     * @param string $emotion 情绪 (fallback)
     * @param string $arcPosition 开场|发展|高潮|收尾
     * @return array Motion Prompt参数
     */
    public static function derive_from_beat_text(string $beatText, string $emotion = 'neutral', string $arcPosition = '发展'): array {
        // 先用emotion作为基础
        $params = self::derive_from_emotion($emotion, $arcPosition);

        // v11.3.0 #2: beat_text关键词覆盖 (基于feicai4.0具体动作原则)
        $text = mb_strtolower($beatText);

        // 动作关键词 → subject_action映射
        $actionKeywords = [
            '走' => 'walking', '跑' => 'running', '坐' => 'sitting_down',
            '站' => 'standing_up', '跳' => 'jumping', '打' => 'fighting',
            '挥' => 'hand_wave', '举' => 'hand_raise', '抱' => 'embracing',
            '看' => 'eye_contact', '望' => 'look_away', '低头' => 'head_bow',
            '转身' => 'head_turn', '回头' => 'head_turn',
            '笑' => 'smile', '哭' => 'crying', '说话' => 'speaking',
            '伸手' => 'reaching', '握' => 'grasping',
        ];
        foreach ($actionKeywords as $cn => $actionKey) {
            if (mb_strpos($text, $cn) !== false) {
                $params['subject_action'] = $actionKey;
                break;
            }
        }

        // 场景关键词 → camera_movement映射
        if (preg_match('/(远|全|大|广|航拍|俯瞰)/', $text)) {
            $params['camera_movement'] = 'pull_out';
        } elseif (preg_match('/(近|特写|细节|聚焦)/', $text)) {
            $params['camera_movement'] = 'push_in';
        } elseif (preg_match('/(环绕|转|旋转)/', $text)) {
            $params['camera_movement'] = 'orbit';
        } elseif (preg_match('/(跟随|追|跟拍)/', $text)) {
            $params['camera_movement'] = 'tracking';
        }

        // 速度关键词
        if (preg_match('/(快|急|猛|迅速|突然)/', $text)) {
            $params['speed'] = 'fast';
        } elseif (preg_match('/(慢|缓|轻|柔|渐渐)/', $text)) {
            $params['speed'] = 'slow';
        }

        // 氛围关键词
        if (preg_match('/(战|斗|冲突|危险)/', $text)) {
            $params['atmosphere'] = 'tense';
        } elseif (preg_match('/(梦|幻|回忆|想象)/', $text)) {
            $params['atmosphere'] = 'dreamy';
        } elseif (preg_match('/(暖|阳光|温馨|家)/', $text)) {
            $params['atmosphere'] = 'warm';
        } elseif (preg_match('/(冷|夜|暗|阴)/', $text)) {
            $params['atmosphere'] = 'cold';
        } elseif (preg_match('/(史诗|宏大|壮|震撼)/', $text)) {
            $params['atmosphere'] = 'epic';
        }

        return $params;
    }

    /**
     * 生成视频脚本组 (首帧+尾帧+Motion Prompt)
     *
     * @param array $beat 分镜beat数据
     * @param array $opts {style_keywords, seed_refs, platform}
     * @return array {group_id, first_frame, last_frame, motion_prompt, transition}
     */
    public static function generate_video_group(array $beat, array $opts = []): array {
        $emotion = $beat['emotion'] ?? 'neutral';
        $arcPosition = $beat['arc_position'] ?? '发展';
        $beatText = $beat['text'] ?? $beat['action'] ?? '';
        $styleKeywords = $opts['style_keywords'] ?? '';

        // v11.3.0 #2: 优先用beat_text动态推导, fallback到emotion
        if (method_exists(__CLASS__, 'derive_from_beat_text') && !empty($beatText)) {
            $motionParams = self::derive_from_beat_text($beatText, $emotion, $arcPosition);
        } else {
            $motionParams = self::derive_from_emotion($emotion, $arcPosition);
        }
        $motionPrompt = self::generate($motionParams);

        // 生成首帧Prompt (静态画面)
        $firstFrame = self::generate_frame_prompt($beat, $styleKeywords, 'first');

        // 生成尾帧Prompt (有变化的画面)
        $lastFrame = self::generate_frame_prompt($beat, $styleKeywords, 'last', $motionParams);

        return [
            'group_id' => $beat['id'] ?? '',
            'arc_position' => $arcPosition,
            'emotion' => $emotion,
            'first_frame' => $firstFrame,
            'last_frame' => $lastFrame,
            'motion_prompt' => $motionPrompt,
            'motion_params' => $motionParams,
            'transition' => self::suggest_transition($arcPosition),
            'beat_text' => mb_substr($beatText, 0, 100),
        ];
    }

    /**
     * 生成帧Prompt (首帧/尾帧)
     */
    private static function generate_frame_prompt(array $beat, string $styleKeywords, string $frameType, array $motionParams = []): string {
        $text = $beat['text'] ?? $beat['action'] ?? '';
        $location = $beat['location'] ?? '';
        $emotion = $beat['emotion'] ?? 'neutral';

        // 基础画面描述
        $parts = [];
        if ($frameType === 'first') {
            $parts[] = 'A scene depicting ' . self::translate_action($text);
        } else {
            // 尾帧: 体现动作结果
            $actionEn = self::SUBJECT_ACTIONS[$motionParams['subject_action'] ?? 'head_turn']['en'] ?? 'turns head';
            $actionEn = str_replace('{target}', $motionParams['target'] ?? 'camera', $actionEn);
            $parts[] = 'A scene depicting ' . self::translate_action($text) . ', subject ' . $actionEn;
        }

        if ($location) {
            $parts[] = 'in ' . self::translate_location($location);
        }

        // 情绪氛围
        $emotionEn = self::map_emotion_en($emotion);
        if ($emotionEn) $parts[] = $emotionEn . ' atmosphere';

        // 画风关键词
        if ($styleKeywords) {
            $parts[] = $styleKeywords;
        }

        return implode(', ', $parts) . '.';
    }

    /**
     * 简易中文动作翻译 (复用FP Extractor逻辑)
     */
    private static function translate_action(string $text): string {
        $text = trim($text);
        if (empty($text)) return 'a scene in a quiet street';

        $dict = [
            '走' => 'walking', '跑' => 'running', '坐' => 'sitting', '站' => 'standing',
            '看' => 'looking at', '说' => 'talking', '笑' => 'smiling', '哭' => 'crying',
            '吃' => 'eating', '喝' => 'drinking', '拿' => 'holding', '放' => 'placing',
            '学校' => 'school', '大学' => 'university', '家' => 'home', '城市' => 'city',
            '街道' => 'street', '公园' => 'park', '咖啡馆' => 'cafe', '办公室' => 'office',
            '少年' => 'a young man', '女孩' => 'a young woman', '老人' => 'an elderly person',
            '学生' => 'a student', '记者' => 'a journalist', '网红' => 'an influencer',
        ];

        $englishParts = [];
        foreach ($dict as $cn => $en) {
            if (mb_strpos($text, $cn) !== false) {
                $englishParts[] = $en;
            }
        }

        if (empty($englishParts)) {
            return 'a candid scene depicting daily life, natural atmosphere';
        }

        return implode(', ', array_slice(array_unique($englishParts), 0, 8));
    }

    private static function translate_location(string $location): string {
        return self::translate_action($location);
    }

    private static function map_emotion_en(string $emotion): string {
        $map = [
            '振奋' => 'exciting', '期待' => 'anticipating', '专注' => 'focused',
            '温情' => 'warm', '希望' => 'hopeful', '决心' => 'determined',
            '紧张' => 'tense', '悲伤' => 'melancholic', '愤怒' => 'furious',
            '释然' => 'relieved', '惊讶' => 'surprised', 'neutral' => 'neutral',
        ];
        return $map[$emotion] ?? 'dramatic';
    }

    /**
     * 建议转场方式
     */
    private static function suggest_transition(string $arcPosition): string {
        $map = [
            '开场' => 'fade in from black',
            '发展' => 'cut to next scene',
            '高潮' => 'quick cut, match on action',
            '收尾' => 'fade out to black',
        ];
        return $map[$arcPosition] ?? 'cut';
    }

    /**
     * 获取所有选项 (供前端渲染)
     */
    public static function get_all_options(): array {
        return [
            'camera_movements' => self::CAMERA_MOVEMENTS,
            'subject_actions' => self::SUBJECT_ACTIONS,
            'environment_dynamics' => self::ENVIRONMENT_DYNAMICS,
            'speed_modifiers' => self::SPEED_MODIFIERS,
            'atmosphere_styles' => self::ATMOSPHERE_STYLES,
        ];
    }
}
