<?php

declare(strict_types=1);
/**
 * V15 Seed Data — 30 种图示 ChartDNA + 4 种 Seed 预设。
 *
 * @package Linked3
 * @subpackage Classes\V15
 */

namespace Linked3\Classes\V15;

if (!defined('ABSPATH')) {
    exit;
}

trait V15SeedTrait
{
    /**
     * 4 种 Seed 预设示例 (每种 3 个, 共 12 个)。
     *
     * @return array
     */
    public function seed_v15_seeds()
    : array {
        return [
            // InfoSeed (3 个)
            ['seed_id' => 'info_general_v1', 'seed_type' => 'InfoSeed', 'seed_name' => '通用信息种子', 'seed_config' => wp_json_encode(['topic' => '通用主题', 'chart_dna' => 'D18', 'content_lock' => ['核心要点'], 'lock_level' => 'Critical'])],
            ['seed_id' => 'info_tech_v1', 'seed_type' => 'InfoSeed', 'seed_name' => '科技信息种子', 'seed_config' => wp_json_encode(['topic' => '科技趋势', 'chart_dna' => 'D16', 'content_lock' => ['技术对比', '趋势预测'], 'lock_level' => 'Critical'])],
            ['seed_id' => 'info_legal_v1', 'seed_type' => 'InfoSeed', 'seed_name' => '法律信息种子', 'seed_config' => wp_json_encode(['topic' => '法律科普', 'chart_dna' => 'D21', 'content_lock' => ['法律依据', '风险点'], 'lock_level' => 'Critical'])],
            // IDSeed (3 个)
            ['seed_id' => 'id_brand_v1', 'seed_type' => 'IDSeed', 'seed_name' => '品牌视觉种子', 'seed_config' => wp_json_encode(['brand_meta' => '默认品牌', 'fixed_dna' => ['logo_position' => 'footer', 'color_ratio' => '40/15/35/10'], 'adaptive_variants' => ['light', 'dark']])],
            ['seed_id' => 'id_tech_v1', 'seed_type' => 'IDSeed', 'seed_name' => '科技博主种子', 'seed_config' => wp_json_encode(['brand_meta' => '科技品牌', 'fixed_dna' => ['logo_position' => 'header', 'color_ratio' => '60/10/20/10'], 'adaptive_variants' => ['light']])],
            ['seed_id' => 'id_ecom_v1', 'seed_type' => 'IDSeed', 'seed_name' => '电商品牌种子', 'seed_config' => wp_json_encode(['brand_meta' => '电商品牌', 'fixed_dna' => ['logo_position' => 'center', 'color_ratio' => '30/20/30/20'], 'adaptive_variants' => ['light', 'dark']])],
            // CharacterSeed (3 个)
            ['seed_id' => 'char_pro_v1', 'seed_type' => 'CharacterSeed', 'seed_name' => '专业人士角色', 'seed_config' => wp_json_encode(['cv' => 'CV01', 'ev' => 'EV01', 'pv' => 'PV01', 'sv' => 'SV01', 'content_type' => 'T1', 'lock' => 'FixedDNA'])],
            ['seed_id' => 'char_casual_v1', 'seed_type' => 'CharacterSeed', 'seed_name' => '生活达人角色', 'seed_config' => wp_json_encode(['cv' => 'CV02', 'ev' => 'EV02', 'pv' => 'PV02', 'sv' => 'SV02', 'content_type' => 'T2', 'lock' => 'FixedDNA'])],
            ['seed_id' => 'char_expert_v1', 'seed_type' => 'CharacterSeed', 'seed_name' => '行业专家角色', 'seed_config' => wp_json_encode(['cv' => 'CV03', 'ev' => 'EV03', 'pv' => 'PV03', 'sv' => 'SV03', 'content_type' => 'T3', 'lock' => 'FixedDNA'])],
            // MixSeed (3 个)
            ['seed_id' => 'mix_general_v1', 'seed_type' => 'MixSeed', 'seed_name' => '通用混合种子', 'seed_config' => wp_json_encode(['info_seed' => 'info_general_v1', 'id_seed' => 'id_brand_v1', 'character_seed' => 'char_pro_v1', 'compatibility_lock' => 'Critical'])],
            ['seed_id' => 'mix_tech_v1', 'seed_type' => 'MixSeed', 'seed_name' => '科技混合种子', 'seed_config' => wp_json_encode(['info_seed' => 'info_tech_v1', 'id_seed' => 'id_tech_v1', 'character_seed' => 'char_expert_v1', 'compatibility_lock' => 'Critical'])],
            ['seed_id' => 'mix_legal_v1', 'seed_type' => 'MixSeed', 'seed_name' => '法律混合种子', 'seed_config' => wp_json_encode(['info_seed' => 'info_legal_v1', 'id_seed' => 'id_brand_v1', 'character_seed' => 'char_pro_v1', 'compatibility_lock' => 'Critical'])],
        ];
    }
}
