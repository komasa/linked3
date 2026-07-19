<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisAtomIndex {
    private static ?GenesisAtomIndex $instance = null;
    private array $data = [];

    public static function instance(): GenesisAtomIndex {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $path = __DIR__ . '/atom_index.json';
        if (file_exists($path)) {
            $json = file_get_contents($path);
            if ($json === false) {
                $this->data = [];
                return;
            }
            $this->data = json_decode($json, true) ?: [];
        }
    }

    public function getAtom(string $atomId): ?array {
        return $this->data['atoms'][$atomId] ?? null;
    }

        public function searchKeyword(string $keyword) : mixed { return GenesisEngineExtras::searchKeyword($keyword); }

        public function getByType(string $type) : mixed { return GenesisEngineExtras::getByType($type); }

        public function getCharacters() : mixed { return GenesisEngineHelpers::getCharacters(); }

        public function getScenes() : mixed { return GenesisEngineHelpers::getScenes(); }

        public function getTemplates() : mixed { return GenesisEngineHelpers::getTemplates(); }

    public function getStyles(): array {
        $path = __DIR__ . '/styles/_index.json';
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?: [];
    }

    public function getStyleConfig(string $styleId): array {
        $styles = $this->getStyles();
        $styleFile = $styles['styles'][$styleId] ?? null;
        if (!$styleFile) return [];
        $path = __DIR__ . '/styles/' . $styleId . '.json';
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?: [];
    }

    public function getStats(): array {
        return [
            'total_atoms' => count($this->data['atoms'] ?? []),
            'types' => array_map('count', $this->data['by_type'] ?? []),
            'styles' => count(($this->getStyles()['styles'] ?? [])),
        ];
    }
}

class Linked3_Genesis_PlotParser {
    private array $contentTypeKeywords = [
        'T1_动作战斗' => ['战斗', '对决', '打', '战', '攻击', '挥', '劈', '刺', '驱魔', '施法', '封印'],
        'T2_对话叙事' => ['说', '道', '问', '答', '对话', '讲述', '解释', '告诉'],
        'T3_警示告诫' => ['警告', '危险', '小心', '注意', '禁忌', '不可', '切勿'],
        'T4_情感回忆' => ['回忆', '记忆', '往事', '曾经', '过去', '思念', '悲伤', '泪', '哭'],
        'T5_对峙紧张' => ['对峙', '僵持', '凝视', '对视', '紧张', '沉默', '逼近'],
        'T6_回忆闪回' => ['闪回', '梦境', '幻觉', '虚幻', '浮现', '重现'],
        'T7_品牌封面' => ['封面', '海报', '宣传', 'logo', '标题', '刊头'],
    ];

    public function parse(string $scriptText): array {
        $scenes = [];
        $current = null;
        $lines = explode("\n", trim($scriptText));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;

            if (preg_match('/^[【\[]场景\s*(\d+)[】\]]\s*(.*)/', $line, $m) ||
                preg_match('/^##\s*场景\s*(\d+)\s*[：:]\s*(.*)/', $line, $m)) {
                if ($current) $scenes[] = $current;
                $current = [
                    'id' => 'S' . str_pad((string)(count($scenes) + 1), 3, '0', STR_PAD_LEFT),
                    'location' => trim($m[2]),
                    'characters' => [],
                    'action' => '',
                    'mood' => '',
                    'dialogues' => [],
                ];
                continue;
            }

            if ($current === null) {
                $current = [
                    'id' => 'S001', 'location' => '默认场景',
                    'characters' => [], 'action' => $line, 'mood' => '', 'dialogues' => [],
                ];
                continue;
            }

            if (mb_strpos($line, '角色:') === 0 || mb_strpos($line, '角色：') === 0) {
                $current['characters'] = array_map('trim', explode('，', mb_substr($line, 3)));
            } elseif (mb_strpos($line, '动作:') === 0 || mb_strpos($line, '动作：') === 0) {
                $current['action'] = trim(mb_substr($line, 3));
            } elseif (mb_strpos($line, '氛围:') === 0 || mb_strpos($line, '氛围：') === 0) {
                $current['mood'] = trim(mb_substr($line, 3));
            }
        }

        if ($current) $scenes[] = $current;
        return $scenes;
    }

    public function detectContentType(string $text): string {
        foreach ($this->contentTypeKeywords as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($text, $kw) !== false) return $type;
            }
        }
        return 'T2_对话叙事';
    }

    public function getContentTypeKeywords(): array {
        return $this->contentTypeKeywords;
    }
}

class Linked3_Genesis_AtomSelector {
    private GenesisAtomIndex $index;

    public function __construct() {
        $this->index = GenesisAtomIndex::instance();
    }

    public function selectForScene(array $scene): array {
        $text = $scene['action'] . ' ' . $scene['location'] . ' ' . $scene['mood'];
        $contentType = linked3_genesis_detect_content_type($text);
        $characters = !empty($scene['characters'])
            ? $this->matchCharacters($scene['characters'])
            : linked3_genesis_detect_characters($text);

        return [
            'content_type' => $contentType,
            'characters' => $characters,
            'template' => $this->selectTemplate($contentType, $characters),
            'scene' => $this->selectScene($scene['location']),
            'atmosphere' => $this->selectAtmosphere($scene['mood']),
            'camera' => $this->selectCamera($scene),
            'composition' => $this->selectComposition($contentType),
            'color_mapping' => $this->selectColorMapping($this->selectScene($scene['location'])),
        ];
    }

    private function matchCharacters(array $charNames): array {
        $found = [];
        foreach ($charNames as $name) {
            foreach (linked3_genesis_get_character_keywords() as $charId => $keywords) {
                foreach ($keywords as $kw) {
                    if (mb_strpos($name, $kw) !== false) {
                        $found[] = $charId;
                        break 2;
                    }
                }
            }
        }
        return array_unique($found) ?: ['C001'];
    }

    private function selectTemplate(string $contentType, array $characters): array {
        $templates = $this->index->getTemplates();
        foreach ($templates as $id => $t) {
            if ($t['content_type'] === $contentType) return ['id' => $id, 'fields' => $t];
        }
        $first = reset($templates);
        return ['id' => key($templates), 'fields' => $first];
    }

    private function selectScene(string $location): array {
        $scenes = $this->index->getScenes();
        foreach ($scenes as $id => $s) {
            if (mb_strpos($location, $s['scene_name']) !== false) return ['id' => $id, 'fields' => $s];
        }
        $keywords = ['古宅' => 'SC01', '荒野' => 'SC02', '山' => 'SC03', '地府' => 'SC04', '回忆' => 'SC05'];
        foreach ($keywords as $kw => $sid) {
            if (mb_strpos($location, $kw) !== false && isset($scenes[$sid])) {
                return ['id' => $sid, 'fields' => $scenes[$sid]];
            }
        }
        return ['id' => 'SC01', 'fields' => $scenes['SC01'] ?? []];
    }

    private function selectAtmosphere(string $mood): array {
        $atmospheres = $this->index->getByType('atmosphere');
        foreach ($atmospheres as $aid) {
            $atom = $this->index->getAtom($aid);
            if ($atom && mb_strpos($mood, $atom['fields']['mood_name'] ?? '') !== false) {
                return ['id' => $aid, 'fields' => $atom['fields']];
            }
        }
        $atom = $this->index->getAtom('AT01');
        return ['id' => 'AT01', 'fields' => $atom['fields'] ?? []];
    }

    private function selectCamera(array $scene): array {
        $cameras = $this->index->getByType('camera');
        $idx = count($scene['characters'] ?? []) % max(1, count($cameras));
        $camId = $cameras[$idx] ?? 'CM01';
        $atom = $this->index->getAtom($camId);
        return ['id' => $camId, 'fields' => $atom['fields'] ?? []];
    }

    private function selectComposition(string $contentType): array {
        $prefer = [
            'T1_动作战斗' => 'CP02', 'T2_对话叙事' => 'CP01',
            'T4_情感回忆' => 'CP03', 'T5_对峙紧张' => 'CP04',
        ];
        $preferId = $prefer[$contentType] ?? 'CP01';
        $atom = $this->index->getAtom($preferId);
        return ['id' => $preferId, 'fields' => $atom['fields'] ?? []];
    }

    private function selectColorMapping(array $sceneAtom): array {
        $sid = $sceneAtom['id'] ?? 'SC01';
        $mappings = $this->index->getByType('color_mapping');
        foreach ($mappings as $mid) {
            $atom = $this->index->getAtom($mid);
            if ($atom && ($atom['fields']['scene_ref'] ?? '') === $sid) {
                return ['id' => $mid, 'fields' => $atom['fields']];
            }
        }
        $atom = $this->index->getAtom('CM01');
        return ['id' => 'CM01', 'fields' => $atom['fields'] ?? []];
    }
}

class Linked3_Genesis_PromptAssembler {
    private GenesisAtomIndex $index;

    public function __construct() {
        $this->index = GenesisAtomIndex::instance();
    }

    public function assemble(array $atoms, string $styleId, string $platform = 'midjourney'): array {
        return $this->assembleFull($atoms, [], $styleId, $platform);
    }

    public function assembleFull(array $atoms, array $panel, string $styleId, string $platform = 'midjourney'): array {
        $styleConfig = $this->index->getStyleConfig($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;
        $styleType = $styleConfig['style_type'] ?? 'painted'; // painted | realistic

        $styleKeywords = $styleConfig['prompt_keywords'] ?? '';
        $styleNegative = $styleConfig['negative_keywords'] ?? '';
        $styleRender = $styleConfig['render'] ?? '';
        $styleLighting = $styleConfig['lighting'] ?? '';
        $styleAtmosphere = $styleConfig['atmosphere'] ?? [];
        $styleSymbols = $styleConfig['symbols'] ?? [];
        $styleLine = $styleConfig['line_style'] ?? '';

        $charPrompts = [];
        $charDetails = [];
        foreach ($atoms['characters'] as $charId) {
            $char = $this->index->getAtom($charId);
            if (!$char) continue;
            $f = $char['fields'];

            $dnaParts = array_filter([
                $f['hair'] ?? '', $f['face'] ?? '', $f['body'] ?? '',
                $f['costume'] ?? '', $f['accessory'] ?? '',
                $f['pose'] ?? '', $f['expression'] ?? '', $f['special_mark'] ?? '',
            ]);
            $dnaStr = str_replace(['"', "'"], '', implode(', ', $dnaParts));
            $charPrompts[] = $f['prompt_kw'] . ' (' . $dnaStr . ')';

            $charDetails[] = [
                'id' => $charId,
                'role' => $f['role'] ?? '',
                'prompt_kw' => $f['prompt_kw'] ?? '',
                'detail' => $dnaStr,
            ];
        }

        $sceneFields = $atoms['scene']['fields'] ?? [];
        $sceneParts = array_filter([
            $sceneFields['scene_name'] ?? '',
            $sceneFields['location_desc'] ?? '',
            $sceneFields['weather'] ?? '',
            $sceneFields['time'] ?? '',
            $sceneFields['atmosphere'] ?? '',
        ]);
        $sceneDesc = str_replace(['"', "'"], '', implode(', ', $sceneParts));

        $actionDesc = $panel['action'] ?? '';
        $actionEn = $this->translateActionEn($actionDesc);

        $aiShot = $panel['shot'] ?? '';
        $aiAngle = $panel['angle'] ?? '';
        $cameraFields = $atoms['camera']['fields'] ?? [];
        $shotMap = ['远景' => 'wide shot', '全景' => 'full shot', '中景' => 'medium shot', '近景' => 'close-up', '特写' => 'extreme close-up', '鸟瞰' => 'bird eye view', '荷兰角' => 'dutch angle'];
        $angleMap = ['平视' => 'eye level', '仰视' => 'low angle', '俯视' => 'high angle'];
        $shotEn = $shotMap[$aiShot] ?? ($cameraFields['prompt_kw'] ?? 'medium shot');
        $angleEn = $angleMap[$aiAngle] ?? 'eye level';
        $cameraDesc = $shotEn . ' ' . $angleEn;

        $aiComp = $panel['comp'] ?? '';
        $compFields = $atoms['composition']['fields'] ?? [];
        $compMap = ['三分法' => 'rule of thirds', '对角线' => 'diagonal composition', '中心构图' => 'center composition', '对称式' => 'symmetric composition', '引导线' => 'leading lines'];
        $compEn = $compMap[$aiComp] ?? 'rule of thirds';

        $aiMood = $panel['mood'] ?? '';
        $atmoFields = $atoms['atmosphere']['fields'] ?? [];
        $moodMap = ['阴森神秘' => 'eerie mysterious', '肃杀紧张' => 'tense murderous', '恐怖压迫' => 'horror oppressive', '宿命沉重' => 'fatalistic heavy', '凄美哀婉' => 'poignant sorrowful'];
        $moodEn = $moodMap[$aiMood] ?? 'eerie mysterious';
        $atmoStr = implode(', ', $styleAtmosphere);

        $colorPalette = $styleConfig['color_palette'] ?? [];
        $colorParts = [];
        foreach (($colorPalette['primary'] ?? []) as $c) {
            $colorParts[] = $c['hex'] . ' ' . ($c['use'] ?? '');
        }
        foreach (($colorPalette['secondary'] ?? []) as $c) {
            $colorParts[] = $c['hex'] . ' ' . ($c['use'] ?? '');
        }
        foreach (($colorPalette['accent'] ?? []) as $c) {
            $colorParts[] = $c['hex'] . ' ' . ($c['use'] ?? '');
        }
        $colorDesc = 'color palette: ' . implode(', ', $colorParts);

        $allSymbols = array_unique(array_merge($styleSymbols, explode('+', $charDetails[0]['detail'] ?? '')));
        $symbolDesc = implode(', ', array_filter($allSymbols));

        $promptParts = array_filter([
            $styleKeywords,                               // 风格基底
            implode('; ', $charPrompts),                  // 角色 DNA
            $sceneDesc,                                   // 场景
            $actionEn,                                    // 动作
            $cameraDesc,                                  // 镜头
            $compEn,                                      // 构图
            $moodEn . ' ' . $atmoStr . ' atmosphere',    // 氛围
            $colorDesc,                                   // 色彩
            $styleRender,                                 // 渲染
            $styleLighting,                               // 光影
            $styleLine,                                   // 线条
            'symbols: ' . $symbolDesc,                    // 符号
        ]);
        $promptEn = implode('. ', $promptParts) . '.';

        if ($styleType === 'realistic') {
            $enhancements = [];
            if (!empty($styleConfig['camera'])) $enhancements[] = $styleConfig['camera'];
            if (!empty($styleConfig['skin_detail'])) $enhancements[] = $styleConfig['skin_detail'];
            if (!empty($styleConfig['lens_params'])) {
                $lp = $styleConfig['lens_params'];
                $enhancements[] = ($lp['focal_length'] ?? '') . ' ' . ($lp['aperture'] ?? '') . ' ' . ($lp['sensor'] ?? '');
            }
            if (!empty($enhancements)) {
                $promptEn .= '. ' . implode(', ', $enhancements);
            }
        }

        if ($styleNegative) {
            $promptEn .= ' || negative: ' . $styleNegative;
        }

        $platformParams = $this->getPlatformParams($platform);
        $promptWithParams = $this->adaptPlatform($promptEn, $platform, $platformParams);

        return [
            'prompt_en' => $promptEn,
            'prompt_with_params' => $promptWithParams,
            'style' => $styleId,
            'style_name' => $styleName,
            'style_type' => $styleType,
            'platform' => $platform,
            'platform_params' => $platformParams,
            'characters' => $charDetails,
            'scene' => $sceneFields,
            'camera' => $cameraFields,
            'composition' => $compFields,
            'atmosphere' => $atmoFields,
            'color_mapping' => $atoms['color_mapping']['fields'] ?? [],
            'template' => $atoms['template']['fields'] ?? [],
            'style_config' => $styleConfig,
            'enhanced' => $styleType === 'realistic',
        ];
    }

    private function adaptPlatform(string $prompt, string $platform, string $params): string {
        switch ($platform) {
            case 'midjourney':
                return $prompt . ' ' . $params;
            case 'sdxl':
                return '(' . $prompt . '), high quality, masterpiece, best quality' .
                    "\n[Params] Steps:30 CFG:7 Sampler:DPM++ 2M Karras Size:768x1024";
            case 'dalle':
                return 'A photorealistic image: ' . $prompt . '. The image should have a cinematic, high-quality feel.';
            default:
                return $prompt . ' ' . $params;
        }
    }

    private function translateActionEn(string $action): string {
        if (empty($action)) return '';
        $map = [
            '驱魔师' => 'exorcist', '白龙' => 'white dragon', '老道长' => 'old taoist master',
            '妖魔' => 'demon', '撑伞' => 'holding umbrella', '站在' => 'standing at',
            '现身' => 'appearing', '对峙' => 'confronting', '战斗' => 'fighting',
            '出鞘' => 'drawing sword', '施法' => 'casting spell', '封印' => 'sealing',
            '倾听' => 'listening', '回忆' => 'remembering', '飞向' => 'flying toward',
            '目送' => 'watching', '手持' => 'holding', '进入' => 'entering',
            '坐在' => 'sitting at', '对视' => 'eye contact', '逼近' => 'approaching',
            '桃木剑' => 'peach wood sword', '拂尘' => 'whisk', '符箓' => 'talisman',
            '古宅' => 'mansion', '雨夜' => 'rainy night', '内堂' => 'hall',
            '后院' => 'backyard', '屋顶' => 'rooftop', '门前' => 'doorway',
            '村民' => 'villager', '说话' => 'talking', '对话' => 'dialogue',
            '逃跑' => 'running away', '追' => 'chasing', '倒下' => 'falling',
            '抬头' => 'looking up', '转身' => 'turning around', '伸手' => 'reaching out',
            '跪下' => 'kneeling', '站起' => 'standing up', '流泪' => 'crying',
            '微笑' => 'smiling', '怒吼' => 'roaring', '低语' => 'whispering',
        ];
        $en = $action;
        foreach ($map as $zh => $e) {
            $en = str_replace($zh, $e, $en);
        }
        return $en;
    }

    private function getPlatformParams(string $platform): string {
        $params = $this->index->getByType('platform_param');
        foreach ($params as $pid) {
            $atom = $this->index->getAtom($pid);
            if ($atom && ($atom['fields']['platform'] ?? '') === $platform) {
                return $atom['fields']['params'] ?? '';
            }
        }
        return '--ar 2:3 --s 750 --style raw --no text';
    }
}

class Linked3_Genesis_PQSChecker {
    public function check(array $panel): array {
        $prompt = $panel['prompt_en'] ?? '';
        $chars = $panel['characters'] ?? [];
        $styleConfig = $panel['style_config'] ?? [];

        $checks = [];

        $ok = preg_match('/ink-wash|dark manga|chinese|cinematic|photography|portrait|fashion|documentary|hanfu|cyberpunk|steampunk|gothic|ukiyoe|zen|minimal/i', $prompt);
        $checks['PQS01'] = ['item' => '风格锚点', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 40, 'msg' => $ok ? '风格关键词存在' : '缺少风格锚点'];

        $ok = count($chars) > 0;
        $checks['PQS02'] = ['item' => '角色完整性', 'passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => count($chars) . '个角色'];

        $ok = strpos($prompt, 'color palette') !== false;
        $checks['PQS03'] = ['item' => '色彩配比', 'passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '色彩配置完整' : '色彩缺失'];

        $ok = preg_match('/rule of thirds|diagonal|center|symmetric|leading lines/i', $prompt);
        $checks['PQS04'] = ['item' => '构图有效性', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '构图有效' : '构图缺失'];

        $ok = !empty($panel['scene']);
        $checks['PQS05'] = ['item' => '场景匹配', 'passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '场景匹配' : '场景缺失'];

        $ok = strpos($prompt, 'symbols:') !== false;
        $checks['PQS06'] = ['item' => '符号准确性', 'passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '符号存在' : '符号缺失'];

        $metaCount = substr_count($prompt, ',');
        $ok = $metaCount >= 10;
        $checks['PQS07'] = ['item' => 'META标签数', 'passed' => $ok, 'score' => $ok ? 100 : 60, 'msg' => $metaCount . '个标签'];

        $ok = preg_match('/atmosphere/i', $prompt);
        $checks['PQS08'] = ['item' => '氛围一致性', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '氛围存在' : '氛围缺失'];

        $len = strlen($prompt);
        $ok = $len >= 200 && $len <= 2000;
        $checks['PQS09'] = ['item' => 'Prompt长度', 'passed' => $ok, 'score' => $ok ? 100 : 70, 'msg' => $len . '字符'];

        $ok = preg_match('/exorcist|dragon|demon|mansion|rainy|night/i', $prompt);
        $checks['PQS10'] = ['item' => '关键词覆盖', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 60, 'msg' => $ok ? '核心关键词覆盖' : '关键词不足'];

        $ok = !preg_match('/[\x{4e00}-\x{9fff}]{5,}/u', $prompt); // 无5个以上连续中文
        $checks['PQS11'] = ['item' => '平台兼容性', 'passed' => $ok, 'score' => $ok ? 100 : 60, 'msg' => $ok ? '兼容' : '中文过多'];

        $ok = count($chars) >= 1 && count($chars) <= 4;
        $checks['PQS12'] = ['item' => '角色数合理', 'passed' => $ok, 'score' => $ok ? 100 : 70, 'msg' => count($chars) . '个角色'];

        $colorCount = substr_count($prompt, '#');
        $ok = $colorCount >= 3 && $colorCount <= 10;
        $checks['PQS13'] = ['item' => '色相数合理', 'passed' => $ok, 'score' => $ok ? 100 : 60, 'msg' => $colorCount . '个色相'];

        $words = explode(' ', strtolower($prompt));
        $dups = count($words) - count(array_unique($words));
        $ok = $dups < 10;
        $checks['PQS14'] = ['item' => '无冗余重复', 'passed' => $ok, 'score' => $ok ? 100 : 70, 'msg' => $dups . '个重复'];

        $passedCount = count(array_filter($checks, fn($c) => $c['passed']));
        $totalScore = array_sum(array_column($checks, 'score')) / count($checks);

        return [
            'checks' => $checks,
            'passed' => $passedCount,
            'total' => count($checks),
            'pass_rate' => count($checks) > 0 ? round($passedCount / count($checks) * 100, 1) : 0,
            'pqs_score' => round($totalScore, 1),
        ];
    }
}

class Linked3_Genesis_StoryboardGenerator {
    private array $shots = ['远景', '中景', '近景', '特写'];
    private array $angles = ['平视', '仰视', '俯视'];
    private array $comps = ['三分法', '对角线', '中心构图', '对称式'];

    public function generate(array $scenes, int $panelsPerScene = 0): array {
        $panels = [];
        $selector = new Linked3_Genesis_AtomSelector();

        foreach ($scenes as $sc) {
            $charCount = max(1, count($sc['characters'] ?? []) ?: 1);
            $panelCount = $panelsPerScene > 0 ? $panelsPerScene : min(max($charCount + 1, 3), 5);

            for ($i = 0; $i < $panelCount; $i++) {
                $focusChar = !empty($sc['characters'])
                    ? $sc['characters'][$i % count($sc['characters'])]
                    : '';

                $panel = [
                    'panel_id' => 'P' . str_pad((string)(count($panels) + 1), 4, '0', STR_PAD_LEFT),
                    'scene_id' => $sc['id'],
                    'location' => $sc['location'],
                    'action' => $sc['action'],
                    'mood' => $sc['mood'],
                    'focus' => $focusChar,
                    'shot' => $this->shots[$i % 4],
                    'angle' => $this->angles[$i % 3],
                    'comp' => $this->comps[$i % 4],
                    'characters' => $sc['characters'] ?? [],
                    'dialogue' => $sc['dialogues'][$i] ?? '',
                ];

                $panel['atoms'] = $selector->selectForScene($sc);
                $panels[] = $panel;
            }
        }

        return $panels;
    }
}

class Linked3_Genesis_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        if (!function_exists('linked3_container')) {
            return;
        }

        $container = linked3_container();
        $container->set('genesis.atom_index', fn() => GenesisAtomIndex::instance());
        $container->set('genesis.plot_parser', fn() => new Linked3_Genesis_PlotParser());
        $container->set('genesis.atom_selector', fn() => new Linked3_Genesis_AtomSelector());
        $container->set('genesis.prompt_assembler', fn() => new Linked3_Genesis_PromptAssembler());
        $container->set('genesis.pqs_checker', fn() => new Linked3_Genesis_PQSChecker());
        $container->set('genesis.storyboard', fn() => new Linked3_Genesis_StoryboardGenerator());

        if (function_exists('linked3_dispatch')) {
            linked3_dispatch('linked3.genesis.boot', ['version' => LINKED3_VERSION]);
        }
    }
}

if (!function_exists('linked3_genesis_detect_content_type')) {
    function linked3_genesis_detect_content_type(string $text): string {
        $keywords = [
            'T1_动作战斗' => ['战斗', '对决', '打', '战', '攻击', '挥', '劈', '刺', '驱魔', '施法', '封印'],
            'T2_对话叙事' => ['说', '道', '问', '答', '对话', '讲述', '解释', '告诉'],
            'T3_警示告诫' => ['警告', '危险', '小心', '注意', '禁忌', '不可', '切勿'],
            'T4_情感回忆' => ['回忆', '记忆', '往事', '曾经', '过去', '思念', '悲伤', '泪', '哭'],
            'T5_对峙紧张' => ['对峙', '僵持', '凝视', '对视', '紧张', '沉默', '逼近'],
            'T6_回忆闪回' => ['闪回', '梦境', '幻觉', '虚幻', '浮现', '重现'],
            'T7_品牌封面' => ['封面', '海报', '宣传', 'logo', '标题', '刊头'],
        ];
        foreach ($keywords as $type => $kws) {
            foreach ($kws as $kw) {
                if (mb_strpos($text, $kw) !== false) return $type;
            }
        }
        return 'T2_对话叙事';
    }
}

if (!function_exists('linked3_genesis_detect_characters')) {
    function linked3_genesis_detect_characters(string $text): array {
        $charKw = linked3_genesis_get_character_keywords();
        $found = [];
        foreach ($charKw as $id => $kws) {
            foreach ($kws as $kw) {
                if (mb_strpos($text, $kw) !== false) {
                    $found[] = $id;
                    break;
                }
            }
        }
        return array_unique($found);
    }
}

if (!function_exists('linked3_genesis_get_character_keywords')) {
    function linked3_genesis_get_character_keywords(): array {
        return [
            'C001' => ['驱魔师', '道士', '主角', '黑衣男'],
            'C002' => ['女驱魔师', '红衣女', '女道士'],
            'C003' => ['老者', '老道', '师父', '白发老'],
            'C004' => ['白龙', '龙', '神兽'],
            'C005' => ['妖魔', '恶魔', '怪物', '邪魔', '鬼'],
            'C006' => ['孩童', '小孩', '儿童', '童'],
            'C007' => ['红衣少女', '少女', '红衣女'],
            'C008' => ['黑衣女子', '黑衣女'],
            'C009' => ['九头怪物', '九头'],
            'C010' => ['恶灵', '邪修', '邪道'],
            'C011' => ['神秘人', '兜帽', '蒙面'],
            'C012' => ['青年', '少年'],
        ];
    }
}
