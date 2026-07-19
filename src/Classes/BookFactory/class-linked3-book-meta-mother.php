<?php
/**
 * Linked3 Book MetaMother — 真理探索系统元母体引擎 (v19.1 新增)
 *
 * 嵌入来源: genesis_meta2_M2_G3 母版 (meta的meta·真理探索系统元母体)
 *
 * 核心能力:
 *   1. 探索方式分类引擎 — 根据探索意图自动推荐最佳探索原型
 *   2. 系统原型生成引擎 — 9大原型 + 自定义原型生成
 *   3. 元规律提炼引擎 — 5大元规律 (可证伪/可传递/可具现/可进化/可守护)
 *   4. 新系统创造引擎 — 按六步创造法生成新探索系统
 *
 * 双公理 (M²元母体级):
 *   公理一·元熵减: 从众多真理探索系统中萃取"探索真理的元规律"
 *   公理二·元降维: 把"探索真理"降维为"分类→生成→提炼→创造"四阶
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
 * Class Linked3_Book_MetaMother
 *
 * 真理探索系统元母体引擎。
 */
class Linked3_Book_MetaMother {

	/**
	 * 元母体版本。
	 */
	const META_VERSION = 'M2-G3';

	/**
	 * 5大元规律。
	 */
	const META_LAWS = array(
		'falsifiability' => '可证伪: 真理必须可被证伪，不可证伪的不是真理而是信仰',
		'transmissibility' => '可传递: 真理必须可被传递，不可传递的不是真理而是直觉',
		'embodiment' => '可具现: 真理必须可被具现，不可具现的不是真理而是空想',
		'evolvability' => '可进化: 真理必须可被进化，不可进化的不是真理而是教条',
		'protectability' => '可守护: 真理必须可被守护，不可守护的不是真理而是流言',
	);

	/**
	 * 4阶元流程。
	 */
	const META_STAGES = array(
		'stage1_classify' => '探索方式分类',
		'stage2_generate' => '系统原型生成',
		'stage3_extract'  => '元规律提炼',
		'stage4_create'   => '新系统创造',
	);

	/**
	 * AI 调用器。
	 *
	 * @var Linked3_Book_AI_Caller_Interface
	 */
	protected $ai_caller;

	/**
	 * 构造函数 — 依赖注入。
	 *
	 * @param Linked3_Book_AI_Caller_Interface|null $ai_caller AI 调用器。
	 */
	public function __construct( Linked3_Book_AI_Caller_Interface $ai_caller = null ) {
		$this->ai_caller = $ai_caller ?: new Linked3_Book_Default_AI_Caller();
	}

	/**
	 * 第一阶: 探索方式分类 — 根据探索意图推荐最佳探索原型。
	 *
	 * @param string $intent 探索意图 (如 "探索意识的本质")。
	 * @return array|WP_Error 返回 array('recommended'=>..., 'alternatives'=>..., 'reasoning'=>...) 或 WP_Error。
	 */
	public function classify_exploration( $intent ) : mixed {
		if ( empty( $intent ) ) {
			return new WP_Error( 'empty_intent', '探索意图不能为空' );
		}

		$prototypes = Linked3_Book_Exploration_Prototypes::get_all();
		$prototype_list = array();
		foreach ( $prototypes as $key => $proto ) {
			$prototype_list[] = "- {$key}: {$proto['name']} — {$proto['description']}";
		}

		$prompt = "你是真理探索系统元母体的第一阶·探索方式分类引擎。\n\n";
		$prompt .= "## 任务\n";
		$prompt .= "根据用户的探索意图，从以下9大探索原型中推荐最佳探索方式。\n\n";
		$prompt .= "## 9大探索原型\n";
		$prompt .= implode( "\n", $prototype_list ) . "\n\n";
		$prompt .= "## 用户探索意图\n";
		$prompt .= $intent . "\n\n";
		$prompt .= "## 输出格式 (JSON)\n";
		$prompt .= "{\n";
		$prompt .= '  "recommended": "原型key",';
		$prompt .= "\n";
		$prompt .= '  "alternatives": ["备选1", "备选2"],';
		$prompt .= "\n";
		$prompt .= '  "reasoning": "推荐理由",';
		$prompt .= "\n";
		$prompt .= '  "custom_params": {}';
		$prompt .= "\n}\n";
		$prompt .= "只输出JSON，不要解释。";

		$response = $this->ai_caller->call( $prompt, array(), array( 'stage' => 'meta_classify' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = $this->parse_json_response( $response['content'] );

		if ( is_wp_error( $parsed ) ) {
			// 回退: 基于关键词的简单分类。
			return $this->fallback_classify( $intent );
		}

		return $parsed;
	}

	/**
	 * 第二阶: 系统原型生成 — 根据原型key生成完整的探索系统配置。
	 *
	 * @param string $prototype_key 原型key (如 book/experimental/observational 等)。
	 * @param array  $custom_params 自定义参数。
	 * @return array|WP_Error
	 */
	public function generate_prototype( $prototype_key, $custom_params = array() ) {
		$prototype = Linked3_Book_Exploration_Prototypes::get( $prototype_key );

		if ( ! $prototype ) {
			return new WP_Error( 'unknown_prototype', '未知探索原型: ' . $prototype_key );
		}

		// 合并默认配置与自定义参数。
		$generated = array_merge( $prototype, $custom_params );
		$generated['generated_at'] = current_time( 'mysql' );
		$generated['meta_version'] = self::META_VERSION;

		return $generated;
	}

	/**
	 * 第三阶: 元规律提炼 — 从探索结果中提炼元规律。
	 *
	 * @param string $exploration_result 探索结果文本。
	 * @return array|WP_Error 返回 array('laws'=>..., 'compliance'=>...) 或 WP_Error。
	 */
	public function extract_meta_laws( $exploration_result ) : mixed {
		if ( empty( $exploration_result ) ) {
			return new WP_Error( 'empty_result', '探索结果不能为空' );
		}

		$laws_text = array();
		foreach ( self::META_LAWS as $key => $desc ) {
			$laws_text[] = "- {$key}: {$desc}";
		}

		$prompt = "你是真理探索系统元母体的第三阶·元规律提炼引擎。\n\n";
		$prompt .= "## 任务\n";
		$prompt .= "评估以下探索结果是否符合5大元规律，给出合规性评分。\n\n";
		$prompt .= "## 5大元规律\n";
		$prompt .= implode( "\n", $laws_text ) . "\n\n";
		$prompt .= "## 探索结果\n";
		$prompt .= mb_substr( $exploration_result, 0, 3000 ) . "\n\n";
		$prompt .= "## 输出格式 (JSON)\n";
		$prompt .= "{\n";
		$prompt .= '  "laws": {';
		$prompt .= "\n";
		foreach ( array_keys( self::META_LAWS ) as $law_key ) {
			$prompt .= '    "' . $law_key . '": {"score": 0-100, "evidence": "证据"},';
			$prompt .= "\n";
		}
		$prompt .= "  },\n";
		$prompt .= '  "overall_score": 0,';
		$prompt .= "\n";
		$prompt .= '  "suggestions": "改进建议"';
		$prompt .= "\n}\n";
		$prompt .= "只输出JSON，不要解释。";

		$response = $this->ai_caller->call( $prompt, array(), array( 'stage' => 'meta_extract' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = $this->parse_json_response( $response['content'] );

		if ( is_wp_error( $parsed ) ) {
			return array(
				'laws'          => array(),
				'overall_score' => 0,
				'suggestions'   => '元规律提炼失败，请手动评估',
			);
		}

		return $parsed;
	}

	/**
	 * 第四阶: 新系统创造 — 按六步创造法生成新探索系统。
	 *
	 * @param string $system_name 新系统名称。
	 * @param string $domain      应用领域。
	 * @return array|WP_Error
	 */
	public function create_new_system( $system_name, $domain = '' ) {
		if ( empty( $system_name ) ) {
			return new WP_Error( 'empty_name', '系统名称不能为空' );
		}

		$prompt = "你是真理探索系统元母体的第四阶·新系统创造引擎。\n\n";
		$prompt .= "## 任务\n";
		$prompt .= "按六步创造法生成新的真理探索系统。\n\n";
		$prompt .= "## 六步创造法\n";
		$prompt .= "1. 需求分析: 分析新系统要探索的真理类型\n";
		$prompt .= "2. 原型选择: 从9大原型中选择基础原型\n";
		$prompt .= "3. 参数定制: 定制系统参数\n";
		$prompt .= "4. 流程设计: 设计探索流程\n";
		$prompt .= "5. 元规律校验: 校验是否符合5大元规律\n";
		$prompt .= "6. 系统命名: 命名并归档\n\n";
		$prompt .= "## 输入\n";
		$prompt .= "系统名称: {$system_name}\n";
		$prompt .= "应用领域: {$domain}\n\n";
		$prompt .= "## 输出格式 (JSON)\n";
		$prompt .= "{\n";
		$prompt .= '  "system_name": "' . $system_name . '",';
		$prompt .= "\n";
		$prompt .= '  "base_prototype": "基础原型",';
		$prompt .= "\n";
		$prompt .= '  "parameters": {},';
		$prompt .= "\n";
		$prompt .= '  "process_steps": [],';
		$prompt .= "\n";
		$prompt .= '  "law_compliance": {},';
		$prompt .= "\n";
		$prompt .= '  "description": "系统描述"';
		$prompt .= "\n}\n";
		$prompt .= "只输出JSON，不要解释。";

		$response = $this->ai_caller->call( $prompt, array(), array( 'stage' => 'meta_create' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = $this->parse_json_response( $response['content'] );

		if ( is_wp_error( $parsed ) ) {
			return array(
				'system_name'  => $system_name,
				'description'  => '新系统创造失败，请手动设计',
				'raw_response' => $response['content'],
			);
		}

		return $parsed;
	}

	/**
	 * 获取元母体元信息。
	 *
	 * @return array
	 */
	public function get_meta_info() : array {
		return array(
			'version'       => self::META_VERSION,
			'axioms'        => array(
				'axiom_1_meta_entropy_reduction' => '元熵减: 从众多真理探索系统中萃取探索真理的元规律',
				'axiom_2_meta_dimension_reduction' => '元降维: 把探索真理降维为分类→生成→提炼→创造四阶',
			),
			'core_nucleus'  => '探索方式分类引擎 × 系统原型生成引擎 × 元规律提炼引擎 × 新系统创造引擎',
			'meta_laws'     => self::META_LAWS,
			'meta_stages'   => self::META_STAGES,
			'prototypes'    => Linked3_Book_Exploration_Prototypes::get_all(),
		);
	}

	/**
	 * 解析AI返回的JSON响应。
	 *
	 * @param string $content AI返回内容。
	 * @return array|WP_Error
	 */
	protected function parse_json_response( $content ) {
		// 移除Markdown代码块标记。
		$content = preg_replace( '/^```(?:json)?\s*/m', '', $content );
		$content = preg_replace( '/\s*```$/m', '', $content );
		$content = trim( $content );

		// 尝试提取JSON块。
		if ( preg_match( '/\{[\s\S]*\}/', $content, $m ) ) {
			$content = $m[0];
		}

		$data = json_decode( $content, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'json_parse_failed', 'JSON解析失败: ' . json_last_error_msg() );
		}

		return $data;
	}

	/**
	 * 回退分类: 基于关键词的简单分类。
	 *
	 * @param string $intent 探索意图。
	 * @return array
	 */
	protected function fallback_classify( $intent ) : array {
		$intent_lower = mb_strtolower( $intent );
		$rules = array(
			'book'           => array( '写书', '图书', '知识', '学习', '理论' ),
			'experimental'   => array( '实验', '测试', '验证', '科学' ),
			'observational'  => array( '观察', '自然', '现象', '记录' ),
			'deductive'      => array( '推演', '逻辑', '数学', '证明', '哲学' ),
			'meditative'     => array( '冥想', '内观', '意识', '禅', '心灵' ),
			'dialogic'       => array( '对话', '讨论', '辩论', '苏格拉底' ),
			'practical'      => array( '实践', '实用', '行动', '经验' ),
			'artistic'       => array( '艺术', '美学', '创作', '审美' ),
			'computational'  => array( '计算', '算法', '数据', '模拟' ),
			'synthetic'      => array( '综合', '多维', '复杂', '系统' ),
		);

		$scores = array();
		foreach ( $rules as $proto => $keywords ) {
			$score = 0;
			foreach ( $keywords as $kw ) {
				if ( false !== mb_strpos( $intent_lower, $kw ) ) {
					$score += 10;
				}
			}
			if ( $score > 0 ) {
				$scores[ $proto ] = $score;
			}
		}

		if ( empty( $scores ) ) {
			return array(
				'recommended'   => 'book',
				'alternatives'  => array( 'deductive', 'synthetic' ),
				'reasoning'     => '未匹配到明确关键词，默认推荐写书式探索',
				'custom_params' => array(),
				'fallback'      => true,
			);
		}

		arsort( $scores );
		$recommended = array_key_first( $scores );
		$alternatives = array_slice( array_keys( $scores ), 1, 2 );

		return array(
			'recommended'   => $recommended,
			'alternatives'  => $alternatives,
			'reasoning'     => sprintf( '基于关键词匹配，推荐 %s 探索方式', $recommended ),
			'custom_params' => array(),
			'fallback'      => true,
		);
	}
}
