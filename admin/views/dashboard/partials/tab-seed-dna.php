<?php
/**
 * Dashboard partial: 🧬 SEED DNA 库入口 v17.2.0
 *
 * G3 R3: SEED DNA库入口加入创作中心 — 解决"左侧有SEED DNA库但创作中心找不到按钮"
 *
 * @package Linked3
 * @version 17.2.0
 */
if (!defined('ABSPATH')) exit;

// 统计SEED DNA数量
$seed_count = 0;
$seed_categories = [];
if (class_exists('Linked3_Seed_Admin') || class_exists('Linked3_Genesis_Seed_CPT')) {
    try {
        $seeds_query = new WP_Query([
            'post_type' => 'linked3_seed',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => false,
        ]);
        $seed_count = $seeds_query->found_posts;

        // 按分类统计
        $cats = ['char' => '角色', 'brand' => '品牌', 'scene' => '场景', 'prop' => '道具', 'style' => '画风', 'soul' => '灵魂风格'];
        foreach ($cats as $slug => $label) {
            $q = new WP_Query([
                'post_type' => 'linked3_seed',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [[
                    'taxonomy' => 'linked3_seed_cat',
                    'field' => 'slug',
                    'terms' => $slug,
                ]],
                'no_found_rows' => false,
            ]);
            if ($q->found_posts > 0) {
                $seed_categories[$slug] = ['label' => $label, 'count' => $q->found_posts];
            }
        }
    } catch (\Throwable $e) {}
}

$seed_list_url = admin_url('admin.php?page=linked3-seed-list');
$seed_new_url = admin_url('admin.php?page=linked3-seed-new');
?>

<h2>🧬 SEED DNA 库 <span style="font-size:12px;color:#71717A;font-weight:normal;">v17.2.0 · 角色/品牌/场景/道具/画风/灵魂</span></h2>

<div class="notice notice-info inline"><p><strong>SEED DNA:</strong> 视觉生态的基因库。角色DNA(脸型/体型/服装)、品牌DNA、场景DNA、道具DNA、画风DNA、灵魂风格DNA——所有视觉内容(图示/漫画/视频)从这里拉取SEED保持一致性。</p></div>

<!-- 统计概览 -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin:16px 0;">
    <div style="background:#fff;border:1px solid #f3f4f6;border-radius:8px;padding:14px;text-align:center;">
        <div style="font-size:28px;font-weight:700;color:#18181B;font-variant-numeric:tabular-nums;"><?php echo (int)$seed_count; ?></div>
        <div style="font-size:11px;color:#71717A;margin-top:2px;">SEED 总数</div>
    </div>
    <?php if (!empty($seed_categories)) : foreach ($seed_categories as $slug => $info) : ?>
    <div style="background:#fff;border:1px solid #f3f4f6;border-radius:8px;padding:14px;text-align:center;">
        <div style="font-size:20px;font-weight:600;color:#3F3F46;font-variant-numeric:tabular-nums;"><?php echo (int)$info['count']; ?></div>
        <div style="font-size:11px;color:#71717A;margin-top:2px;"><?php echo esc_html($info['label']); ?></div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- 操作入口 -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0;">
    <a href="<?php echo esc_url($seed_list_url); ?>" class="button button-primary">📋 管理SEED DNA库 →</a>
    <a href="<?php echo esc_url($seed_new_url); ?>" class="button">➕ 创建新SEED</a>
</div>

<?php if ($seed_count === 0) : ?>
<div class="notice notice-warning inline"><p>💡 SEED DNA库为空。创建SEED后, 视觉生态的图示/漫画/视频脚本将自动拉取SEED保持角色和画风一致性。</p></div>
<?php endif; ?>

<!-- 使用说明 -->
<div style="background:#f9fafb;border-radius:8px;padding:14px;margin-top:16px;">
    <h4 style="margin:0 0 8px 0;font-size:13px;">📖 SEED DNA 使用流程</h4>
    <div style="font-size:12px;color:#3F3F46;line-height:1.8;">
        <div>① 在此创建SEED DNA (角色/品牌/场景/道具/画风/灵魂风格)</div>
        <div>② 前往 <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual')); ?>">视觉生态</a> 生成图示/漫画/视频脚本</div>
        <div>③ 脚本生成时自动拉取SEED DNA, 保持跨分镜的角色和画风一致</div>
        <div>④ 生成结果可保存草稿或去发布</div>
    </div>
</div>
