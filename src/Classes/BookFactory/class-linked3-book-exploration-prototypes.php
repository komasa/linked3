<?php
/**
 * Linked3 Book Exploration Prototypes — 9大真理探索原型 (v19.1 新增)
 *
 * 嵌入来源: genesis_meta2_M2_G3 母版
 *
 * 9大原型:
 *   1. book          — 写书式探索 (通过写书发现真理)
 *   2. experimental  — 实验式探索 (通过实验发现真理)
 *   3. observational — 观察式探索 (通过观察发现真理)
 *   4. deductive     — 推演式探索 (通过逻辑推演发现真理)
 *   5. meditative    — 冥想式探索 (通过内观发现真理)
 *   6. dialogic      — 对话式探索 (通过对话发现真理)
 *   7. practical     — 实践式探索 (通过实践发现真理)
 *   8. artistic      — 艺术式探索 (通过艺术发现真理)
 *   9. computational — 计算式探索 (通过计算发现真理)
 *   10. synthetic    — 综合式探索 (多维度并行探索)
 *
 * @package Linked3\BookFactory
 * @since   19.1
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Linked3_Book_Exploration_Prototypes
 *
 * 9大真理探索原型定义。
 */
class Linked3_Book_Exploration_Prototypes {

	/**
	 * 9大原型定义。
	 *
	 * @var array
	 */
	private static $prototypes = array(

		// ═══════════════════════════════════════
		// 1. 写书式探索 (默认, 向后兼容)
		// ═══════════════════════════════════════
		'book' => array(
			'key'         => 'book',
			'name'        => '写书式探索',
			'description' => '通过写书发现真理 — 系统化知识整理与真理萃取',
			'category'    => 'knowledge',
			'best_for'    => array( '知识体系构建', '理论梳理', '学习总结', '经验沉淀' ),
			'process'     => array( '演示', '探索', '大纲', '扩写', '拼接', '审阅' ),
			'output_type' => 'book',
			'prompt_style' => '系统化、结构化、逻辑严密',
			'law_focus'   => array( 'transmissibility', 'embodiment' ),
			'icon'        => '📖',
		),

		// ═══════════════════════════════════════
		// 2. 实验式探索
		// ═══════════════════════════════════════
		'experimental' => array(
			'key'         => 'experimental',
			'name'        => '实验式探索',
			'description' => '通过实验发现真理 — 假设→验证→结论的科学方法',
			'category'    => 'scientific',
			'best_for'    => array( '科学发现', '假设验证', '因果关系', '效果测试' ),
			'process'     => array( '假设提出', '实验设计', '实验执行', '数据分析', '结论提炼', '复现验证' ),
			'output_type' => 'experiment_report',
			'prompt_style' => '严谨、可复现、数据驱动',
			'law_focus'   => array( 'falsifiability', 'evolvability' ),
			'icon'        => '🔬',
		),

		// ═══════════════════════════════════════
		// 3. 观察式探索
		// ═══════════════════════════════════════
		'observational' => array(
			'key'         => 'observational',
			'name'        => '观察式探索',
			'description' => '通过观察发现真理 — 自然主义田野观察法',
			'category'    => 'empirical',
			'best_for'    => array( '自然现象', '行为模式', '生态研究', '田野调查' ),
			'process'     => array( '观察准备', '系统观察', '记录整理', '模式识别', '规律提炼', '理论构建' ),
			'output_type' => 'observation_journal',
			'prompt_style' => '客观、细致、模式导向',
			'law_focus'   => array( 'embodiment', 'protectability' ),
			'icon'        => '👁️',
		),

		// ═══════════════════════════════════════
		// 4. 推演式探索
		// ═══════════════════════════════════════
		'deductive' => array(
			'key'         => 'deductive',
			'name'        => '推演式探索',
			'description' => '通过逻辑推演发现真理 — 数学/哲学的演绎法',
			'category'    => 'rational',
			'best_for'    => array( '数学证明', '哲学思辨', '逻辑推理', '理论推导' ),
			'process'     => array( '公理确立', '定义概念', '推演规则', '定理证明', '体系构建', '一致性检验' ),
			'output_type' => 'deductive_proof',
			'prompt_style' => '严密、形式化、无矛盾',
			'law_focus'   => array( 'falsifiability', 'transmissibility' ),
			'icon'        => '🧮',
		),

		// ═══════════════════════════════════════
		// 5. 冥想式探索
		// ═══════════════════════════════════════
		'meditative' => array(
			'key'         => 'meditative',
			'name'        => '冥想式探索',
			'description' => '通过内观发现真理 — 东方智慧的直觉路径',
			'category'    => 'introspective',
			'best_for'    => array( '意识本质', '心灵探索', '存在追问', '内在体验' ),
			'process'     => array( '静心准备', '专注内观', '觉知观察', '洞察涌现', '智慧提炼', '实践验证' ),
			'output_type' => 'meditation_insight',
			'prompt_style' => '内观、觉知、非二元',
			'law_focus'   => array( 'embodiment', 'evolvability' ),
			'icon'        => '🧘',
		),

		// ═══════════════════════════════════════
		// 6. 对话式探索
		// ═══════════════════════════════════════
		'dialogic' => array(
			'key'         => 'dialogic',
			'name'        => '对话式探索',
			'description' => '通过对话发现真理 — 苏格拉底式问答法',
			'category'    => 'social',
			'best_for'    => array( '概念澄清', '偏见破除', '共识达成', '思想碰撞' ),
			'process'     => array( '话题确立', '提问引导', '回应分析', '矛盾揭示', '共识提炼', '新知生成' ),
			'output_type' => 'dialogue_transcript',
			'prompt_style' => '追问、辩证、启发式',
			'law_focus'   => array( 'transmissibility', 'evolvability' ),
			'icon'        => '💬',
		),

		// ═══════════════════════════════════════
		// 7. 实践式探索
		// ═══════════════════════════════════════
		'practical' => array(
			'key'         => 'practical',
			'name'        => '实践式探索',
			'description' => '通过实践发现真理 — 实用主义的行知合一',
			'category'    => 'pragmatic',
			'best_for'    => array( '技能掌握', '方案验证', '经验提炼', '工具开发' ),
			'process'     => array( '需求识别', '方案设计', '实践执行', '效果评估', '经验提炼', '迭代优化' ),
			'output_type' => 'practice_report',
			'prompt_style' => '实用、迭代、效果导向',
			'law_focus'   => array( 'embodiment', 'protectability' ),
			'icon'        => '🛠️',
		),

		// ═══════════════════════════════════════
		// 8. 艺术式探索
		// ═══════════════════════════════════════
		'artistic' => array(
			'key'         => 'artistic',
			'name'        => '艺术式探索',
			'description' => '通过艺术发现真理 — 美学路径的直觉表达',
			'category'    => 'aesthetic',
			'best_for'    => array( '情感真理', '美学发现', '创意突破', '文化表达' ),
			'process'     => array( '灵感捕捉', '素材积累', '创作表达', '作品反思', '美学提炼', '真理具现' ),
			'output_type' => 'artwork_collection',
			'prompt_style' => '感性、象征、多义性',
			'law_focus'   => array( 'embodiment', 'evolvability' ),
			'icon'        => '🎨',
		),

		// ═══════════════════════════════════════
		// 9. 计算式探索
		// ═══════════════════════════════════════
		'computational' => array(
			'key'         => 'computational',
			'name'        => '计算式探索',
			'description' => '通过计算发现真理 — 计算科学的模拟与涌现',
			'category'    => 'digital',
			'best_for'    => array( '复杂系统', '模式发现', '预测建模', '涌现行为' ),
			'process'     => array( '问题形式化', '算法设计', '计算执行', '结果分析', '模式发现', '理论构建' ),
			'output_type' => 'computation_result',
			'prompt_style' => '形式化、可计算、可复现',
			'law_focus'   => array( 'falsifiability', 'transmissibility' ),
			'icon'        => '💻',
		),

		// ═══════════════════════════════════════
		// 10. 综合式探索 (多维并行)
		// ═══════════════════════════════════════
		'synthetic' => array(
			'key'         => 'synthetic',
			'name'        => '综合式探索',
			'description' => '多维度并行探索 — 复杂真理的综合路径',
			'category'    => 'meta',
			'best_for'    => array( '复杂问题', '跨学科', '系统性真理', '多维验证' ),
			'process'     => array( '维度分析', '原型组合', '并行探索', '结果整合', '综合判断', '元规律校验' ),
			'output_type' => 'synthesis_report',
			'prompt_style' => '多维、整合、系统化',
			'law_focus'   => array( 'falsifiability', 'transmissibility', 'embodiment', 'evolvability', 'protectability' ),
			'icon'        => '🌐',
		),
	);

	/**
	 * 获取所有原型。
	 *
	 * @return array
	 */
	public static function get_all() : mixed {
		return self::$prototypes;
	}

	/**
	 * 获取指定原型。
	 *
	 * @param string $key 原型key。
	 * @return array|null
	 */
	public static function get( $key ) : mixed {
		return self::$prototypes[ $key ] ?? null;
	}

	/**
	 * 获取原型key列表。
	 *
	 * @return array
	 */
	public static function get_keys() {
		return array_keys( self::$prototypes );
	}

	/**
	 * 获取原型名称映射 (供UI渲染)。
	 *
	 * @return array
	 */
	public static function get_label_map() {
		$map = array();
		foreach ( self::$prototypes as $key => $proto ) {
			$map[ $key ] = $proto['icon'] . ' ' . $proto['name'];
		}
		return $map;
	}

	/**
	 * 按类别分组获取原型。
	 *
	 * @return array
	 */
	public static function get_by_category() {
		$grouped = array();
		foreach ( self::$prototypes as $key => $proto ) {
			$category = $proto['category'];
			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}
			$grouped[ $category ][ $key ] = $proto;
		}
		return $grouped;
	}
}
