<?php

declare(strict_types=1);
/**
 * BookFactory 大纲处理器 (v19.0 从 Book_Factory 拆分)
 *
 * 职责: 大纲处理 — 大纲合并、智能拆分、章节索引管理。
 *
 * @package Linked3\Classes\BookFactory
 * @since   19.0
 */

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) {
    exit;
}

class BookOutlineProcessor
{
    use \Linked3\Classes\BookFactory\Traits\OutlineMerger;


}
