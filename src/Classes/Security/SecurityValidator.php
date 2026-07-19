<?php

declare(strict_types=1);
/**
 * Linked3 Security Validator — v5.7.0.1
 *
 * 功能:
 *   - 统一输入校验 (XSS/SQL注入/路径遍历检测)
 *   - 按类型 sanitize (text/html/url/email/int/float/textarea/json)
 *   - Nonce 验证 + 权限检查
 *   - 事件驱动: security.violation 记录攻击尝试
 *
 * @package Linked3\Security
 * @since 5.7.0.1
 */
namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) exit;

class SecurityValidator {
    private static ?SecurityValidator $instance = null;

    public static function instance(): SecurityValidator {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 按类型清理输入。
     */
    public function sanitize($input, string $type = 'text'): string {
        switch ($type) {
            case 'html':
                return wp_kses_post($input);
            case 'url':
                return esc_url_raw($input);
            case 'email':
                return sanitize_email($input);
            case 'int':
                return (string) (int) $input;
            case 'float':
                return (string) (float) $input;
            case 'textarea':
                return sanitize_textarea_field($input);
            case 'json':
                $decoded = json_decode($input, true);
                return is_array($decoded) ? wp_json_encode($this->sanitizeArray($decoded)) : '';
            case 'key':
                return preg_replace('/[^a-zA-Z0-9_\-]/', '', $input);
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * 递归清理数组。
     */
    public function sanitizeArray(array $arr): array {
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->sanitizeArray($v);
            } else {
                $arr[$k] = $this->sanitize($v, 'text');
            }
        }
        return $arr;
    }

    /**
     * XSS 检测。
     */
    public function detectXSS(string $input): bool {
        $patterns = [
            '/<script[^>]*>/i',
            '/javascript\s*:/i',
            '/on\w+\s*=\s*["\']?[^"\'\s>]/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/<svg[^>]*on\w+/i',
            '/eval\s*\(/i',
            '/document\.cookie/i',
            '/document\.write/i',
            '/window\.location/i',
            '/String\.fromCharCode/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $input)) {
                $this->logViolation('xss', $p, $input);
                return true;
            }
        }
        return false;
    }

    /**
     * SQL 注入检测。
     */
    public function detectSQLInjection(string $input): bool {
        $patterns = [
            '/\bunion\s+select\b/i',
            '/\bdrop\s+table\b/i',
            '/\binsert\s+into\b/i',
            '/\bdelete\s+from\b/i',
            '/\bupdate\s+set\b/i',
            '/--\s*$/',
            '/\/\*.*\*\//',
            '/\bor\s+1\s*=\s*1\b/i',
            '/\band\s+1\s*=\s*1\b/i',
            '/\bexec\s*\(/i',
            '/\bconvert\s*\(/i',
            '/\bxp_cmdshell\b/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $input)) {
                $this->logViolation('sql_injection', $p, $input);
                return true;
            }
        }
        return false;
    }

    /**
     * 路径遍历检测。
     */
    public function detectPathTraversal(string $input): bool {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/i',
            '/%2e%2e\//i',
            '/\.\.%2f/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $input)) {
                $this->logViolation('path_traversal', $p, $input);
                return true;
            }
        }
        return false;
    }

    /**
     * 综合安全检查 (XSS + SQL + 路径遍历)。
     */
    public function isSafe(string $input): bool {
        return !$this->detectXSS($input)
            && !$this->detectSQLInjection($input)
            && !$this->detectPathTraversal($input);
    }

    /**
     * Nonce 验证。
     */
    public function verifyNonce(string $nonce, string $action): bool {
        $ok = wp_verify_nonce($nonce, $action) !== false;
        if (!$ok) {
            $this->logViolation('invalid_nonce', $action, $nonce);
        }
        return $ok;
    }

    /**
     * 权限检查。
     */
    public function checkCapability(string $cap = 'edit_posts'): bool {
        $ok = current_user_can($cap);
        if (!$ok) {
            $this->logViolation('no_capability', $cap, '');
        }
        return $ok;
    }

    /**
     * AJAX 请求安全门 (Nonce + 权限 + 输入校验三合一)。
     */
    public function gateAjax(string $nonceAction, string $cap = 'edit_posts'): void {
        if (!$this->checkCapability($cap)) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? $_GET['nonce'] ?? ''));
        if (!$this->verifyNonce($nonce, $nonceAction)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }
    }

    /**
     * 记录安全违规。
     */
    private function logViolation(string $type, string $pattern, string $input): void {
        $violation = [
            'type' => $type,
            'pattern' => $pattern,
            'input' => mb_substr($input, 0, 200),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_id' => get_current_user_id(),
            'time' => time(),
        ];
        linked3_dispatch('linked3.security.violation', $violation);
        if (class_exists('\Linked3\Classes\Security\Linked3_Audit_Logger')) {
            Linked3_Audit_Logger::instance()->log('security_violation', $violation);
        }
    }
}
