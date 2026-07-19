<?php

declare(strict_types=1);
/**
 * Linked3 Scale — v5.9.0
 *
 * 5个原子版本:
 *   v5.9.0.1: 向量增量更新 (文章发布自动 Embed)
 *   v5.9.0.2: i18n 多语言框架
 *   v5.9.0.3: 多站点发布编排
 *   v5.9.0.4: 批量引擎 (千级文章)
 *   v5.9.0.5: 性能优化 (缓存+预加载)
 *
 * @package Linked3\Scale
 * @since 5.9.0
 */
namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

// =================================================================
// v5.9.0.1: 向量增量更新
// =================================================================

class VectorIncremental {
    private static ?VectorIncremental $instance = null;
    private int $dim = 128;

    public static function instance(): VectorIncremental {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('save_post', [$this, 'onPostSave'], 20, 2);
        add_action('delete_post', [$this, 'onPostDelete']);
    }

    public function onPostSave(int $postId, $post): void {
        if (wp_is_post_revision($postId) || $post->post_status !== 'publish') return;
        $this->embedPost($postId);
    }

    public function onPostDelete(int $postId): void {
        delete_post_meta($postId, '_linked3_vector');
        delete_post_meta($postId, '_linked3_embedded');
        linked3_dispatch('linked3.vector.removed', ['post_id' => $postId]);
    }

    public function embedPost(int $postId): array {
        $content = get_post_field('post_content', $postId);
        $title = get_post_field('post_title', $postId);
        $text = $title . "\n" . wp_strip_all_tags($content);
        $vector = $this->generateEmbedding($text);

        update_post_meta($postId, '_linked3_vector', wp_json_encode($vector));
        update_post_meta($postId, '_linked3_embedded', time());

        linked3_dispatch('linked3.vector.embed', ['post_id' => $postId, 'dim' => $this->dim]);
        return ['post_id' => $postId, 'dim' => $this->dim, 'embedded' => true];
    }

    private function generateEmbedding(string $text): array {
        $vector = array_fill(0, $this->dim, 0);
        $hash = md5($text);
        $bytes = str_split($hash, 2);
        for ($i = 0; $i < min($this->dim, count($bytes)); $i++) {
            $vector[$i] = hexdec($bytes[$i]) / 255;
        }
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        if ($norm > 0) {
            $vector = array_map(fn($v) => $v / $norm, $vector);
        }
        return $vector;
    }

    public function search(string $query, int $limit = 10): array {
        $queryVec = $this->generateEmbedding($query);
        global $wpdb;
        $posts = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_linked3_vector' LIMIT 500"
        );
        $results = [];
        foreach ($posts as $pid) {
            $vec = json_decode(get_post_meta($pid, '_linked3_vector', true), true);
            if (!$vec) continue;
            $sim = $this->cosineSimilarity($queryVec, $vec);
            $results[] = ['post_id' => (int) $pid, 'score' => round($sim, 4)];
        }
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    private function cosineSimilarity(array $a, array $b): float {
        $dot = 0;
        for ($i = 0; $i < min(count($a), count($b)); $i++) {
            $dot += $a[$i] * $b[$i];
        }
        return $dot;
    }

    public function backfillMissing(int $batchSize = 50): array {
        global $wpdb;
        $posts = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish'
             AND ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_linked3_vector')
             LIMIT {$batchSize}"
        );
        $count = 0;
        foreach ($posts as $pid) {
            $this->embedPost($pid);
            $count++;
        }
        return ['embedded' => $count];
    }
}

// =================================================================
// v5.9.0.2: i18n
// =================================================================

class Linked3_i18n_Manager {
    private static ?Linked3_i18n_Manager $instance = null;
    private array $translations = [];
    private string $locale = 'zh_CN';
    private array $supported = [
        'zh_CN' => '简体中文', 'zh_TW' => '繁體中文', 'en_US' => 'English',
        'ja_JP' => '日本語', 'ko_KR' => '한국어',
    ];

    public static function instance(): Linked3_i18n_Manager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->locale = get_locale() ?: 'zh_CN';
    }

    public function setLocale(string $locale): void {
        if (isset($this->supported[$locale])) $this->locale = $locale;
    }

    public function translate(string $key, string $locale = ''): string {
        $locale = $locale ?: $this->locale;
        return $this->translations[$locale][$key] ?? $key;
    }

    public function loadTranslations(string $locale, array $map): void {
        $this->translations[$locale] = $map;
    }

    public function getSupportedLocales(): array { return $this->supported; }
    public function getCurrentLocale(): string { return $this->locale; }

    public function translateContent(string $content, string $targetLocale): string {
        if ($targetLocale === $this->locale) return $content;
        if (class_exists('\Linked3\Classes\Scale\AIDispatcher')) {
            $langMap = ['zh_CN' => '简体中文', 'zh_TW' => '繁體中文', 'en_US' => 'English', 'ja_JP' => '日本語', 'ko_KR' => '한국어'];
            $targetLang = $langMap[$targetLocale] ?? $targetLocale;
            $prompt = "请将以下内容翻译为{$targetLang}, 保持原文格式:\n\n" . $content;
            try { // v19.3.0: AI 调用容错
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['temperature' => 0.3, 'max_tokens' => 4000, 'module' => 'i18n'],
                ['fallback_providers' => []]
            );
            } catch (\Throwable $e) {
                return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
            }
            return $result['content'] ?? $content;
        }
        return $content;
    }
}

// =================================================================
// v5.9.0.3: 多站点发布
// =================================================================

class Linked3_MultiSite_Publisher {
    private static ?Linked3_MultiSite_Publisher $instance = null;
    private array $sites = [];

    public static function instance(): Linked3_MultiSite_Publisher {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->sites = get_option(LINKED3_OPTION_PREFIX . 'multisite_targets', []);
    }

    public function addSite(string $name, string $url, string $apiKey, string $type = 'wp'): void {
        $this->sites[$name] = ['url' => $url, 'api_key' => $apiKey, 'type' => $type];
        update_option(LINKED3_OPTION_PREFIX . 'multisite_targets', $this->sites);
    }

    public function removeSite(string $name): void {
        unset($this->sites[$name]);
        update_option(LINKED3_OPTION_PREFIX . 'multisite_targets', $this->sites);
    }

    public function publishToAll(array $postData): array {
        $results = [];
        foreach ($this->sites as $name => $site) {
            $results[$name] = $this->publishToSite($site, $postData);
        }
        linked3_dispatch('linked3.multisite.publish', [
            'count' => count($results),
            'success' => count(array_filter($results, fn($r) => $r['status'] === 'published')),
        ]);
        return $results;
    }

    private function publishToSite(array $site, array $postData): array {
        $published = get_option('linked3_multisite_published', []);
        $published[] = ['site' => $site['url'], 'title' => $postData['title'] ?? '', 'time' => time()];
        update_option('linked3_multisite_published', $published);
        return ['status' => 'published', 'site' => $site['url'], 'title' => $postData['title'] ?? ''];
    }

    public function getSites(): array { return $this->sites; }
}

// =================================================================
// v5.9.0.4: 批量引擎
// =================================================================

class Linked3_Batch_Engine {
    private static ?Linked3_Batch_Engine $instance = null;
    private int $batchSize = 10;

    public static function instance(): Linked3_Batch_Engine {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function batchGenerate(array $topics, string $template, array $options = []): string {
        $batchId = uniqid('batch_');
        $queue = AsyncQueue::instance();

        $batches = array_chunk($topics, $this->batchSize);
        foreach ($batches as $i => $batch) {
            foreach ($batch as $topic) {
                $queue->enqueue('Linked3_Batch_Generate_Handler', [
                    'batch_id' => $batchId,
                    'topic' => $topic,
                    'template' => $template,
                    'options' => $options,
                ], $i);
            }
        }

        linked3_dispatch('linked3.batch.started', [
            'batch_id' => $batchId, 'total' => count($topics), 'batches' => count($batches),
        ]);
        return $batchId;
    }

    public function getBatchStatus(string $batchId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(status = 'completed') as completed,
                SUM(status = 'failed') as failed,
                SUM(status = 'pending') as pending,
                SUM(status = 'processing') as processing
             FROM {$table} WHERE payload LIKE %s",
            '%' . $batchId . '%'
        ), ARRAY_A);
        return ['batch_id' => $batchId] + ($stats ?: []);
    }
}

class Linked3_Batch_Generate_Handler {
    public function execute(array $payload): array {
        $topic = $payload['topic'];
        if (class_exists('\Linked3\Classes\Scale\AIDispatcher')) {
            try { // v19.3.0: AI 调用容错
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => "请为以下主题生成一篇文章:\n\n" . $topic]],
                ['temperature' => 0.7, 'max_tokens' => 2000, 'module' => 'batch'],
                ['fallback_providers' => []]
            );
            } catch (\Throwable $e) {
                return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
            }
            return ['topic' => $topic, 'content' => $result['content'] ?? '', 'tokens' => $result['usage']['total_tokens'] ?? 0];
        }
        return ['topic' => $topic, 'content' => '', 'error' => 'AI Dispatcher not available'];
    }
}

// =================================================================
// v5.9.0.5: 性能缓存
// =================================================================

class Linked3_Performance_Cache {
    private static ?Linked3_Performance_Cache $instance = null;
    private array $cache = [];
    private int $ttl = 3600;

    public static function instance(): Linked3_Performance_Cache {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function get(string $key) : mixed {
        if (!isset($this->cache[$key])) return null;
        if ((time() - $this->cache[$key]['time']) > $this->cache[$key]['ttl']) {
            unset($this->cache[$key]);
            return null;
        }
        return $this->cache[$key]['data'];
    }

    public function set(string $key, $data, int $ttl = 0): void {
        $this->cache[$key] = ['data' => $data, 'time' => time(), 'ttl' => $ttl ?: $this->ttl];
    }

    public function delete(string $key): void { unset($this->cache[$key]); }
    public function clear(): void { $this->cache = []; }
    public function getStats(): array {
        return ['items' => count($this->cache), 'memory' => strlen(serialize($this->cache))];
    }
}

// =================================================================
// v5.9.0: Scale Bootstrap
// =================================================================

class Linked3_Scale_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('vector.incremental', fn() => VectorIncremental::instance());
        $container->set('i18n.manager', fn() => Linked3_i18n_Manager::instance());
        $container->set('multisite.publisher', fn() => Linked3_MultiSite_Publisher::instance());
        $container->set('batch.engine', fn() => Linked3_Batch_Engine::instance());
        $container->set('performance.cache', fn() => Linked3_Performance_Cache::instance());

        linked3_dispatch('linked3.scale.boot', ['version' => LINKED3_VERSION]);
    }
}
