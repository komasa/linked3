<?php
/**
 * Trait: enforce plan-level access on AJAX/REST handlers.
 *
 * v2.9.0: 本地模式所有功能对所有用户开放,require_plan 改为 no-op。
 * 保留方法签名以兼容现有调用点。
 *
 * Usage:
 *   class My_Premium_Action {
 *       use Trait_Check_Plan_Access;
 *       public function handle() : void {
 *           $this->require_plan('pro'); // 始终返回 true (v2.9.0)
 *           // ... 业务逻辑
 *       }
 *   }
 *
 * @package Linked3
 * @subpackage Traits
 */

namespace Linked3\Includes\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait Trait_Check_Plan_Access
{
    /**
     * v2.9.0: 本地模式所有功能开放,no-op 返回 true。
     *
     * @param string $required_plan 'pro' | 'premium'
     * @return true
     */
    protected function require_plan($required_plan)
    : bool {
        return true;
    }
}
