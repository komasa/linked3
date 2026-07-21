<?php
if (!defined('ABSPATH')) exit;
/** @var array $overview */
/** @var array $chart */
?>
<div class="wrap">
    <h1><?php echo esc_html__('Linked3 仪表盘', 'linked3'); ?></h1>
    <p><?php echo esc_html(sprintf(__('Welcome! Your plan: %s', 'linked3'), ucfirst($overview['plan']))); ?></p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0;">
        <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('今日 Token', 'linked3'); ?></h3>
            <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html(number_format($overview['tokens_today'])); ?> / <?php echo esc_html(number_format($overview['tokens_quota'])); ?></p>
            <p style="color:#666;margin:5px 0 0;"><?php echo esc_html(sprintf(__('剩余 %d', 'linked3'), $overview['tokens_remaining'])); ?></p>
        </div>
        <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('近 30 天 AI 调用', 'linked3'); ?></h3>
            <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html(number_format($overview['ai_calls_30d'])); ?></p>
        </div>
        <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('活跃 Agent', 'linked3'); ?></h3>
            <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html((string) $overview['tasks_active']); ?></p>
        </div>
        <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('Provider 数', 'linked3'); ?></h3>
            <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html((string) $overview['providers_configured']); ?></p>
        </div>
    </div>

    <h2><?php echo esc_html__('用量(近 30 天)', 'linked3'); ?></h2>
    <div style="background:#fff;border:1px solid #e5e7eb;padding:15px;border-radius:8px;">
        <?php if (empty($chart)) : ?>
            <p style="color:#999;"><?php echo esc_html__('暂无用量数据。', 'linked3'); ?></p>
        <?php else : ?>
            <div style="display:flex;align-items:flex-end;height:150px;gap:2px;">
                <?php
                $max_tokens = max(array_column($chart, 'tokens') ?: [1]);
                foreach ($chart as $row) :
                    $height = $max_tokens > 0 ? max(2, (int) ($row['tokens'] / $max_tokens * 140)) : 2;
                ?>
                    <div title="<?php echo esc_attr($row['d'] . ': ' . number_format($row['calls']) . ' calls, ' . number_format($row['tokens']) . ' tokens'); ?>"
                         style="flex:1;background:#2563eb;height:<?php echo esc_attr($height); ?>px;border-radius:2px 2px 0 0;"></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <h2><?php echo esc_html__('快捷入口', 'linked3'); ?></h2>
    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-content-writer')); ?>" class="button"><?php echo esc_html__('内容写作', 'linked3'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-publish')); ?>" class="button"><?php echo esc_html__('发布目标', 'linked3'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-chat')); ?>" class="button"><?php echo esc_html__('AI 对话', 'linked3'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-autogpt')); ?>" class="button"><?php echo esc_html__('AutoGPT', 'linked3'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-speech')); ?>" class="button"><?php echo esc_html__('语音', 'linked3'); ?></a>
        <a href="<?php echo esc_url(admin_url('tools.php?page=linked3-security-audit')); ?>" class="button"><?php echo esc_html__('安全审计', 'linked3'); ?></a>
    </p>
</div>
