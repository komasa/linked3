<?php

declare(strict_types=1);
/**
 * BookFactory 草稿构建器 (v19.0 从 Book_Factory 拆分)
 *
 * 职责: 草稿文件重建 — 将项目状态中的章节内容拼接为完整的 MD/HTML 草稿文件。
 * v18.x 中此逻辑嵌入 Book_Factory::rebuild_draft_incremental(), 约 200 行。
 *
 * @package Linked3\BookFactory
 * @since   19.0
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BookDraftBuilder
 */
class BookDraftBuilder {

	/**
	 * 增量重建草稿文件。
	 *
	 * 遍历项目状态中的章节, 将已完成的章节内容拼接为 MD/HTML 文件。
	 * 使用原子写入确保文件不会出现半写入状态。
	 *
	 * @param BookProjectState $state 项目状态。
	 * @return array|WP_Error 返回 array('md_path'=>..., 'html_path'=>...) 或 WP_Error。
	 */
	public function rebuild( $state ) : mixed {
		$project_id = $state->get( 'project_id' );
		$book_title = $state->get( 'book_title' );
		$outline    = $state->get( 'outline', array() );

		if ( empty( $outline ) ) {
			return new WP_Error( 'empty_outline', '大纲为空, 无法重建草稿' );
		}

		// 委托给 Section_Stitcher 进行拼接。
		$stitcher = new SectionStitcher();
		return $stitcher->stitch( $state );
	}

}
