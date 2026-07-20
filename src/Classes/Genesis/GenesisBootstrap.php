<?php

declare(strict_types=1);
/**
 * GenesisBootstrap — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisBootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        if (!function_exists('linked3_container')) {
            return;
        }

        $container = linked3_container();
        $container->set('genesis.atom_index', fn() => GenesisAtomIndex::instance());
        $container->set('genesis.plot_parser', fn() => new GenesisPlotParser());
        $container->set('genesis.atom_selector', fn() => new GenesisAtomSelector());
        $container->set('genesis.prompt_assembler', fn() => new GenesisPromptAssembler());
        $container->set('genesis.pqs_checker', fn() => new GenesisPQSChecker());
        $container->set('genesis.storyboard', fn() => new GenesisStoryboardGenerator());

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
