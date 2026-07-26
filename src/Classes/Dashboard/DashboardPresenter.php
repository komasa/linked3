<?php
declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;

/**
 * DashboardPresenter — v30 MVP
 * Single source for tab rendering (axiom α + β)
 */
class DashboardPresenter {
    public function render(string $tab, array $context = []): string {
        $tab = sanitize_key($tab);
        $partial = LINKED3_DIR . "admin/views/dashboard/partials/tab-{$tab}.php";
        if (!file_exists($partial)) {
            return '<div class="notice notice-error"><p>' . esc_html__('未知标签', 'linked3') . '</p></div>';
        }
        extract($context, EXTR_SKIP);
        ob_start();
        include $partial;
        return (string) ob_get_clean();
    }

    public function renderSub(string $tab, string $sub, array $context = []): string {
        $path = LINKED3_DIR . "admin/views/dashboard/partials/" . sanitize_key($tab) . "-" . sanitize_key($sub) . ".php";
        if (file_exists($path)) {
            extract($context, EXTR_SKIP);
            ob_start();
            include $path;
            return (string) ob_get_clean();
        }
        return $this->render($tab, $context);
    }
}
