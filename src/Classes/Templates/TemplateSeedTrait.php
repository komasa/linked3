<?php
declare(strict_types=1);
/**
 * Shared starter-template seed data.
 *
 * v4.8.0: extracted the preset template definitions into a single trait so
 * both TemplateManager (option-based) and
 * ContentTemplateManager (DB-based) share the same source of
 * truth for starter templates. This resolves P1-3 (duplicate template
 * definitions diverging over time).
 *
 * The trait provides two accessors:
 *   - seed_templates_simple()  — for the option-based manager (name/type/config keys)
 *   - seed_templates_db()      — for the DB-based manager (template_name/template_type keys)
 *
 * @package Linked3
 * @subpackage Classes\Templates
 */

namespace Linked3\Classes\Templates;

if (!defined('ABSPATH')) {
    exit;
}

trait TemplateSeedTrait
{
    /**
     * The canonical list of starter templates.
     *
     * Each entry has the full prompt configuration so both managers produce
     * identical output. Keys are the option-based shape (name/type/config);
     * seed_templates_db() adapts them to the DB shape.
     *
     * @return array
     */
    private function seed_templates_canonical()
    : array {
        // v5.1.7: V15 占位符统一注入到全部 5 个模板 × 6 个 prompt 字段。
        // 每个 prompt 根据其用途注入不同的 V15 要素:
        //   title  → {mood} {culture}        (标题要契合品牌调性和目标读者)
        //   content→ {mood} {culture} {platform} (正文要适配平台和读者)
        //   meta   → {mood}                  (meta 要契合调性)
        //   keyword→ (无需 V15,只提取关键词)
        //   excerpt→ {mood}                  (摘要要契合调性)
        //   tags   → (无需 V15,只生成标签)

        // V15 后缀片段 (复用,避免重复)
        $v15_title  = "\n品牌调性: {mood}\n文化背景: {culture}";
        $v15_content= "\n品牌调性: {mood}\n目标读者: {culture}\n平台: {platform}";
        $v15_meta   = "\n品牌调性: {mood}";
        $v15_excerpt= "\n品牌调性: {mood}";

        return [
            [
                'name' => __('短文章 (600-800字)', 'linked3'),
                'type' => 'article',
                'config' => [
                    'tone' => 'professional',
                    'complexity' => 'intermediate',
                    'word_count' => 700,
                    'content_length' => 'short',
                    'seo_focus' => true,
                    'prompt_mode' => 'custom',
                    'custom_title_prompt' => "你是一位资深内容编辑。请为以下主题撰写一个 SEO 友好的文章标题。\n\n要求:\n1. 标题长度 8-15 个中文字符\n2. 必须包含主关键词,自然融入不生硬\n3. 能激发目标读者的点击欲望\n4. 避免标题党和夸大表述\n5. 适合搜索引擎结果页展示\n\n主题: {topic}\n关键词: {keywords}\n品牌调性: {mood}\n文化背景: {culture}\n\n只返回标题文本,不要解释或编号。",
                    'custom_content_prompt' => "你是一位专业的{mood}风格写手。请围绕以下主题撰写一篇 600-800 字的短文章。\n\n写作要求:\n1. 采用「总-分-总」结构:开头点明核心观点,中间分 2-3 个小节展开,结尾总结升华\n2. 使用 H2/H3 标题划分段落,每段 3-5 句话,保持节奏紧凑\n3. 自然融入关键词,密度控制在 1-2%,不要堆砌\n4. 至少包含 1 个具体案例或数据支撑论点\n5. 语言风格: {mood},面向 {culture} 读者,适合 {platform} 平台\n6. 用 Markdown 格式输出,适当使用列表和加粗\n7. 不要重复相同的内容或标题,确保文章结构完整、逻辑通顺\n\n主题: {topic}\n关键词: {keywords}\n目标字数: 600-800 字",
                    'custom_meta_prompt' => "你是一位 SEO 专家。请为以下文章撰写 meta description。\n\n要求:\n1. 长度 150-160 字符(中文约 75-80 字)\n2. 必须包含主关键词,自然融入\n3. 能概括文章核心内容,激发点击欲望\n4. 风格: {mood}\n\n标题: {title}\n关键词: {keywords}\n\n只返回 meta description 文本。",
                    'custom_keyword_prompt' => "你是一位 SEO 关键词分析师。请为以下文章提取关键词。\n\n要求:\n1. 1 个焦点关键词(搜索量最高、最核心的词)\n2. 5 个长尾关键词(更具体、转化率更高的词)\n3. 关键词应反映用户搜索意图\n\n标题: {title}\n\n格式: 焦点关键词: xxx\\n长尾关键词: 词1, 词2, 词3, 词4, 词5",
                    'custom_excerpt_prompt' => "你是一位内容编辑。请为以下文章撰写摘要。\n\n要求:\n1. 100 字以内\n2. 概括文章核心观点和价值\n3. 能吸引读者继续阅读全文\n4. 风格: {mood}\n\n标题: {title}\n\n只返回摘要文本。",
                    'custom_tags_prompt' => "你是一位内容标签专家。请为以下文章生成标签。\n\n要求:\n1. 5-8 个标签,逗号分隔\n2. 涵盖文章核心主题、相关领域、目标受众\n3. 标签应有助于 SEO 和内容分类\n4. 避免过于宽泛的标签(如「文章」「内容」)\n\n标题: {title}\n关键词: {keywords}\n\n只返回标签,逗号分隔。",
                ],
            ],
            [
                'name' => __('中等文章 (1200-1600字)', 'linked3'),
                'type' => 'article',
                'config' => [
                    'tone' => 'professional',
                    'complexity' => 'intermediate',
                    'word_count' => 1400,
                    'content_length' => 'medium',
                    'seo_focus' => true,
                    'prompt_mode' => 'custom',
                    'custom_title_prompt' => "你是一位资深内容编辑。请为以下主题撰写一个 SEO 友好的文章标题。\n\n要求:\n1. 标题长度 10-18 个中文字符\n2. 必须包含主关键词,可使用冒号/破折号增强可读性\n3. 能体现文章深度和专业性\n4. 适合搜索结果页和社交媒体分享\n5. 避免与已有标题雷同\n\n主题: {topic}\n关键词: {keywords}\n品牌调性: {mood}\n文化背景: {culture}\n\n只返回标题文本,不要解释或编号。",
                    'custom_content_prompt' => "你是一位专业的{mood}风格写手。请围绕以下主题撰写一篇 1200-1600 字的中等长度文章。\n\n写作要求:\n1. 采用「递进结构」:引言(背景+痛点) → 核心内容(3-4 个 H2 小节,逐步深入) → 案例分析 → 总结展望\n2. 每个 H2 小节 300-400 字,包含论点+论据+案例\n3. 自然融入关键词,密度 1-2%,首段和末段各出现一次\n4. 至少包含 2 个具体案例或数据点,增强说服力\n5. 语言风格: {mood},面向 {culture} 读者,适合 {platform} 平台\n6. 用 Markdown 格式输出,H2/H3 标题,适当使用列表、表格和加粗\n7. 段落之间要有过渡句,确保逻辑连贯\n8. 不要重复相同的内容或标题,确保文章结构完整\n\n主题: {topic}\n关键词: {keywords}\n目标字数: 1200-1600 字",
                    'custom_meta_prompt' => "你是一位 SEO 专家。请为以下文章撰写 meta description。\n\n要求:\n1. 长度 150-160 字符(中文约 75-80 字)\n2. 包含主关键词,概括文章 3-4 个核心要点\n3. 能体现文章深度,激发点击欲望\n4. 风格: {mood}\n\n标题: {title}\n关键词: {keywords}\n\n只返回 meta description 文本。",
                    'custom_keyword_prompt' => "你是一位 SEO 关键词分析师。请为以下文章提取关键词。\n\n要求:\n1. 1 个焦点关键词\n2. 5 个长尾关键词(包含疑问词/比较词/地域词等多样化类型)\n3. 关键词应覆盖文章不同段落的核心主题\n\n标题: {title}\n\n格式: 焦点关键词: xxx\\n长尾关键词: 词1, 词2, 词3, 词4, 词5",
                    'custom_excerpt_prompt' => "你是一位内容编辑。请为以下文章撰写摘要。\n\n要求:\n1. 100-120 字\n2. 概括文章核心观点、关键论据和价值主张\n3. 能吸引读者继续阅读全文\n4. 风格: {mood}\n\n标题: {title}\n\n只返回摘要文本。",
                    'custom_tags_prompt' => "你是一位内容标签专家。请为以下文章生成标签。\n\n要求:\n1. 5-8 个标签,逗号分隔\n2. 包含核心主题标签 + 行业领域标签 + 受众标签\n3. 标签应有助于 SEO 和内容关联推荐\n4. 避免过于宽泛或重复的标签\n\n标题: {title}\n关键词: {keywords}\n\n只返回标签,逗号分隔。",
                ],
            ],
            [
                'name' => __('长文章 (2000-2500字)', 'linked3'),
                'type' => 'article',
                'config' => [
                    'tone' => 'professional',
                    'complexity' => 'expert',
                    'word_count' => 2250,
                    'content_length' => 'long',
                    'seo_focus' => true,
                    'prompt_mode' => 'custom',
                    'custom_title_prompt' => "你是一位资深内容编辑。请为以下主题撰写一个 SEO 友好的深度文章标题。\n\n要求:\n1. 标题长度 12-20 个中文字符\n2. 必须包含主关键词,可搭配副标题增强深度感\n3. 能体现文章的权威性和系统性\n4. 适合搜索结果页、知乎/公众号等深度内容平台\n5. 避免泛泛而谈,要有具体切入点\n\n主题: {topic}\n关键词: {keywords}\n品牌调性: {mood}\n文化背景: {culture}\n\n只返回标题文本,不要解释或编号。",
                    'custom_content_prompt' => "你是一位专业的{mood}风格深度写手。请围绕以下主题撰写一篇 2000-2500 字的长文章。\n\n写作要求:\n1. 采用「分层结构」:引言(问题定义+价值主张) → 背景分析(H2) → 核心方法论(2-3 个 H2,每个含 H3 子节) → 实践案例(H2) → 趋势展望(H2) → 总结\n2. 每个 H2 小节 400-500 字,包含论点+论据+案例+数据\n3. 自然融入关键词,密度 1-2%,在引言、核心段落和总结中各出现一次\n4. 至少包含 3 个具体案例或数据点,引用权威来源\n5. 提出个性化观点和见解,非简单信息堆砌\n6. 语言风格: {mood},面向 {culture} 读者,适合 {platform} 平台\n7. 用 Markdown 格式输出,H2/H3 标题,使用列表、表格、引用块增强可读性\n8. 段落之间使用过渡句,保持逻辑连贯\n9. 不要重复相同的内容或标题,确保文章结构完整、逻辑通顺\n\n主题: {topic}\n关键词: {keywords}\n目标字数: 2000-2500 字",
                    'custom_meta_prompt' => "你是一位 SEO 专家。请为以下深度文章撰写 meta description。\n\n要求:\n1. 长度 150-160 字符(中文约 75-80 字)\n2. 包含主关键词,概括文章核心方法论和关键发现\n3. 能体现文章深度和权威性\n4. 风格: {mood}\n\n标题: {title}\n关键词: {keywords}\n\n只返回 meta description 文本。",
                    'custom_keyword_prompt' => "你是一位 SEO 关键词分析师。请为以下深度文章提取关键词。\n\n要求:\n1. 1 个焦点关键词\n2. 5 个长尾关键词(覆盖不同搜索意图:信息型/导航型/交易型)\n3. 关键词应反映文章的多层次结构\n\n标题: {title}\n\n格式: 焦点关键词: xxx\\n长尾关键词: 词1, 词2, 词3, 词4, 词5",
                    'custom_excerpt_prompt' => "你是一位内容编辑。请为以下深度文章撰写摘要。\n\n要求:\n1. 120-150 字\n2. 概括文章核心方法论、关键案例和主要结论\n3. 能体现文章的深度和价值\n4. 风格: {mood}\n\n标题: {title}\n\n只返回摘要文本。",
                    'custom_tags_prompt' => "你是一位内容标签专家。请为以下深度文章生成标签。\n\n要求:\n1. 6-8 个标签,逗号分隔\n2. 包含核心主题 + 方法论标签 + 行业标签 + 受众标签\n3. 标签应有层次感,涵盖宏观和微观维度\n4. 避免过于宽泛或重复\n\n标题: {title}\n关键词: {keywords}\n\n只返回标签,逗号分隔。",
                ],
            ],
            [
                'name' => __('产品评测', 'linked3'),
                'type' => 'review',
                'config' => [
                    'tone' => 'balanced',
                    'complexity' => 'intermediate',
                    'word_count' => 1500,
                    'content_length' => 'medium',
                    'seo_focus' => true,
                    'prompt_mode' => 'custom',
                    'custom_title_prompt' => "你是一位资深产品评测编辑。请为以下产品撰写一个 SEO 友好的评测标题。\n\n要求:\n1. 标题包含产品名 + 核心卖点/评价关键词\n2. 可使用「实测」「深度体验」「优缺点分析」等评测类用语\n3. 能体现客观公正的评测立场\n4. 适合搜索结果页和电商平台\n\n主题: {topic}\n关键词: {keywords}\n品牌调性: {mood}\n文化背景: {culture}\n\n只返回标题文本。",
                    'custom_content_prompt' => "你是一位专业的产品评测写手。请围绕以下产品撰写一篇 1500 字的深度评测文章。\n\n写作要求:\n1. 采用「对比结构」:产品概述 → 外观设计(H2) → 功能体验(H2,含优缺点对比) → 性能测试(H2,含数据) → 使用场景(H2) → 购买建议(H2)\n2. 客观分析优点和缺点,避免过度褒贬\n3. 包含实际使用体验描述,让读者有代入感\n4. 至少提供 2 个竞品对比或使用场景对比\n5. 给出明确的购买建议(推荐/观望/不推荐 + 理由)\n6. 语言风格: {mood},面向 {culture} 读者,适合 {platform} 平台\n7. 用 Markdown 格式输出,H2/H3 标题,使用表格做参数对比\n8. 不要重复相同的内容或标题\n\n主题: {topic}\n关键词: {keywords}\n目标字数: 约 1500 字",
                    'custom_meta_prompt' => "你是一位 SEO 专家。请为以下产品评测撰写 meta description。\n\n要求:\n1. 长度 150-160 字符\n2. 包含产品名和关键词,概括评测核心结论\n3. 能激发目标用户的点击欲望\n4. 风格: {mood}\n\n标题: {title}\n关键词: {keywords}\n\n只返回 meta description 文本。",
                    'custom_keyword_prompt' => "你是一位 SEO 关键词分析师。请为以下产品评测提取关键词。\n\n要求:\n1. 1 个焦点关键词(产品名+评测)\n2. 5 个长尾关键词(包含使用场景/竞品对比/价格相关词)\n\n标题: {title}\n\n格式: 焦点关键词: xxx\\n长尾关键词: 词1, 词2, 词3, 词4, 词5",
                    'custom_excerpt_prompt' => "你是一位内容编辑。请为以下产品评测撰写摘要。\n\n要求:\n1. 100-120 字\n2. 概括产品核心卖点、评测结论和购买建议\n3. 风格: {mood}\n\n标题: {title}\n\n只返回摘要文本。",
                    'custom_tags_prompt' => "你是一位内容标签专家。请为以下产品评测生成标签。\n\n要求:\n1. 5-8 个标签,逗号分隔\n2. 包含产品名 + 品类 + 评测类型 + 目标用户\n3. 有助于电商搜索和内容关联\n\n标题: {title}\n关键词: {keywords}\n\n只返回标签,逗号分隔。",
                ],
            ],
            [
                'name' => __('教程攻略', 'linked3'),
                'type' => 'howto',
                'config' => [
                    'tone' => 'instructional',
                    'complexity' => 'beginner',
                    'word_count' => 1800,
                    'content_length' => 'long',
                    'seo_focus' => true,
                    'prompt_mode' => 'custom',
                    'custom_title_prompt' => "你是一位资深教程内容编辑。请为以下主题撰写一个 SEO 友好的教程标题。\n\n要求:\n1. 标题包含关键词 + 「教程/指南/攻略/实操」等教程类用语\n2. 可标注难度级别(入门/进阶/高级)\n3. 能体现教程的实操性和可 follow 性\n4. 适合搜索结果页和知识类平台\n\n主题: {topic}\n关键词: {keywords}\n品牌调性: {mood}\n文化背景: {culture}\n\n只返回标题文本。",
                    'custom_content_prompt' => "你是一位专业的教程内容写手。请围绕以下主题撰写一篇 1800 字的教程攻略。\n\n写作要求:\n1. 采用「步骤结构」:引言(问题背景+学习目标) → 准备工作(H2) → 核心步骤(3-5 个 H2,每步含:操作说明+截图说明+注意事项) → 进阶技巧(H2) → 常见问题(H2,FAQ 格式) → 总结\n2. 每个步骤必须有明确的操作指令,读者能照做\n3. 标注关键注意事项和常见错误(用 > 引用块或 ⚠️ 标记)\n4. 至少包含 1 个实战案例或完整演示流程\n5. 语言风格: {mood},面向 {culture} 读者(新手友好),适合 {platform} 平台\n6. 用 Markdown 格式输出,H2/H3 标题,使用有序列表、代码块(如需)、引用块\n7. 不要重复相同的内容或标题\n\n主题: {topic}\n关键词: {keywords}\n目标字数: 约 1800 字",
                    'custom_meta_prompt' => "你是一位 SEO 专家。请为以下教程撰写 meta description。\n\n要求:\n1. 长度 150-160 字符\n2. 包含关键词,概括教程核心步骤和学习成果\n3. 能体现教程的实操性\n4. 风格: {mood}\n\n标题: {title}\n关键词: {keywords}\n\n只返回 meta description 文本。",
                    'custom_keyword_prompt' => "你是一位 SEO 关键词分析师。请为以下教程提取关键词。\n\n要求:\n1. 1 个焦点关键词\n2. 5 个长尾关键词(包含「怎么做/怎么用/教程/步骤」等教程类搜索词)\n\n标题: {title}\n\n格式: 焦点关键词: xxx\\n长尾关键词: 词1, 词2, 词3, 词4, 词5",
                    'custom_excerpt_prompt' => "你是一位内容编辑。请为以下教程撰写摘要。\n\n要求:\n1. 100-120 字\n2. 概括教程目标、核心步骤和适用人群\n3. 风格: {mood}\n\n标题: {title}\n\n只返回摘要文本。",
                    'custom_tags_prompt' => "你是一位内容标签专家。请为以下教程生成标签。\n\n要求:\n1. 5-8 个标签,逗号分隔\n2. 包含教程主题 + 难度级别 + 工具/技术 + 目标用户\n3. 有助于知识类搜索和教程关联\n\n标题: {title}\n关键词: {keywords}\n\n只返回标签,逗号分隔。",
                ],
            ],
        ];
    }

    /**
     * Seed templates in the option-based shape (name/type/config).
     *
     * @return array
     */
    public function seed_templates_simple() : mixed {
        return $this->seed_templates_canonical();
    }

    /**
     * Seed templates in the DB-based shape (template_name/template_type/config/is_starter).
     *
     * @return array
     */
    public function seed_templates_db() : mixed     {
        $out = [];
        foreach ($this->seed_templates_canonical() as $tpl) {
            $out[] = [
                'template_name' => $tpl['name'],
                'template_type' => $tpl['type'],
                'template_category' => 'content',
                'pipeline_stage' => '',
                'config'        => $tpl['config'],
                'is_starter'    => 1,
            ];
        }
        return $out;
    }

    /**
     * v5.1.1: Pipeline template seed data (B 类模板).
     *
     * 10 个管线模板,对应文章生产的 10 个阶段:
     *   rewrite / outline / section / keyword / title / meta / excerpt / tags / video_script / visual
     *
     * 每个模板的 config.prompt 字段存储该阶段的 prompt 模板,
     * 支持 {keyword} {topic} {title} {outline} {word_count} 等占位符。
     *
     * @return array
     */
    public function seed_pipeline_templates()
    : array {
        return [
            [
                'template_name' => __('默认改写提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'rewrite',
                'config' => ['prompt' => "你是一位专业的内容改写专家。请改写以下文章,在保持原意的基础上提升表达质量。\n\n改写要求:\n1. 保持核心观点和信息完整性不变\n2. 优化句式结构:长短句交替,避免单一句型\n3. 丰富词汇:用更精准/生动的词替换泛泛之词\n4. 改善段落衔接:增加过渡句,提升逻辑连贯性\n5. 调整语气: {tone},复杂度: {complexity}\n6. SEO 优化:保持关键词密度,改善可读性\n7. 不要逐句翻译式改写,而是理解原文后重新组织表达\n\n语气: {tone}\n复杂度: {complexity}\n\n原文:\n{content}\n\n请直接输出改写后的文章,用 Markdown 格式。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认大纲生成提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'outline',
                'config' => ['prompt' => "你是一位资深内容架构师。请为一篇约 {word_count} 字的文章设计结构化大纲。\n\n大纲设计原则:\n1. 每个章节有明确的「功能定位」(如:引出问题/展开论述/案例支撑/总结升华)\n2. 章节之间有递进关系,不是平铺罗列\n3. 每个章节标注目标字数,总字数约 {word_count}\n4. 考虑 SEO 结构:H2 包含关键词变体\n\n主题: {topic}\n关键词: {keyword}\n品牌调性: {mood}\n目标读者: {culture}\n\n请返回 JSON 数组,每个元素:\n{\"h2\": \"章节标题\", \"target_words\": 目标字数, \"function\": \"本章节功能(如:引出问题/展开论述/案例支撑)\"}"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认逐段生成提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'section',
                'config' => ['prompt' => "你是一位专业的{mood}风格写手。你正在撰写一篇关于「{topic}」的长文,现在请撰写第 {section_index} 段。\n\n段落写作要求:\n1. 本段标题: {section_title}\n2. 目标字数: {section_words} 字\n3. 每段 3-5 句话为一个意群,意群之间用过渡句衔接\n4. 自然融入关键词,不要堆砌\n5. 如果是论述段:论点→论据→案例;如果是案例段:背景→过程→结果→启示\n6. 与前文保持逻辑连贯:前文摘要 → {prev_summary}\n7. 语言风格: {mood},适合 {platform} 平台\n8. 不要重复已有内容,每段都要提供新信息或新视角\n\n请直接输出本段正文,用 Markdown 格式。不要输出 H2 标题(标题已由大纲确定)。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认关键词生成提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'keyword',
                'config' => ['prompt' => "你是一位 SEO 关键词研究专家。请围绕「{topic}」这个主题,生成 {count} 个长尾关键词。\n\n生成原则:\n1. 覆盖不同搜索意图:信息型(是什么/为什么)、导航型(哪个好/怎么选)、交易型(价格/购买)\n2. 包含疑问词变体:怎么做/如何/为什么/哪个好\n3. 包含修饰词:最好/免费/快速/简单/专业\n4. 每个关键词 4-15 个字,符合中文搜索习惯\n5. 避免重复和过于相似的词\n\n每行一个,只返回关键词,不要编号或解释。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认热词采集AI备用提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'hotword',
                'config' => ['prompt' => "你是一位热搜趋势分析师。请生成 {count} 个当前可能热门的中文搜索关键词。\n\n生成原则:\n1. 覆盖多个领域:科技/生活/财经/娱乐/健康/教育/职场\n2. 反映当前社会热点和季节性趋势\n3. 每个词 2-10 个字,简洁有力\n4. 避免过于冷门或过于宽泛的词\n5. 如果有种子词,围绕种子词展开\n\n种子词: {seed}\n\n每行一个,只返回关键词,不要编号或解释。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认标题生成提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'title',
                'config' => ['prompt' => "你是一位资深内容编辑。请为以下主题撰写一个 SEO 友好的文章标题。\n\n标题设计原则:\n1. 长度 8-15 个中文字符\n2. 必须包含主关键词,自然融入\n3. 能激发 {culture} 读者的点击欲望\n4. 体现 {mood} 的品牌调性\n5. 适合搜索引擎结果页展示\n6. 避免标题党和夸大表述\n\n主题: {topic}\n关键词: {keyword}\n\n只返回标题文本,不要解释。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认 Meta 生成提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'meta',
                'config' => ['prompt' => "你是一位 SEO 专家。请为以下文章撰写 meta description。\n\n要求:\n1. 长度 150-160 字符(中文约 75-80 字)\n2. 必须包含主关键词 {keyword},自然融入\n3. 概括文章核心内容,激发点击欲望\n4. 避免与标题重复,提供额外信息\n\n标题: {title}\n关键词: {keyword}\n\n只返回 meta description 文本。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认摘要生成提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'excerpt',
                'config' => ['prompt' => "你是一位内容编辑。请为以下文章撰写摘要。\n\n要求:\n1. 100 字以内\n2. 概括文章核心观点和价值主张\n3. 能吸引读者继续阅读全文\n4. 不要直接复制文章开头,要重新组织语言\n\n标题: {title}\n\n只返回摘要文本。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认标签生成提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'tags',
                'config' => ['prompt' => "你是一位内容标签专家。请为以下文章生成标签。\n\n要求:\n1. 5-8 个标签,逗号分隔\n2. 涵盖:核心主题 + 相关领域 + 目标受众\n3. 有助于 SEO 和内容分类推荐\n4. 避免过于宽泛(如「文章」「内容」)或过于细碎\n\n标题: {title}\n关键词: {keyword}\n\n只返回标签,逗号分隔。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认视频脚本提示词 (9页SOP)', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'video_script',
                'config' => ['prompt' => "你是一位专业的短视频脚本编剧,精通 9 页 SOP 分镜结构。请为以下主题生成一个 {duration} 秒的短视频脚本。\n\n【视觉系统 V15 8 维度】\n- 品牌: {brand}\n- 创作者签名: {signature}\n- 色彩体系: {color}\n- 风格调性: {mood}\n- 文化背景: {culture}\n- 发布平台: {platform}\n- 信息密度: {density}\n- 产品类型: {product_type}\n\n【脚本设计原则 — 写书式学习】\n1. 知识颗粒化:每个分镜聚焦一个最小知识点 (5-10 秒)\n2. 认知阶梯:从现象→问题→原理→方法→案例,层层递进\n3. 视觉锚定:每个分镜必须有具体可执行的画面描述\n4. 旁白口语化:符合 {platform} 平台观众习惯, 风格 {mood}\n5. 字幕精炼:每条字幕 8-15 字, 突出关键词\n6. 品牌闭环:开头 3 秒 hook + 结尾 CTA + 品牌色 {color} 自然融入\n\n【9 页 SOP 分镜结构】\nP01 封面钩子 (3-5s) → P02 问题定义 → P03 原理拆解 → P04 案例佐证 → P05 方法步骤 → P06 常见误区 → P07 进阶技巧 → P08 总结升华 → P09 品牌闭环 (CTA)\n\n主题: {topic}\n关键词: {keyword}\n时长: {duration} 秒\n\n【输出格式 — 严格遵守】\n返回 JSON 对象, 包含 scenes 数组:\n{\"scenes\":[{\"scene\":1,\"page\":\"P01\",\"visual\":\"画面描述\",\"narration\":\"旁白\",\"text\":\"字幕\",\"duration\":5}]}\n\n只返回 JSON, 不要 markdown 代码块标记, 不要额外说明。"],
                'is_starter' => 1,
            ],
            // v5.3.2: 对比公式 — A vs B 同维度对照 (写书式学习:通过对比建立认知锚点)
            [
                'template_name' => __('视频脚本·对比公式 (A vs B)', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'video_script',
                'config' => ['prompt' => "你是一位擅长\"对比公式\"的短视频脚本编剧。请为以下主题生成一个 {duration} 秒的对比型视频脚本。\n\n【对比公式定义】\n通过 A vs B 同维度对照 (如:协议 vs 诉讼、租房 vs 买房、Python vs Java), 帮助观众在两难选择中建立决策框架。\n\n【视觉系统 V15 8 维度】\n- 品牌: {brand} | 签名: {signature} | 色彩: {color}\n- 调性: {mood} | 文化: {culture} | 平台: {platform}\n- 密度: {density} | 产品类型: {product_type}\n\n【对比公式 9 页 SOP】\nP01 钩子: 抛出 A vs B 的两难选择 (3-5s)\nP02 定义: A 和 B 各自是什么 (8-10s)\nP03 维度1: 成本对比 (8-10s)\nP04 维度2: 收益对比 (8-10s)\nP05 维度3: 风险对比 (8-10s)\nP06 真相: 看似对立实则互补的场景 (10s)\nP07 决策树: 什么人选 A, 什么人选 B (10s)\nP08 总结: 不存在普适答案 (8s)\nP09 CTA: 关注看后续 A/B 深度评测 (5s)\n\n主题: {topic}\n关键词: {keyword}\n时长: {duration} 秒\n\n【输出格式】\n返回 JSON 对象: {\"scenes\":[{\"scene\":1,\"page\":\"P01\",\"visual\":\"画面\",\"narration\":\"旁白\",\"text\":\"字幕\",\"duration\":5}]}\n只返回 JSON, 不要额外说明。"],
                'is_starter' => 1,
            ],
            // v5.3.2: 罗列公式 — N 个方法清单 (写书式学习:通过清单建立知识地图)
            [
                'template_name' => __('视频脚本·罗列公式 (N个方法)', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'video_script',
                'config' => ['prompt' => "你是一位擅长\"罗列公式\"的短视频脚本编剧。请为以下主题生成一个 {duration} 秒的罗列型视频脚本。\n\n【罗列公式定义】\n通过 N 个并列要点 (如:5个方法/7个风险点/3个误区), 帮助观众快速建立知识地图。\n\n【视觉系统 V15 8 维度】\n- 品牌: {brand} | 签名: {signature} | 色彩: {color}\n- 调性: {mood} | 文化: {culture} | 平台: {platform}\n- 密度: {density} | 产品类型: {product_type}\n\n【罗列公式 9 页 SOP】\nP01 钩子: 抛出问题 + 预告 N 个答案 (3-5s)\nP02 全景图: N 个要点的速览图 (5s)\nP03 要点1: 详解 (8-10s)\nP04 要点2: 详解 (8-10s)\nP05 要点3: 详解 (8-10s)\nP06 要点4: 详解 (8-10s)\nP07 关键提醒: 最容易被忽视的要点 (10s)\nP08 总结: N 个要点的优先级排序 (8s)\nP09 CTA: 关注看后续每个要点的深度展开 (5s)\n\n主题: {topic}\n关键词: {keyword}\n时长: {duration} 秒\n要点数量: 5 个 (可根据时长调整 3-7 个)\n\n【输出格式】\n返回 JSON 对象: {\"scenes\":[{\"scene\":1,\"page\":\"P01\",\"visual\":\"画面\",\"narration\":\"旁白\",\"text\":\"字幕\",\"duration\":5}]}\n只返回 JSON, 不要额外说明。"],
                'is_starter' => 1,
            ],
            // v5.3.2: 步骤公式 — 1→2→3 流程 (写书式学习:通过流程建立执行能力)
            [
                'template_name' => __('视频脚本·步骤公式 (1→2→3 流程)', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'video_script',
                'config' => ['prompt' => "你是一位擅长\"步骤公式\"的短视频脚本编剧。请为以下主题生成一个 {duration} 秒的步骤型视频脚本。\n\n【步骤公式定义】\n通过 1→2→3 线性流程 (如:操作教程/部署流程/诊断步骤), 帮助观众从\"知道\"升级到\"做到\"。\n\n【视觉系统 V15 8 维度】\n- 品牌: {brand} | 签名: {signature} | 色彩: {color}\n- 调性: {mood} | 文化: {culture} | 平台: {platform}\n- 密度: {density} | 产品类型: {product_type}\n\n【步骤公式 9 页 SOP】\nP01 钩子: 抛出痛点 + 预告可执行步骤 (3-5s)\nP02 前置条件: 开始前必须准备什么 (5-8s)\nP03 步骤1: 详解 + 操作画面 (10s)\nP04 步骤2: 详解 + 操作画面 (10s)\nP05 步骤3: 详解 + 操作画面 (10s)\nP06 关键决策点: 哪一步最容易出错 (10s)\nP07 验证: 如何判断成功 (8s)\nP08 进阶: 从做到到做好 (8s)\nP09 CTA: 关注看进阶教程 (5s)\n\n主题: {topic}\n关键词: {keyword}\n时长: {duration} 秒\n\n【输出格式】\n返回 JSON 对象: {\"scenes\":[{\"scene\":1,\"page\":\"P01\",\"visual\":\"画面\",\"narration\":\"旁白\",\"text\":\"字幕\",\"duration\":5}]}\n只返回 JSON, 不要额外说明。"],
                'is_starter' => 1,
            ],
            [
                'template_name' => __('默认视觉提示词模板', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'visual',
                'config' => ['prompt' => "你是一位 V15 视觉提示词工程师。请基于以下 8 维度信息,生成一张完整的图片生成提示词。\n\n8 维度输入:\n- 品牌: {brand}\n- 签名: {signature}\n- 色彩体系: {color} (主色/辅色/中性色/点缀色)\n- 风格调性: {mood}\n- 文化背景: {culture}\n- 发布平台: {platform}\n- 信息密度: {density}\n- 产品类型: {product_type}\n\n内容主题: {topic}\n\n提示词生成要求:\n1. 输出英文提示词(Midjourney/DALL-E/Stable Diffusion 通用格式)\n2. 包含:画面主体描述 + 构图布局 + 色彩方案 + 光影氛围 + 风格参考 + 细节质感\n3. 色彩方案必须与输入的 {color} 一致\n4. 画面风格必须符合 {mood} 调性\n5. 适配 {platform} 平台的尺寸和比例要求\n6. 如果是 {product_type} 类型,确保排版结构匹配\n\n请输出完整的英文图片生成提示词。"],
                'is_starter' => 1,
            ],
            // v5.2.6: 图片插图提示词 — v5.2.8 结合 V15 视觉系统完善
            [
                'template_name' => __('默认图片插图提示词', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'image_inject',
                'config' => ['prompt' => "你是一位 V15 视觉提示词工程师。请为以下文章内容生成一张配图提示词。\n\n文章信息:\n- 主题: {topic}\n- 关键词: {keyword}\n\n品牌视觉 DNA:\n- 品牌: {brand}\n- 色彩体系: {color}\n- 风格调性: {mood}\n- 发布平台: {platform}\n\n配图要求:\n1. 图片内容与文章主题强相关,能辅助理解正文\n2. 色调和风格必须与品牌视觉 DNA 一致\n3. 适配 {platform} 平台的展示比例\n4. 避免文字过多的图片(文字由正文承担)\n5. 画面要有视觉焦点,引导读者注意力\n\n请输出英文图片生成提示词,包含:画面描述 + 构图 + 色彩 + 风格 + 光影。"],
                'is_starter' => 1,
            ],
            // v5.2.6: 图片插图位置说明 — v5.2.8 完善
            [
                'template_name' => __('默认图片位置说明', 'linked3'),
                'template_category' => 'pipeline',
                'pipeline_stage' => 'image_position',
                'config' => ['prompt' => "你是一位内容排版专家。请根据文章结构和图片数量,计算最佳配图插入位置。\n\n排版规则:\n- 1 张图: 插入在第一个 H2 标题后(帮助读者进入正文)\n- 2 张图: 第一张在引言后(吸引注意),第二张在中间 H2 后(防止视觉疲劳)\n- 3 张图+: 每隔 2-3 个 H2 标题插入一张,保持均匀间距\n- 特色图片: 单独设置为文章封面,不重复插入正文\n- 最后一张图: 距离文章结尾至少 200 字,避免结尾突兀\n\n当前图片数量: {image_count}\n文章 H2 数量: {h2_count}\n\n请返回 JSON 数组,每个元素:\n{\"image_index\": 图片序号(从0开始), \"position\": \"after_h2_N\" 或 \"after_intro\" 或 \"before_conclusion\", \"reason\": \"插入原因\"}"],
                'is_starter' => 1,
            ],
        ];
    }
}
