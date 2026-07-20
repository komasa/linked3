<?php

declare(strict_types=1);
/**
 * Linked3 Genesis 统一错误码与异常 v10.0.1
 *
 * 公理1实现: 错误信息熵减 — 从无限字符串收敛到有限错误码枚举
 * 解决熵增点E8: 错误处理碎片化
 *
 * 错误码规范 (6大类):
 *   E_NETWORK_xxx   网络错误 (连接/超时/DNS)
 *   E_API_xxx        AI API错误 (Key/余额/限流/模型)
 *   E_PARSE_xxx      解析错误 (剧本/JSON/结构)
 *   E_SEED_xxx       SEED错误 (不存在/锁定/类型)
 *   E_PQS_xxx        质检错误 (核心维度失败/回流耗尽)
 *   E_SYSTEM_xxx     系统错误 (内存/权限/配置)
 *   E_UNKNOWN        未知错误 (兜底)
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @version 10.0.1
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

/**
 * 统一错误码枚举
 */
class GenesisErrorCode {
    // 网络错误
    const E_NETWORK_TIMEOUT    = 'E_NETWORK_TIMEOUT';
    const E_NETWORK_CONN       = 'E_NETWORK_CONN';
    const E_NETWORK_DNS        = 'E_NETWORK_DNS';

    // AI API 错误
    const E_API_KEY_INVALID    = 'E_API_KEY_INVALID';
    const E_API_QUOTA          = 'E_API_QUOTA';
    const E_API_RATE_LIMIT     = 'E_API_RATE_LIMIT';
    const E_API_MODEL          = 'E_API_MODEL';
    const E_API_RESPONSE       = 'E_API_RESPONSE';

    // 解析错误
    const E_PARSE_SCRIPT       = 'E_PARSE_SCRIPT';
    const E_PARSE_JSON         = 'E_PARSE_JSON';
    const E_PARSE_STRUCTURE    = 'E_PARSE_STRUCTURE';

    // SEED 错误
    const E_SEED_NOT_FOUND     = 'E_SEED_NOT_FOUND';
    const E_SEED_LOCKED        = 'E_SEED_LOCKED';
    const E_SEED_TYPE          = 'E_SEED_TYPE';

    // 质检错误
    const E_PQS_CORE_FAILED    = 'E_PQS_CORE_FAILED';
    const E_PQS_RETRY_EXHAUST  = 'E_PQS_RETRY_EXHAUST';

    // 系统错误
    const E_SYSTEM_MEMORY      = 'E_SYSTEM_MEMORY';
    const E_SYSTEM_PERMISSION  = 'E_SYSTEM_PERMISSION';
    const E_SYSTEM_CONFIG      = 'E_SYSTEM_CONFIG';

    // 兜底
    const E_UNKNOWN            = 'E_UNKNOWN';

    /**
     * 错误码 → 人类可读信息映射
     */
    public static $messages = [
        self::E_NETWORK_TIMEOUT    => '网络请求超时',
        self::E_NETWORK_CONN       => '无法连接到服务器',
        self::E_NETWORK_DNS        => 'DNS解析失败',
        self::E_API_KEY_INVALID    => 'API Key 无效或未配置',
        self::E_API_QUOTA          => 'API 余额不足',
        self::E_API_RATE_LIMIT     => 'API 限流 (429)',
        self::E_API_MODEL          => 'AI 模型不可用',
        self::E_API_RESPONSE       => 'AI 响应格式异常',
        self::E_PARSE_SCRIPT       => '剧本解析失败',
        self::E_PARSE_JSON         => 'JSON 解析失败',
        self::E_PARSE_STRUCTURE    => '数据结构不符合预期',
        self::E_SEED_NOT_FOUND     => 'SEED 不存在',
        self::E_SEED_LOCKED        => 'SEED 已锁定, 不可修改',
        self::E_SEED_TYPE          => 'SEED 类型不匹配',
        self::E_PQS_CORE_FAILED    => 'PQS 核心维度校验失败',
        self::E_PQS_RETRY_EXHAUST  => 'PQS 回流重试次数耗尽',
        self::E_SYSTEM_MEMORY      => '内存不足',
        self::E_SYSTEM_PERMISSION  => '权限不足',
        self::E_SYSTEM_CONFIG      => '系统配置错误',
        self::E_UNKNOWN            => '未知错误',
    ];

    /**
     * 错误码 → HTTP状态映射 (用于AJAX响应)
     */
    public static $http_status = [
        self::E_NETWORK_TIMEOUT    => 504,
        self::E_NETWORK_CONN       => 503,
        self::E_API_KEY_INVALID    => 401,
        self::E_API_QUOTA          => 402,
        self::E_API_RATE_LIMIT     => 429,
        self::E_SYSTEM_PERMISSION  => 403,
        self::E_SYSTEM_CONFIG      => 500,
    ];

    /**
     * 错误码 → 用户建议操作映射
     */
    public static $suggestions = [
        self::E_NETWORK_TIMEOUT    => ['重试', '减少分镜数', '增加 PHP max_execution_time'],
        self::E_NETWORK_CONN       => ['检查网络连接', '查看服务器状态'],
        self::E_API_KEY_INVALID    => ['检查 API Key 配置', '重新生成 Key'],
        self::E_API_QUOTA          => ['充值 API 余额', '更换 AI 服务商'],
        self::E_API_RATE_LIMIT     => ['稍后重试', '降低并发数', '升级 API 套餐'],
        self::E_API_RESPONSE       => ['重试', '查看 AI 服务商状态页', '检查模型是否支持'],
        self::E_PARSE_SCRIPT       => ['检查剧本格式', '确保有明确的场景标记'],
        self::E_PARSE_JSON         => ['重试', '查看 PHP error_log'],
        self::E_SEED_NOT_FOUND     => ['检查 SEED ID', '刷新 SEED 列表'],
        self::E_SEED_LOCKED        => ['解锁 SEED 后再修改', '创建新版本'],
        self::E_PQS_CORE_FAILED    => ['查看失败维度', '调整剧本或 SEED'],
        self::E_PQS_RETRY_EXHAUST  => ['检查 AI 响应质量', '更换 AI 模型'],
        self::E_SYSTEM_MEMORY      => ['增加 PHP memory_limit', '减少分镜数'],
        self::E_SYSTEM_PERMISSION  => ['检查用户角色权限'],
        self::E_SYSTEM_CONFIG      => ['检查插件设置', '查看 PHP error_log'],
        self::E_UNKNOWN            => ['重试', '查看 PHP error_log', '联系技术支持'],
    ];

    /**
     * 获取错误信息
     */
    public static function message($code) : mixed {
        return isset(self::$messages[$code]) ? self::$messages[$code] : self::$messages[self::E_UNKNOWN];
    }

    /**
     * 获取HTTP状态码
     */
    public static function http_status($code) : mixed {
        return isset(self::$http_status[$code]) ? self::$http_status[$code] : 500;
    }

    /**
     * 获取建议操作
     */
    public static function suggestions($code) : mixed {
        return isset(self::$suggestions[$code]) ? self::$suggestions[$code] : self::$suggestions[self::E_UNKNOWN];
    }

    /**
     * 从异常或字符串推断错误码
     */
    public static function infer($e) : mixed {
        $msg = is_string($e) ? $e : ($e instanceof \Exception ? $e->getMessage() : '');
        $msg_lower = strtolower($msg);

        if (preg_match('/timeout|timed out/i', $msg)) return self::E_NETWORK_TIMEOUT;
        if (preg_match('/failed to fetch|network|connection refused/i', $msg)) return self::E_NETWORK_CONN;
        if (preg_match('/dns|resolve host/i', $msg)) return self::E_NETWORK_DNS;
        if (preg_match('/401|unauthorized|invalid.*key|api.*key/i', $msg)) return self::E_API_KEY_INVALID;
        if (preg_match('/402|quota|insufficient.*balance|余额/i', $msg)) return self::E_API_QUOTA;
        if (preg_match('/429|rate.*limit|too many requests/i', $msg)) return self::E_API_RATE_LIMIT;
        if (preg_match('/model.*not.*found|model.*unavailable/i', $msg)) return self::E_API_MODEL;
        if (preg_match('/json|parse|syntax/i', $msg)) return self::E_PARSE_JSON;
        if (preg_match('/seed.*not.*found|seed.*exist/i', $msg)) return self::E_SEED_NOT_FOUND;
        if (preg_match('/locked|immutable/i', $msg)) return self::E_SEED_LOCKED;
        if (preg_match('/memory|allowed.*size.*exhausted/i', $msg)) return self::E_SYSTEM_MEMORY;
        if (preg_match('/permission|forbidden|403/i', $msg)) return self::E_SYSTEM_PERMISSION;
        if (preg_match('/pqs|quality.*fail/i', $msg)) return self::E_PQS_CORE_FAILED;

        return self::E_UNKNOWN;
    }

    /**
     * 转为 AJAX 响应数组
     */
    public static function to_response($code, $detail = '', $context = []) : array {
        return [
            'success'      => false,
            'error'        => [
                'code'        => $code,
                'message'     => self::message($code),
                'detail'      => $detail,
                'suggestions' => self::suggestions($code),
                'http_status' => self::http_status($code),
                'context'     => $context,
            ],
        ];
    }
}


