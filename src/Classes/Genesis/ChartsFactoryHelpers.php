<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class ChartsFactoryHelpers
{
    public function __construct() { return ChartsRenderer::__construct(); }

    public function compile(array $context) : mixed { return ChartsRenderer::compile($context); }

    public function split_long_article(string $article, int $target_count) : mixed { return ChartsRenderer::split_long_article($article, $target_count); }

    public function split_by_chinese_headers(string $article) : mixed { return ChartsRenderer::split_by_chinese_headers($article); }

    public function split_by_paragraphs(string $article, int $target_count) : mixed { return ChartsRenderer::split_by_paragraphs($article, $target_count); }

}
