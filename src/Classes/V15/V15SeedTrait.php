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
     * 30 种图示 ChartDNA 完整索引。
     *
     * @return array
     */
    public function seed_chart_dna()
    : array {
        return [
            // 结构关系类 (D01-D07)
            ['dna_code' => 'D01', 'chart_name_zh' => '架构图', 'chart_name_en' => 'Architecture', 'category' => '结构关系', 'use_case' => '系统架构展示', 'prompt_template' => '绘制一张系统架构图,展示各组件层次关系'],
            ['dna_code' => 'D02', 'chart_name_zh' => '框架图', 'chart_name_en' => 'Framework', 'category' => '结构关系', 'use_case' => '方法论框架', 'prompt_template' => '绘制一张框架图,展示方法论四要素'],
            ['dna_code' => 'D03', 'chart_name_zh' => '思维导图', 'chart_name_en' => 'MindMap', 'category' => '结构关系', 'use_case' => '知识体系展开', 'prompt_template' => '绘制一张思维导图,中心主题向外展开'],
            ['dna_code' => 'D04', 'chart_name_zh' => '韦恩图', 'chart_name_en' => 'Venn', 'category' => '结构关系', 'use_case' => '集合交集分析', 'prompt_template' => '绘制一张韦恩图,展示3个集合的交集关系'],
            ['dna_code' => 'D05', 'chart_name_zh' => 'ER图', 'chart_name_en' => 'ER', 'category' => '结构关系', 'use_case' => '实体关系建模', 'prompt_template' => '绘制一张ER图,展示实体间关系'],
            ['dna_code' => 'D06', 'chart_name_zh' => '网络图', 'chart_name_en' => 'Network', 'category' => '结构关系', 'use_case' => '社交网络/拓扑', 'prompt_template' => '绘制一张网络图,展示节点连接关系'],
            ['dna_code' => 'D07', 'chart_name_zh' => '树形图', 'chart_name_en' => 'Tree', 'category' => '结构关系', 'use_case' => '组织结构/分类', 'prompt_template' => '绘制一张树形图,展示层级分类'],
            // 流程时序类 (D08-D13)
            ['dna_code' => 'D08', 'chart_name_zh' => '流程图', 'chart_name_en' => 'Flowchart', 'category' => '流程时序', 'use_case' => '操作流程', 'prompt_template' => '绘制一张流程图,展示步骤流转'],
            ['dna_code' => 'D09', 'chart_name_zh' => '时序图', 'chart_name_en' => 'Sequence', 'category' => '流程时序', 'use_case' => '交互时序', 'prompt_template' => '绘制一张时序图,展示消息传递顺序'],
            ['dna_code' => 'D10', 'chart_name_zh' => '类图', 'chart_name_en' => 'ClassDiagram', 'category' => '流程时序', 'use_case' => 'UML类关系', 'prompt_template' => '绘制一张UML类图'],
            ['dna_code' => 'D11', 'chart_name_zh' => '甘特图', 'chart_name_en' => 'Gantt', 'category' => '流程时序', 'use_case' => '项目排期', 'prompt_template' => '绘制一张甘特图,展示任务时间线'],
            ['dna_code' => 'D12', 'chart_name_zh' => '泳道图', 'chart_name_en' => 'Swimlane', 'category' => '流程时序', 'use_case' => '跨角色流程', 'prompt_template' => '绘制一张泳道图,展示各角色职责'],
            ['dna_code' => 'D13', 'chart_name_zh' => '时间线', 'chart_name_en' => 'Timeline', 'category' => '流程时序', 'use_case' => '发展历程', 'prompt_template' => '绘制一张时间线,展示关键节点'],
            // 数据分析类 (D14-D22)
            ['dna_code' => 'D14', 'chart_name_zh' => '图表', 'chart_name_en' => 'Chart', 'category' => '数据分析', 'use_case' => '数据可视化', 'prompt_template' => '绘制一张数据图表'],
            ['dna_code' => 'D15', 'chart_name_zh' => '科研绘图', 'chart_name_en' => 'Scientific', 'category' => '数据分析', 'use_case' => '实验数据', 'prompt_template' => '绘制一张科研级数据图'],
            ['dna_code' => 'D16', 'chart_name_zh' => '技术路线图', 'chart_name_en' => 'TechRoadmap', 'category' => '数据分析', 'use_case' => '技术演进', 'prompt_template' => '绘制一张技术路线图'],
            ['dna_code' => 'D17', 'chart_name_zh' => '信息图', 'chart_name_en' => 'Infographic', 'category' => '数据分析', 'use_case' => '信息传播', 'prompt_template' => '绘制一张信息图,数据+视觉'],
            ['dna_code' => 'D18', 'chart_name_zh' => '知识卡片', 'chart_name_en' => 'KnowledgeCard', 'category' => '数据分析', 'use_case' => '知识浓缩', 'prompt_template' => '绘制一张知识卡片,核心信息+视觉'],
            ['dna_code' => 'D19', 'chart_name_zh' => '金字塔', 'chart_name_en' => 'Pyramid', 'category' => '数据分析', 'use_case' => '层次优先级', 'prompt_template' => '绘制一张金字塔图,展示层次优先级'],
            ['dna_code' => 'D20', 'chart_name_zh' => '鱼骨图', 'chart_name_en' => 'Fishbone', 'category' => '数据分析', 'use_case' => '因果分析', 'prompt_template' => '绘制一张鱼骨图,分析问题根因'],
            ['dna_code' => 'D21', 'chart_name_zh' => '矩阵', 'chart_name_en' => 'Matrix', 'category' => '数据分析', 'use_case' => '多维对比', 'prompt_template' => '绘制一张矩阵图,二维对比分析'],
            ['dna_code' => 'D22', 'chart_name_zh' => '堆叠图', 'chart_name_en' => 'Stacked', 'category' => '数据分析', 'use_case' => '构成分析', 'prompt_template' => '绘制一张堆叠图,展示各部分占比'],
            // 战略分析类 (D23-D27)
            ['dna_code' => 'D23', 'chart_name_zh' => 'SWOT', 'chart_name_en' => 'SWOT', 'category' => '战略分析', 'use_case' => '优劣势分析', 'prompt_template' => '绘制一张SWOT分析图'],
            ['dna_code' => 'D24', 'chart_name_zh' => 'PEST', 'chart_name_en' => 'PEST', 'category' => '战略分析', 'use_case' => '宏观环境', 'prompt_template' => '绘制一张PEST分析图'],
            ['dna_code' => 'D25', 'chart_name_zh' => '用户画像', 'chart_name_en' => 'Persona', 'category' => '战略分析', 'use_case' => '目标用户', 'prompt_template' => '绘制一张用户画像图'],
            ['dna_code' => 'D26', 'chart_name_zh' => '用户故事', 'chart_name_en' => 'UserStory', 'category' => '战略分析', 'use_case' => '需求描述', 'prompt_template' => '绘制一张用户故事地图'],
            ['dna_code' => 'D27', 'chart_name_zh' => '精益画布', 'chart_name_en' => 'LeanCanvas', 'category' => '战略分析', 'use_case' => '商业模式', 'prompt_template' => '绘制一张精益画布'],
            // 其他 (D28-D30)
            ['dna_code' => 'D28', 'chart_name_zh' => '矩形树图', 'chart_name_en' => 'Treemap', 'category' => '其他', 'use_case' => '层级占比', 'prompt_template' => '绘制一张矩形树图'],
            ['dna_code' => 'D29', 'chart_name_zh' => '简易流程', 'chart_name_en' => 'SimpleFlowchart', 'category' => '其他', 'use_case' => '封面钩子', 'prompt_template' => '绘制一张简易流程图'],
            ['dna_code' => 'D30', 'chart_name_zh' => '辐射图', 'chart_name_en' => 'Radial', 'category' => '其他', 'use_case' => '总结升华', 'prompt_template' => '绘制一张辐射图,中心向外扩散'],
        ];
    }

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
