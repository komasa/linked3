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

if ( ! defined( 'ABSPATH' ) ) exit;

trait CostTracker {

    /**
     * 计算单次调用成本 (USD)
     *
     * @param int $tokens_in
     * @param int $tokens_out
     * @return float
     */
    protected function calculate_cost( $tokens_in, $tokens_out ) : mixed {
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
    protected function log_cost_to_state( $state, $step_id, $tokens_in, $tokens_out, $cost ) : void {
        $state->log_cost( $step_id, $tokens_in, $tokens_out, $cost );
    }

    /**
     * 获取成本看板数据
     *
     * @param BookProjectState $state
     * @return array
     */
    protected function get_cost_dashboard( $state ) : array {
        $cost_log = $state->get( 'cost_log', array() );

        $total_tokens_in  = 0;
        $total_tokens_out = 0;
        $total_cost       = 0.0;
        $by_step          = array();

        foreach ( $cost_log as $entry ) {
            $total_tokens_in  += $entry['tokens_in'];
            $total_tokens_out += $entry['tokens_out'];
            $total_cost       += $entry['cost'];

            if ( ! isset( $by_step[ $entry['step_id'] ] ) ) {
                $by_step[ $entry['step_id'] ] = array(
                    'calls'       => 0,
                    'tokens_in'   => 0,
                    'tokens_out'  => 0,
                    'cost'        => 0.0,
                );
            }
            $by_step[ $entry['step_id'] ]['calls']++;
            $by_step[ $entry['step_id'] ]['tokens_in']  += $entry['tokens_in'];
            $by_step[ $entry['step_id'] ]['tokens_out'] += $entry['tokens_out'];
            $by_step[ $entry['step_id'] ]['cost']       += $entry['cost'];
        }

        $budget = $state->get( 'budget_total', 5.0 );
        $usage_percent = $budget > 0 ? round( ( $total_cost / $budget ) * 100, 1 ) : 0;

        return array(
            'total_tokens_in'  => $total_tokens_in,
            'total_tokens_out' => $total_tokens_out,
            'total_cost'       => round( $total_cost, 4 ),
            'budget'           => $budget,
            'usage_percent'    => $usage_percent,
            'by_step'          => $by_step,
            'warning'          => $usage_percent >= 80,
            'exceeded'         => $usage_percent >= 100,
        );
    }
}
