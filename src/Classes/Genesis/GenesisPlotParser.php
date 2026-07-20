<?php

declare(strict_types=1);
/**
 * Linked3_Genesis_PlotParser — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisPlotParser {
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
