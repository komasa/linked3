<?php

declare(strict_types=1);
/**
 * GenesisException — extracted from GenesisErrorCode.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisException extends \Exception {
    /** @var string 错误码 */
    protected $error_code;

    /** @var array 上下文 */
    protected $context;

    public function __construct($message = '', $error_code = GenesisErrorCode::E_UNKNOWN, $context = [], $previous = null) {
        parent::__construct($message, 0, $previous);
        $this->error_code = $error_code;
        $this->context = is_array($context) ? $context : [];
    }

    public function getErrorCode() : mixed {
        return $this->error_code;
    }

    public function getContext() : mixed {
        return $this->context;
    }

    /**
     * 从普通异常/错误转换
     */
    public static function from($e, $context = []) : mixed {
        if ($e instanceof self) {
            if (!empty($context)) {
                $e->context = array_merge($e->context, $context);
            }
            return $e;
        }
        $code = GenesisErrorCode::infer($e);
        $msg = is_string($e) ? $e : ($e instanceof \Throwable ? $e->getMessage() : '未知错误');
        return new self($msg, $code, $context, $e instanceof \Exception ? $e : null);
    }
}
