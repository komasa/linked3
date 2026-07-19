<?php
/**
 * Linked3 Genesis v10.1.0 补丁 — 彻底修复SEED生成+FP提取+UI
 *
 * v10.1.0 修复:
 *   Bug1: SEED生成只有风格 → 增加本地兜底提取(角色/场景/道具/色板/品牌)
 *   Bug2: FP提取质量低 → 增强本地字典+过滤网页噪声+中文摘要兜底
 *   Bug3: 章节标记/三轴路由 → 前端已修复(无选项+说明)
 *   Bug4: 商业级UI优化 → 前端已修复
 *
 * @package Linked3\Genesis
 * @version 10.1.2
 * @date 2026-06-23
 * @deprecated 10.2.0 本补丁逻辑将合并至 Dashboard_Ajax_Registrar, 届时删除此类
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_Genesis_Patch_V1006 {

    // G2.3: Updated to reference Action classes instead of Legacy God Class
    const ORIG_SEED_CLASS = 'Linked3\\Classes\\Dashboard\\Ajax\\Actions\\Linked3_Dashboard_Genesis_Actions';
    const ORIG_V9_CLASS = 'Linked3\\Classes\\Dashboard\\Ajax\\Actions\\Linked3_Dashboard_GenesisV9_Actions';

    public static function register() : void {
        // G5.1: Removed override chain — endpoints are now registered by Action classes.
        // Patch methods are available as static methods for direct calls if needed.
        if (self::$registered) return;
        self::$registered = true;
    }

    /** @var bool 补丁是否已成功注册 */
    private static $registered = false;

    /**
     * v10.1.2 新增: 检查补丁是否成功覆盖原handler
     * 用于健康检查面板和O部盲区预警验证
     *
     * @return array { active: bool, actions: array }
     */
    public static function health_check(): array {
        $actions = [
            'linked3_genesis_seed_generate',
            'linked3_genesis_v9_stage1',
            'linked3_genesis_v9_stage2',
        ];
        $status = [];
        foreach ($actions as $action) {
            $hook = "wp_ajax_{$action}";
            $patch_active = false;
            if (isset($GLOBALS['wp_filter'][$hook])) {
                foreach ($GLOBALS['wp_filter'][$hook]->callbacks as $priority => $cb_group) {
                    foreach ($cb_group as $cb) {
                        if (is_array($cb['function']) && isset($cb['function'][0]) && $cb['function'][0] === __CLASS__) {
                            $patch_active = true;
                            break 2;
                        }
                    }
                }
            }
            $status[$action] = $patch_active;
        }
        return [
            'active' => self::$registered,
            'actions' => $status,
            'deprecated_target' => 'v10.2.0 — merge into Dashboard_Ajax_Registrar',
        ];
    }

    /**
     * v10.1.2 新增: 外部查询补丁是否已注册
     */
    public static function is_active(): bool {
        return self::$registered;
    }

    // ================================================================
    // Bug1修复: SEED生成 — AI提取 + 本地兜底提取6类SEED
    // ================================================================

        public static function ajax_seed_generate_full() : mixed { return Linked3_Genesis_Patch_Handlers::ajax_seed_generate_full(); }

    // ================================================================
    // v10.1.0 本地兜底: 从剧本提取角色 (中文人名+职业)
    // ================================================================
    private static function local_extract_characters(string $script): array {
        $characters = [];
        $seen = [];

        // 1. 提取中文人名 (2-4字, 常见姓氏开头)
        $commonSurnames = '赵钱孙李周吴郑王冯陈褚卫蒋沈韩杨朱秦尤许何吕施张孔曹严华金魏陶姜戚谢邹喻柏水窦章云苏潘葛奚范彭郎鲁韦昌马苗凤花方俞任袁柳酆鲍史唐费廉岑薛雷贺倪汤滕殷罗毕郝邬安常乐于时傅皮卞齐康伍余元卜顾孟平黄和穆萧尹姚邵湛汪祁毛禹狄米贝明臧计伏成戴谈宋茅庞熊纪舒屈项祝董梁杜阮蓝闵席季麻强贾路娄危江童颜郭梅盛林刁钟徐邱骆高夏蔡田樊胡凌霍虞万支柯昝管卢莫经房裘缪干解应宗丁宣贲邓郁单杭洪包诸左石崔吉钮龚程嵇邢滑裴陆荣翁荀羊於惠甄曲家封芮羿储靳汲邴糜松井段富巫乌焦巴弓牧隗山谷车侯宓蓬全郗班仰秋仲伊宫宁仇栾暴甘钭厉戎祖武符刘景詹束龙叶幸司韶郜黎蓟薄印宿白怀蒲邰从鄂索咸籍赖卓蔺屠蒙池乔阴鬱胥能苍双闻莘党翟谭贡劳逄姬申扶堵冉宰郦雍卻璩桑桂濮牛寿通边扈燕冀郏浦尚农温别庄晏柴瞿阎充慕连茹习宦艾鱼容向古易慎戈廖庾终暨居衡步都耿满弘匡国文寇广禄阙东欧殳沃利蔚越夔隆师巩厍聂晁勾敖融冷訾辛阚那简饶空曾毋沙乜养鞠须丰巢关蒯相查后荆红游竺权逯盖益桓公';
        // 用正则提取 "姓+名" 模式 (2-4字中文, 出现2次以上才算角色名)
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $script, $matches);
        $nameCandidates = [];
        if (!empty($matches[0])) {
            $nameCount = array_count_values($matches[0]);
            foreach ($nameCount as $name => $count) {
                if ($count >= 2 && mb_strlen($name) >= 2 && mb_strlen($name) <= 4) {
                    // 检查是否以常见姓氏开头
                    $firstChar = mb_substr($name, 0, 1);
                    if (mb_strpos($commonSurnames, $firstChar) !== false) {
                        // 排除常见非人名词
                        $excludeWords = ['这个', '一个', '什么', '怎么', '可以', '已经', '现在', '他们', '我们', '自己', '没有', '不是', '这样', '那种', '的话', '因为', '所以', '但是', '如果', '虽然', '然而', '之后', '之前', '之间', '之后', '起来', '下去', '过来', '过去', '时候', '地方', '东西', '事情', '问题', '感觉', '觉得', '知道', '认为', '看到', '听到', '想到', '发现', '出现', '发生', '存在', '继续', '开始', '结束', '完成', '进行'];
                        if (!in_array($name, $excludeWords)) {
                            $nameCandidates[] = $name;
                        }
                    }
                }
            }
        }
        $nameCandidates = array_slice(array_unique($nameCandidates), 0, 5);
        foreach ($nameCandidates as $name) {
            $characters[] = ['name' => $name, 'appearance' => '人物外观待补充', 'clothing' => '', 'distinctive_features' => ''];
            $seen[] = $name;
        }

        // 2. 提取职业角色
        $roleRules = [
            'a student' => ['学生', '少年', '青年', '学子', '同学'],
            'a journalist' => ['记者', '编辑', '媒体人'],
            'a teacher' => ['老师', '教师', '教授', '导师'],
            'a doctor' => ['医生', '大夫', '护士'],
            'a worker' => ['工人', '员工', '职员', '建设者'],
            'a child' => ['孩子', '儿童', '小孩', '少年'],
            'an elderly person' => ['老人', '大爷', '大妈', '老者'],
            'a celebrity' => ['网红', '明星', '艺人', '演员', '歌手'],
            'a businessman' => ['商人', '老板', '企业家', 'CEO'],
            'a parent' => ['父亲', '母亲', '爸爸', '妈妈', '父母'],
        ];
        foreach ($roleRules as $en => $cnList) {
            foreach ($cnList as $cn) {
                if (mb_strpos($script, $cn) !== false) {
                    // 避免重复
                    $alreadyHas = false;
                    foreach ($characters as $c) {
                        if (isset($c['appearance']) && strpos($c['appearance'], $en) !== false) { $alreadyHas = true; break; }
                    }
                    if (!$alreadyHas && count($characters) < 5) {
                        $characters[] = ['name' => $cn, 'appearance' => $en . ' appearance', 'clothing' => '', 'distinctive_features' => ''];
                    }
                    break;
                }
            }
        }

        return array_slice($characters, 0, 5);
    }

    // ================================================================
    // v10.1.0 本地兜底: 从剧本提取场景
    // ================================================================
    private static function local_extract_scenes(string $script): array {
        $scenes = [];
        $sceneRules = [
            ['name' => '学校', 'keywords' => ['学校', '大学', '中学', '小学', '校园', '教室', '课堂', '学院']],
            ['name' => '家庭', 'keywords' => ['家里', '家中', '客厅', '卧室', '厨房', '家庭']],
            ['name' => '办公室', 'keywords' => ['办公室', '公司', '写字楼', '会议室', '工位']],
            ['name' => '街道', 'keywords' => ['街道', '马路', '街头', '路边', '街上']],
            ['name' => '医院', 'keywords' => ['医院', '诊所', '病房', '急诊']],
            ['name' => '社交媒体', 'keywords' => ['社交媒体', '微博', '微信', '抖音', '视频', '直播', '网络', '网上', '网友']],
            ['name' => '城市', 'keywords' => ['城市', '都市', '北京', '上海', '广州', '深圳', '波士顿', '纽约', '洛杉矶']],
            ['name' => '户外', 'keywords' => ['户外', '公园', '广场', '街头', '野外', '山顶', '海边']],
        ];
        foreach ($sceneRules as $rule) {
            foreach ($rule['keywords'] as $kw) {
                if (mb_strpos($script, $kw) !== false) {
                    $scenes[] = ['name' => $rule['name'], 'description' => '包含' . $kw . '的场景', 'lighting' => 'natural lighting', 'atmosphere' => 'realistic'];
                    break;
                }
            }
            if (count($scenes) >= 5) break;
        }
        return $scenes;
    }

    private static function extract_props_from_script(string $script): array {
        $props = [];
        $propDict = ['手机', '相机', '电脑', '汽车', '自行车', '雨伞', '书包', '吉他', '钢琴', '画笔', '话筒', '麦克风', '奖杯', '证书', '信件', '日记', '照片', '钥匙', '手表', '眼镜', '帽子', '围巾', '背包', '行李箱', '怀表', '刀剑', '书本', '报纸', '杂志', '文件'];
        foreach ($propDict as $p) {
            if (mb_strpos($script, $p) !== false) $props[] = $p;
        }
        return array_slice(array_unique($props), 0, 5);
    }

    private static function extract_brand_from_script(string $script): string {
        if (preg_match('/[""""]([^"""]{2,20})["""]/', $script, $m)) return $m[1];
        if (preg_match('/《([^》]{2,20})》/', $script, $m)) return $m[1];
        return '';
    }

    // ================================================================
    // Bug2修复: Stage1 — 读取panel_count按split_mode控制beats
    // ================================================================

        public static function ajax_v9_stage1_fixed() : mixed { return Linked3_Genesis_Patch_Handlers::ajax_v9_stage1_fixed(); }

    // ================================================================
    // v10.1.0: 过滤网页噪声文本
    // ================================================================
    private static function filter_web_noise(string $text): string {
        $noisePatterns = [
            '/AI导读[：:]/u',
            '/内容由AI智能生成/u',
            '/有用[】\]]/u',
            '/🧬\s*Stage\s*\d/u',
            '/SEED\s*中心/u',
            '/公理\d/u',
            '/点击[「」""\']+从库中选择[「」""\']+/u',
            '/未选择任何\s*SEED/u',
            '/已选\s*\d+\s*个\s*SEED/u',
        ];
        foreach ($noisePatterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        return trim($text);
    }

    // ================================================================
    // Bug3修复: Stage2 — 注入styleId画风 + 增强FP兜底
    // ================================================================

        public static function ajax_v9_stage2_fixed() : mixed { return Linked3_Genesis_Patch_Handlers::ajax_v9_stage2_fixed(); }

    // ================================================================
    // v10.1.0: 增强本地FP提取 — 大字典+中文摘要兜底
    // ================================================================
    private static function enhanced_local_extract(string $text, string $emotion = 'neutral'): array {
        $text = trim($text);
        if (empty($text)) {
            return ['who' => 'a person', 'what' => '', 'where' => '', 'when' => '', 'emotion' => $emotion, 'theme' => '', 'action_en' => 'a person in a scene, natural lighting', 'raw' => ''];
        }

        // 大幅扩展的中→英字典
        $dict = [
            // 地点
            '学校' => 'school', '大学' => 'university', '校园' => 'campus', '教室' => 'classroom',
            '医院' => 'hospital', '诊所' => 'clinic', '办公室' => 'office', '公司' => 'company',
            '家庭' => 'home', '家里' => 'at home', '客厅' => 'living room', '卧室' => 'bedroom',
            '街道' => 'street', '马路' => 'road', '街头' => 'street corner', '城市' => 'city',
            '都市' => 'metropolis', '乡村' => 'countryside', '公园' => 'park', '广场' => 'square',
            '咖啡馆' => 'cafe', '餐厅' => 'restaurant', '商店' => 'shop', '市场' => 'market',
            '工厂' => 'factory', '工地' => 'construction site', '车站' => 'station', '机场' => 'airport',
            '海边' => 'seaside', '山顶' => 'mountain top', '森林' => 'forest', '河边' => 'riverside',
            '社交媒体' => 'social media', '网络' => 'internet', '网上' => 'online', '视频' => 'video',
            '直播' => 'livestream', '镜头' => 'camera lens', '屏幕' => 'screen',
            // 人物
            '学生' => 'student', '少年' => 'young man', '青年' => 'young person', '女孩' => 'girl',
            '男孩' => 'boy', '老人' => 'elderly person', '大爷' => 'old man', '大妈' => 'old woman',
            '记者' => 'journalist', '编辑' => 'editor', '老师' => 'teacher', '医生' => 'doctor',
            '工人' => 'worker', '农民' => 'farmer', '商人' => 'businessman', '网红' => 'internet celebrity',
            '明星' => 'celebrity', '演员' => 'actor', '歌手' => 'singer', '父母' => 'parents',
            '父亲' => 'father', '母亲' => 'mother', '孩子' => 'child', '儿童' => 'child',
            '家庭' => 'family', '朋友' => 'friend', '同学' => 'classmate', '同事' => 'colleague',
            // 动作
            '走' => 'walking', '跑' => 'running', '坐' => 'sitting', '站' => 'standing',
            '看' => 'looking', '说' => 'speaking', '谈' => 'talking', '笑' => 'smiling',
            '哭' => 'crying', '听' => 'listening', '写' => 'writing', '读' => 'reading',
            '吃' => 'eating', '喝' => 'drinking', '买' => 'buying', '卖' => 'selling',
            '拿' => 'holding', '放' => 'placing', '推' => 'pushing', '拉' => 'pulling',
            '打' => 'hitting', '抓' => 'grabbing', '举' => 'raising', '指' => 'pointing',
            '拍' => 'filming', '录' => 'recording', '播' => 'broadcasting', '传' => 'spreading',
            '拒绝' => 'refusing', '接受' => 'accepting', '坚持' => 'persisting', '放弃' => 'giving up',
            '成功' => 'succeeding', '失败' => 'failing', '开始' => 'starting', '结束' => 'ending',
            '回' => 'returning', '去' => 'going', '来' => 'coming', '到' => 'arriving',
            '考入' => 'admitted to', '入学' => 'entering school', '毕业' => 'graduating',
            '签约' => 'signing contract', '挣钱' => 'earning money', '存钱' => 'saving money',
            // 情绪/状态
            '开心' => 'happy', '快乐' => 'joyful', '悲伤' => 'sad', '愤怒' => 'angry',
            '紧张' => 'nervous', '焦虑' => 'anxious', '兴奋' => 'excited', '平静' => 'calm',
            '疲惫' => 'exhausted', '坚定' => 'determined', '犹豫' => 'hesitant', '惊讶' => 'surprised',
            '温暖' => 'warm', '寒冷' => 'cold', '热闹' => 'bustling', '安静' => 'quiet',
            '真实' => 'authentic', '自然' => 'natural', '严肃' => 'serious', '轻松' => 'relaxed',
            // 物品
            '手机' => 'smartphone', '相机' => 'camera', '电脑' => 'computer', '书' => 'book',
            '笔' => 'pen', '纸' => 'paper', '桌' => 'desk', '椅' => 'chair',
            '门' => 'door', '窗' => 'window', '车' => 'car', '伞' => 'umbrella',
            '包' => 'bag', '眼镜' => 'glasses', '帽子' => 'hat', '表' => 'watch',
            // 时间/环境
            '白天' => 'daytime', '夜晚' => 'night', '早上' => 'morning', '下午' => 'afternoon',
            '傍晚' => 'evening', '凌晨' => 'dawn', '雨天' => 'rainy day', '晴天' => 'sunny day',
            '雪天' => 'snowy day', '室内' => 'indoor', '室外' => 'outdoor',
            // 城市
            '北京' => 'Beijing', '上海' => 'Shanghai', '广州' => 'Guangzhou', '深圳' => 'Shenzhen',
            '波士顿' => 'Boston', '纽约' => 'New York', '洛杉矶' => 'Los Angeles', '中国' => 'China',
            '美国' => 'America', '日本' => 'Japan', '韩国' => 'Korea',
        ];

        $englishParts = [];
        foreach ($dict as $cn => $en) {
            if (mb_strpos($text, $cn) !== false) {
                $englishParts[] = $en;
            }
        }

        // 提取who
        $who = 'a person';
        $whoRules = [
            'a student' => ['学生', '少年', '青年', '学子'],
            'a journalist' => ['记者', '编辑'],
            'a teacher' => ['老师', '教师', '教授'],
            'a doctor' => ['医生', '大夫'],
            'a celebrity' => ['网红', '明星', '艺人'],
            'a child' => ['孩子', '儿童', '小孩'],
            'an elderly person' => ['老人', '大爷', '大妈'],
            'a young man' => ['男孩', '少年'],
            'a young woman' => ['女孩', '少女'],
        ];
        foreach ($whoRules as $en => $cnList) {
            foreach ($cnList as $cn) {
                if (mb_strpos($text, $cn) !== false) { $who = $en; break 2; }
            }
        }

        // 提取where
        $where = '';
        if (preg_match('/在(.{2,10}?)(?:[，,。])/u', $text, $m)) {
            $where = $m[1];
        }

        // 生成action_en
        if (!empty($englishParts)) {
            $desc = implode(', ', array_slice(array_unique($englishParts), 0, 10));
            $actionEn = $who . ' in ' . $desc . ', natural lighting, authentic atmosphere';
        } else {
            // v10.1.0 兜底: 用文本前50字的拼音/直译描述 (避免中文直塞)
            $actionEn = $who . ' in a scene, natural lighting, authentic atmosphere, candid moment';
        }

        return [
            'who' => $who,
            'what' => mb_substr($text, 0, 50),
            'where' => $where,
            'when' => '',
            'emotion' => $emotion,
            'theme' => mb_substr($text, 0, 30),
            'action_en' => $actionEn,
            'raw' => $text,
        ];
    }

    /**
     * v10.1.0: 画风风格自动检测 — 根据剧本关键词推断最匹配的styleId
     */
    public static function auto_detect_style(string $script): string {
        $text = mb_strtolower($script);
        $rules = [
            // 驱魔/灵异/东方暗黑
            'exorcism_dark_ink' => ['驱魔', '道士', '妖魔', '鬼', '灵异', '古宅', '阴间', '符箓', '桃木剑', '超自然'],
            'exorcism_ink_variant' => ['水墨', '东方', '古风', '侠客', '江湖', '武侠'],
            // 赛博朋克
            'cyberpunk_neon' => ['赛博', '霓虹', '未来', '机械', '义体', '黑客', 'AI', '机器人', '2077', 'dystopia'],
            // 浮世绘/和风
            'ukiyoe_washoku' => ['日本', '和风', '浮世绘', '武士', '樱花', '东京', '京都', '忍者'],
            // 影视剧照
            'cinematic_still' => ['电影', '剧照', '大片', '好莱坞', '导演', '演员', '片场', 'cinematic'],
            // 时尚大片
            'fashion_editorial' => ['时尚', '杂志', '封面', '模特', '时装', '秀场', 'vogue', 'fashion', '品牌大片'],
            // 古风汉服
            'hanfu_photography' => ['汉服', '古风', '国风', '唐朝', '宋朝', '汉', '宫', '妃', '古装'],
            // 哥特暗黑
            'gothic_dark_stained' => ['哥特', '教堂', '彩窗', '暗黑', '吸血鬼', '中世纪', 'gothic'],
            // 极简水墨
            'zen_minimal_ink' => ['禅', '极简', '留白', '水墨', '文人', '雅', 'zen', 'minimal'],
            // 蒸汽朋克
            'steampunk_victorian' => ['蒸汽', '机械', '维多利亚', '齿轮', '蒸汽朋克', 'steampunk'],
            // 写真摄影
            'portrait_photography' => ['写真', '人像', '肖像', '特写', 'portrait', '摄影'],
            // 纪实摄影
            'documentary_photo' => ['新闻', '纪实', '报道', '记者', '现场', '真实', 'documentary', '事件'],
            // 暗黑肖像
            'dark_portrait' => ['暗黑', '哥特', '阴郁', '恐怖', 'dark', 'gothic'],
            // 水彩
            'watercolor_soft' => ['水彩', '绘本', '童话', '治愈', '插画', 'watercolor'],
        ];

        $scores = [];
        foreach ($rules as $styleId => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (mb_strpos($text, mb_strtolower($kw)) !== false) {
                    $score++;
                }
            }
            if ($score > 0) $scores[$styleId] = $score;
        }

        if (empty($scores)) return 'documentary_photo';
        arsort($scores);
        return array_key_first($scores);
    }
}

// G5.1: Patch auto-registration disabled — override chain eliminated
