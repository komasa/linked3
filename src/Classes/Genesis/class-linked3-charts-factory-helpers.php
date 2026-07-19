<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class Linked3_Charts_Factory_Helpers
{
    public function __construct() { return Linked3_Charts_Renderer::__construct(); }

    public function compile(array $context) : mixed { return Linked3_Charts_Renderer::compile($context); }

    public function split_long_article(string $article, int $target_count) : mixed { return Linked3_Charts_Renderer::split_long_article($article, $target_count); }

    public function split_by_chinese_headers(string $article) : mixed { return Linked3_Charts_Renderer::split_by_chinese_headers($article); }

    public function split_by_paragraphs(string $article, int $target_count) : mixed { return Linked3_Charts_Renderer::split_by_paragraphs($article, $target_count); }

}
