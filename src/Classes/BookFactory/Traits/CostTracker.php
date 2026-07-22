<?php

declare(strict_types=1);
/**
 * Linked3 Cost Tracker — 成本追踪
 *
 * 方案: S20 (G2成本透明化)
 * 公理: 每步记录token消耗，实时看板
 *
 * @package Linked3\BookFactory\Traits
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory\Traits;

use Linked3\Classes\BookFactory\BookProjectState;
if ( ! defined( 'ABSPATH' ) ) exit;

trait CostTracker {

    /**
     * 计算单次调用成本 (USD)
     *
     * @param int $tokens_in
     * @param int $tokens_out
     * @return float
     */
    protected function calculate_cost(int $tokens_in, int $tokens_out) : mixed {
        // 默认费率 (可配置)
        $rate_in  = 0.0000015;  // $1.5/1M tokens
        $rate_out = 0.000002;   // $2.0/1M tokens

        if ( defined( 'LINKED3_AI_COST_RATE_IN' ) ) {
            $rate_in = LINKED3_AI_COST_RATE_IN;
        }
        if ( defined( 'LINKED3_AI_COST_RATE_OUT' ) ) {
            $rate_out = LINKED3_AI_COST_RATE_OUT;
        }

        return round( $tokens_in * $rate_in + $tokens_out * $rate_out, 6 );
    }

    /**
     * 记录成本到State
     *
     * @param BookProjectState $state
     * @param string $step_id
     * @param int $tokens_in
     * @param int $tokens_out
     * @param float $cost
     */
    protected function log_cost_to_state(BookProjectState $state, string $step_id, int $tokens_in, int $tokens_out, float $cost) : void {
        $state->log_cost( $step_id, $tokens_in, $tokens_out, $cost );
    }

}
