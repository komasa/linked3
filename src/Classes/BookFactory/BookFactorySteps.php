<?php

declare(strict_types=1);
/**
 * BookFactory Steps — extracted from Book_Factory God Class (G4.3) (Facade).
 *
 * v2026.07.25: Facade+Trait refactor —
 *   - step1+step2 → BookSteps1to2Trait
 *   - step3+step4 → BookSteps3to4Trait
 *   - step5+step6+helpers → BookSteps5to6Trait
 *   This class is now a ~50-line Facade. All public method signatures
 *   (execute_step1_demo through execute_step6_review) are preserved.
 *
 * Contains the 6 step execution methods (demo→explore→outline→expand→complete→review).
 * v19.0.1: Made self-contained — owns call_ai_with_rate_limit(), rebuild_draft_incremental(),
 *          and uses Traits directly. No longer depends on BookFactory instance private methods.
 *
 * @package Linked3
 * @subpackage Classes\BookFactory
 * @since      27.5.0
 */

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) exit;

// 显式加载 Trait (与 BookFactory 保持一致)
$trait_dir = __DIR__ . '/Traits/';
require_once $trait_dir . 'OutlineMerger.php';
require_once $trait_dir . 'SectionExpander.php';
require_once $trait_dir . 'ReviewLinker.php';
require_once $trait_dir . 'CostTracker.php';
require_once $trait_dir . 'BookSteps1to2Trait.php';
require_once $trait_dir . 'BookSteps3to4Trait.php';
require_once $trait_dir . 'BookSteps5to6Trait.php';

use \Linked3\Classes\BookFactory\Traits\OutlineMerger;
use \Linked3\Classes\BookFactory\Traits\SectionExpander;
use \Linked3\Classes\BookFactory\Traits\ReviewLinker;
use \Linked3\Classes\BookFactory\Traits\CostTracker;
use \Linked3\Classes\BookFactory\Traits\BookSteps1to2Trait;
use \Linked3\Classes\BookFactory\Traits\BookSteps3to4Trait;
use \Linked3\Classes\BookFactory\Traits\BookSteps5to6Trait;

class BookFactorySteps
{
    use OutlineMerger;
    use SectionExpander;
    use ReviewLinker;
    use CostTracker;
    use BookSteps1to2Trait;
    use BookSteps3to4Trait;
    use BookSteps5to6Trait;

    /** @var float 上次AI调用时间戳 (速率控制) */
    private $last_api_call = 0;
}
