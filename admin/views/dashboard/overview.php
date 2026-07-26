<?php
if (!defined('ABSPATH')) exit;
/** @var array $overview */
/** @var array $chart */
?>

<div class="wrap linked3-admin-wrap">
    <div class="lk3-page-header">
        <h1 class="lk3-page-title"><?php echo esc_html__('Linked3 仪表盘', 'linked3'); ?></h1>
        <p class="lk3-page-subtitle">Welcome! Your plan: <strong><?php echo esc_html(ucfirst($overview['plan'])); ?></strong></p>
    </div>

    <div class="lk3-grid-auto">
        <div class="lk3-card lk3-stat-card">
            <div class="lk3-stat-label"><?php echo esc_html__('今日 Token', 'linked3'); ?></div>
            <div class="lk3-stat-value tabular-nums"><?php echo esc_html(number_format($overview['tokens_today'])); ?> <span class="lk3-stat-unit">/ <?php echo esc_html(number_format($overview['tokens_quota'])); ?></span></div>
            <div class="lk3-stat-meta">剩余 <?php echo esc_html(number_format($overview['tokens_remaining'])); ?></div>
        </div>

        <div class="lk3-card lk3-stat-card">
            <div class="lk3-stat-label"><?php echo esc_html__('近 30 天 AI 调用', 'linked3'); ?></div>
            <div class="lk3-stat-value tabular-nums"><?php echo esc_html(number_format($overview['ai_calls_30d'])); ?></div>
            <div class="lk3-stat-meta">calls</div>
        </div>

        <div class="lk3-card lk3-stat-card">
            <div class="lk3-stat-label"><?php echo esc_html__('活跃 Agent', 'linked3'); ?></div>
            <div class="lk3-stat-value tabular-nums"><?php echo esc_html((string) $overview['tasks_active']); ?></div>
            <div class="lk3-stat-meta">running</div>
        </div>

        <div class="lk3-card lk3-stat-card">
            <div class="lk3-stat-label"><?php echo esc_html__('Provider 数', 'linked3'); ?></div>
            <div class="lk3-stat-value tabular-nums"><?php echo esc_html((string) $overview['providers_configured']); ?></div>
            <div class="lk3-stat-meta">configured</div>
        </div>
    </div>

    <div class="lk3-section">
        <h2 class="lk3-section-title"><?php echo esc_html__('用量 (近 30 天)', 'linked3'); ?></h2>
        <div class="lk3-card" style="padding: var(--lk3-space-5);">
            <?php if (empty($chart)) : ?>
                <p class="lk3-text-muted"><?php echo esc_html__('暂无用量数据。', 'linked3'); ?></p>
            <?php else : ?>
                <div style="display:flex;align-items:flex-end;height:160px;gap:3px;">
                    <?php
                    $max_tokens = max(array_column($chart, 'tokens') ?: [1]);
                    foreach ($chart as $row) :
                        $height = $max_tokens > 0 ? max(2, (int) ($row['tokens'] / $max_tokens * 150)) : 2;
                    ?>
                        <div title="<?php echo esc_attr($row['d'] . ': ' . number_format($row['calls']) . ' calls, ' . number_format($row['tokens']) . ' tokens'); ?>"
                             style="flex:1;background:linear-gradient(180deg, var(--lk3-accent) 0%, var(--lk3-accent-hover) 100%);height:<?php echo esc_attr($height); ?>px;border-radius:3px 3px 0 0;transition:opacity 150ms ease;cursor:pointer;"
                             onmouseover="this.style.opacity='0.8'"
                             onmouseout="this.style.opacity='1'"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="lk3-section">
        <h2 class="lk3-section-title"><?php echo esc_html__('快捷入口', 'linked3'); ?></h2>
        <div class="lk3-grid-auto-sm">
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-content-writer')); ?>" class="lk3-btn lk3-btn-primary">✍️ 内容写作</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-publish')); ?>" class="lk3-btn lk3-btn-secondary">📤 发布目标</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-chat')); ?>" class="lk3-btn lk3-btn-secondary">💬 AI 对话</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-autogpt')); ?>" class="lk3-btn lk3-btn-secondary">🤖 AutoGPT</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-speech')); ?>" class="lk3-btn lk3-btn-secondary">🔊 语音</a>
            <a href="<?php echo esc_url(admin_url('tools.php?page=linked3-security-audit')); ?>" class="lk3-btn lk3-btn-secondary">🛡️ 安全审计</a>
        </div>
    </div>
</div>
