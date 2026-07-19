<?php
/**
 * FP True Bone-Strip Extractor v10.0.1 — 令牌桶+缓存+兜底增强
 *
 * v10.0.1 优化 (基于 /genesis 链路深度公理分析):
 *   公理1: 信息熵减 — AI结果缓存, 相同文本段不重复调用
 *   公理2: 系统降维 — 令牌桶限流, 避免并发429; 本地兜底保证不报错
 *
 * 优化项:
 *   1. 令牌桶限流 (默认3并发, 每秒补充1令牌, 突发可借3)
 *   2. transient缓存 (语义核1小时, 相同文本段不重复AI调用)
 *   3. 本地兜底增强 (AI失败时自动降级, UI标注"本地兜底")
 *   4. 结构化日志 (调用/缓存命中/兜底 全程记录)
 *   5. 统一错误码 (E_API_xxx, E_NETWORK_xxx)
 *
 * 兼容性: 保留原 extract() 方法签名, 内部增强
 *
 * @package Linked3\Genesis
 * @since 8.1.0
 * @version 10.0.1
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_FP_Extractor
{
    /** @var Linked3_Token_Bucket|null 令牌桶实例 */
    private $token_bucket = null;

    /** @var int 缓存过期时间(秒) */
    const CACHE_TTL = 3600; // 1小时

    /** @var string 缓存键前缀 */
    const CACHE_PREFIX = 'lk3_fp_core_';

    /**
     * 构造 — 初始化令牌桶
     */
    public function __construct() {
        if (!class_exists('\Linked3\Classes\Genesis\Linked3_Token_Bucket')) {
            require_once __DIR__ . '/class-linked3-token-bucket.php';
        }
        // 令牌桶: 容量3, 每秒补充1, 突发可借3
        $this->token_bucket = new Linked3_Token_Bucket(
            'lk3_fp_ai',
            intval(get_option(LINKED3_OPTION_PREFIX . 'fp_ai_concurrency', 3)),
            1.0
        );
    }

    /**
     * 真剥骨 — 从中文文本段提取结构化语义核
     *
     * @param string $text_segment 中文文本段
     * @param array  $opts {use_ai: bool, style_name: string, cache_key: string}
     * @return array {who, what, where, when, emotion, theme, action_en, raw, source}
     */
    public function extract(string $text_segment, array $opts = []): array
    {
        $text = trim($text_segment);
        if (empty($text)) {
            return $this->empty_core();
        }

        // v10.0.1: 缓存检查
        $cache_key = $opts['cache_key'] ?? self::CACHE_PREFIX . md5($text);
        if (empty($opts['no_cache'])) {
            $cached = get_transient($cache_key);
            if (is_array($cached) && !empty($cached['action_en'])) {
                if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Logger')) {
                    Linked3_Genesis_Logger::debug('FP缓存命中', ['cache_key' => $cache_key], 'fp_extract');
                }
                $cached['source'] = 'cache';
                return $cached;
            }
        }

        // 优先 AI 提取 (精准), 失败回退本地规则库
        if (!empty($opts['use_ai'])) {
            $ai_core = $this->ai_extract($text, $opts['style_name'] ?? '');
            if ($ai_core && !empty($ai_core['action_en'])) {
                $ai_core['source'] = 'ai';
                // v10.0.1: 写入缓存
                set_transient($cache_key, $ai_core, self::CACHE_TTL);
                return $ai_core;
            }
            // AI失败, 记录日志
            if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Logger')) {
                Linked3_Genesis_Logger::warn('FP AI提取失败, 降级本地兜底', [
                    'text_preview' => mb_substr($text, 0, 50),
                ], 'fp_extract');
            }
        }

        // 本地规则库兜底 (关键词→结构化映射)
        $local = $this->local_extract($text);
        $local['source'] = 'local';
        return $local;
    }

    /**
     * AI 提取结构化语义核 (含中文→英文翻译)
     * v10.0.1: 增加令牌桶限流 + 错误码 + 日志
     */
    private function ai_extract(string $text, string $styleName): ?array
    {
        if (!class_exists('\\Linked3\\Classes\\Core\\AIDispatcher')) {
            return null;
        }

        // v10.0.1: 令牌桶限流
        if ($this->token_bucket && !$this->token_bucket->acquire(1, 5)) {
            // 5秒内拿不到令牌, 直接降级本地兜底
            if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Logger')) {
                Linked3_Genesis_Logger::warn('FP令牌桶限流, 降级本地兜底', [
                    'text_preview' => mb_substr($text, 0, 50),
                ], 'fp_extract');
            }
            return null;
        }

        $prompt = sprintf(
            "You are an FP semantic extractor. Extract a structured semantic core from this Chinese text and translate to English.\n\n" .
            "Text: %s\n\n" .
            "Return JSON with these fields:\n" .
            "- who: main subject in English (e.g. 'a traveler', 'a doctor', 'a job seeker')\n" .
            "- what: main action in English (e.g. 'boarding the first high-speed train')\n" .
            "- where: location in English (e.g. 'Jingmen station')\n" .
            "- when: time context in English (e.g. 'opening day', 'night', 'morning')\n" .
            "- emotion: one Chinese word from: 振奋/期待/专注/温情/希望/决心/宁静/愉悦/紧张/焦虑/悲伤/愤怒/恐惧/释然/自豪/孤独/怀念/惊讶/平和\n" .
            "- theme: one sentence theme in English\n" .
            "- action_en: a complete English phrase describing the action for image generation (15-30 words)\n\n" .
            "Example for '去年荆荆高铁开通':\n" .
            "{\"who\":\"a traveler\",\"what\":\"boarding the first high-speed train\",\"where\":\"Jingmen station\",\"when\":\"opening day\",\"emotion\":\"振奋\",\"theme\":\"transportation milestone\",\"action_en\":\"a traveler boarding the first high-speed train at Jingmen station on opening day, warm golden hour light\"}\n\n" .
            "Return only JSON, no explanation.",
            mb_substr($text, 0, 500)
        );

        try {
            $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

            $result = \Linked3\Classes\Core\AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                [
                    'provider' => $provider,
                    'model'    => $model,
                    'temperature' => 0.3,
                    'max_tokens'  => 400,
                    'module'      => 'genesis_fp',
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );

            $raw = $result['content'] ?? '';
            return $this->parse_core_json($raw, $text);
        } catch (\Throwable $e) {
            // v10.0.1: 结构化日志
            if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Logger')) {
                Linked3_Genesis_Logger::exception($e, 'fp_extract', [
                    'text_preview' => mb_substr($text, 0, 50),
                ]);
            }
            return null;
        }
    }

    /**
     * 本地规则库兜底 — 关键词→结构化映射
     */
    private function local_extract(string $text): array
    {
        $core = $this->empty_core();
        $core['raw'] = $text;

        // 情绪关键词映射
        $emotionRules = [
            '振奋' => ['开通', '首发', '成功', '突破', '创新', '胜利', '夺冠'],
            '期待' => ['即将', '等待', '盼望', '迎来'],
            '专注' => ['研究', '治疗', '诊断', '学习', '工作', '讨论'],
            '温情' => ['陪伴', '照顾', '关怀', '温暖', '家庭'],
            '希望' => ['计划', '梦想', '未来', '招才', '引智'],
            '决心' => ['决定', '坚持', '誓言', '承诺'],
            '紧张' => ['比赛', '冲突', '对峙', '战斗', '危机'],
            '悲伤' => ['去世', '离别', '失去', '遗憾'],
            '释然' => ['终于', '完成', '解决'],
            '自豪' => ['荣誉', '成就', '第一', '冠军'],
        ];
        foreach ($emotionRules as $emo => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($text, $kw) !== false) {
                    $core['emotion'] = $emo;
                    break 2;
                }
            }
        }

        // 提取人物 (简单规则: 中文姓名/职业)
        $subjectRules = [
            'a traveler' => ['旅客', '乘客', '市民'],
            'a doctor' => ['医生', '大夫', '中医'],
            'a job seeker' => ['求职者', '应聘者'],
            'a student' => ['学生', '学子'],
            'a worker' => ['工人', '建设者'],
            'a child' => ['孩子', '儿童', '少年'],
        ];
        foreach ($subjectRules as $en => $cnList) {
            foreach ($cnList as $cn) {
                if (mb_strpos($text, $cn) !== false) {
                    $core['who'] = $en;
                    break 2;
                }
            }
        }

        // 提取地点
        if (preg_match('/在(.{2,10}?)(?:[，,。])/u', $text, $m)) {
            $core['where'] = $m[1];
        }

        // 提取时间
        $timeRules = ['今天' => 'today', '昨天' => 'yesterday', '凌晨' => 'dawn', '上午' => 'morning', '下午' => 'afternoon', '晚上' => 'night', '夜间' => 'night'];
        foreach ($timeRules as $cn => $en) {
            if (mb_strpos($text, $cn) !== false) {
                $core['when'] = $en;
                break;
            }
        }

        // v9.1.7: action_en — 禁止中文直塞, 用关键词翻译
        $core['action_en'] = self::translate_to_english($text);
        $core['what'] = mb_substr($text, 0, 30);
        $core['theme'] = mb_substr($text, 0, 20);

        return $core;
    }

    /**
     * v9.1.7: 简易中→英翻译 (关键词映射)
     * 避免中文直塞英文 Prompt
     */
    private function translate_to_english(string $text): string
    {
        $text = trim($text);
        if (empty($text)) return 'a scene in a quiet street';

        $dict = [
            '胡同' => 'Beijing hutong alley', '巷子' => 'narrow alley', '街道' => 'street',
            '城市' => 'city', '都市' => 'metropolis', '乡村' => 'countryside', '农村' => 'rural village',
            '山' => 'mountain', '森林' => 'forest', '海' => 'sea', '湖' => 'lake', '河' => 'river',
            '公园' => 'park', '广场' => 'square', '学校' => 'school', '医院' => 'hospital',
            '咖啡馆' => 'cafe', '餐厅' => 'restaurant', '商店' => 'shop', '市场' => 'market',
            '工厂' => 'factory', '办公室' => 'office', '房间' => 'room', '客厅' => 'living room',
            '老人' => 'an elderly person', '大爷' => 'an old man', '大妈' => 'an old woman',
            '孩子' => 'a child', '少年' => 'a teenager', '少女' => 'a young girl',
            '小贩' => 'a street vendor', '手艺人' => 'a craftsman', '商人' => 'a merchant',
            '旅客' => 'a traveler', '医生' => 'a doctor', '学生' => 'a student',
            '工人' => 'a worker', '警察' => 'a police officer', '司机' => 'a driver',
            '烤白薯' => 'roasted sweet potato', '糖葫芦' => 'candied hawthorn stick',
            '驴打滚' => 'glutinous rice roll', '豌豆黄' => 'pea flour cake',
            '自行车' => 'bicycle', '汽车' => 'car', '火车' => 'train', '飞机' => 'airplane',
            '雨' => 'rain', '雪' => 'snow', '风' => 'wind', '云' => 'cloud', '月' => 'moon',
            '日出' => 'sunrise', '日落' => 'sunset', '黄昏' => 'golden hour', '夜晚' => 'night',
            '青砖' => 'grey brick', '灰瓦' => 'grey tile', '古宅' => 'old mansion',
            '炭火' => 'charcoal fire', '灯光' => 'lamplight', '阳光' => 'sunlight',
            '走' => 'walking', '跑' => 'running', '坐' => 'sitting', '站' => 'standing',
            '看' => 'looking at', '笑' => 'smiling', '哭' => 'crying', '说' => 'talking',
            '吃' => 'eating', '喝' => 'drinking', '买' => 'buying', '卖' => 'selling',
            '推' => 'pushing', '拉' => 'pulling', '拿' => 'holding', '放' => 'placing',
            '温暖' => 'warm', '寒冷' => 'cold', '热闹' => 'bustling', '安静' => 'quiet',
            '怀念' => 'nostalgic', '悲伤' => 'sad', '快乐' => 'happy', '愤怒' => 'angry',
            '北京' => 'Beijing', '上海' => 'Shanghai', '中国' => 'China',
            '炒菜' => 'cooking', '下棋' => 'playing chess', '锅铲' => 'spatula',
            '市井' => 'street life', '味道' => 'flavor', '甜香' => 'sweet aroma',
            '微改造' => 'gentle renovation', '修旧如旧' => 'restored to original',
        ];

        $english_parts = [];
        foreach ($dict as $cn => $en) {
            if (mb_strpos($text, $cn) !== false) {
                $english_parts[] = $en;
            }
        }

        if (empty($english_parts)) {
            return 'a candid scene depicting daily life, natural atmosphere, authentic moment';
        }

        $desc = implode(', ', array_slice(array_unique($english_parts), 0, 8));
        return 'a scene depicting ' . $desc . ', natural lighting, authentic atmosphere';
    }

    /**
     * 解析 AI 返回的语义核 JSON
     */
    private function parse_core_json(string $raw, string $originalText): ?array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (!is_array($decoded)) return null;

        if (empty($decoded['action_en'])) return null;

        return [
            'who'       => $decoded['who'] ?? 'a figure',
            'what'      => $decoded['what'] ?? '',
            'where'     => $decoded['where'] ?? '',
            'when'      => $decoded['when'] ?? '',
            'emotion'   => $decoded['emotion'] ?? 'neutral',
            'theme'     => $decoded['theme'] ?? '',
            'action_en' => $decoded['action_en'],
            'raw'       => $originalText,
        ];
    }

    private function empty_core(): array
    {
        return [
            'who' => '', 'what' => '', 'where' => '', 'when' => '',
            'emotion' => 'neutral', 'theme' => '', 'action_en' => '', 'raw' => '',
            'source' => 'empty',
        ];
    }
}
