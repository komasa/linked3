<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class SceneAxisUtils
{
    public static function get_l1_types(): array
    {
        return [
            [
                'category_id'   => 'city_life',
                'category_name' => __('城市生活', 'linked3'),
                'scenes' => [
                    ['id' => 'street_cafe',      'name' => '街头咖啡馆', 'name_en' => 'street cafe',         'prompt_keywords' => 'urban street, cafe storefront, pedestrian flow'],
                    ['id' => 'subway_platform',  'name' => '地铁站台',   'name_en' => 'subway platform',     'prompt_keywords' => 'underground subway platform, fluorescent light, train arrival'],
                    ['id' => 'office_tower',     'name' => '办公写字楼', 'name_en' => 'office tower',        'prompt_keywords' => 'modern office tower, glass facade, workday rhythm'],
                    ['id' => 'shopping_mall',    'name' => '商场购物',   'name_en' => 'shopping mall',       'prompt_keywords' => 'shopping mall interior, storefronts, crowd flow'],
                    ['id' => 'night_market',     'name' => '夜市小吃',   'name_en' => 'night market',        'prompt_keywords' => 'night market, food stalls, neon signs'],
                    ['id' => 'rooftop_night',    'name' => '天台夜色',   'name_en' => 'rooftop at night',    'prompt_keywords' => 'city rooftop, night skyline, wind'],
                ],
            ],
            [
                'category_id'   => 'nature',
                'category_name' => __('自然风光', 'linked3'),
                'scenes' => [
                    ['id' => 'mountain_mist',    'name' => '山峦雾境',   'name_en' => 'mountain mist',       'prompt_keywords' => 'mountain range, drifting mist, dawn light'],
                    ['id' => 'coastal_cliff',    'name' => '海岸悬崖',   'name_en' => 'coastal cliff',       'prompt_keywords' => 'coastal cliff, crashing waves, sea spray'],
                    ['id' => 'grassland_sunset', 'name' => '草原日落',   'name_en' => 'grassland sunset',    'prompt_keywords' => 'vast grassland, golden sunset, lone tree'],
                    ['id' => 'desert_stars',     'name' => '沙漠星空',   'name_en' => 'desert starscape',    'prompt_keywords' => 'desert dunes, milky way, profound silence'],
                    ['id' => 'snowy_dawn',       'name' => '雪山黎明',   'name_en' => 'snowy dawn',          'prompt_keywords' => 'snow mountain, dawn alpenglow, untouched snow'],
                    ['id' => 'forest_path',      'name' => '森林小径',   'name_en' => 'forest path',         'prompt_keywords' => 'forest trail, sunbeams through canopy, moss'],
                    ['id' => 'rainforest_falls', 'name' => '雨林瀑布',   'name_en' => 'rainforest falls',    'prompt_keywords' => 'tropical rainforest, waterfall, misty spray'],
                ],
            ],
            [
                'category_id'   => 'interior',
                'category_name' => __('室内居家', 'linked3'),
                'scenes' => [
                    ['id' => 'living_room',      'name' => '客厅',       'name_en' => 'living room',         'prompt_keywords' => 'cozy living room, sofa, warm lamp light'],
                    ['id' => 'kitchen',          'name' => '厨房',       'name_en' => 'kitchen',             'prompt_keywords' => 'home kitchen, utensils, morning sunlight'],
                    ['id' => 'bedroom',          'name' => '卧室',       'name_en' => 'bedroom',             'prompt_keywords' => 'quiet bedroom, made bed, soft curtain light'],
                    ['id' => 'study_room',       'name' => '书房',       'name_en' => 'study room',          'prompt_keywords' => 'study room, bookshelves, desk lamp'],
                    ['id' => 'bathroom',         'name' => '浴室',       'name_en' => 'bathroom',            'prompt_keywords' => 'clean bathroom, mirror, steam haze'],
                    ['id' => 'balcony',          'name' => '阳台',       'name_en' => 'balcony',             'prompt_keywords' => 'apartment balcony, potted plants, evening sun'],
                ],
            ],
            [
                'category_id'   => 'commercial',
                'category_name' => __('商业空间', 'linked3'),
                'scenes' => [
                    ['id' => 'fine_dining',      'name' => '高级餐厅',   'name_en' => 'fine dining',         'prompt_keywords' => 'fine dining restaurant, candlelight, white tablecloth'],
                    ['id' => 'boutique_hotel',   'name' => '精品酒店',   'name_en' => 'boutique hotel',      'prompt_keywords' => 'boutique hotel lobby, designer furniture, ambient light'],
                    ['id' => 'indie_bookstore',  'name' => '独立书店',   'name_en' => 'indie bookstore',     'prompt_keywords' => 'indie bookstore, wood shelves, reading nook'],
                    ['id' => 'bar_lounge',       'name' => '酒吧',       'name_en' => 'bar lounge',          'prompt_keywords' => 'dim bar, bottles on shelf, neon backlight'],
                    ['id' => 'gym_fitness',      'name' => '健身房',     'name_en' => 'gym',                 'prompt_keywords' => 'modern gym, weights, mirror wall'],
                    ['id' => 'cafe_workspace',   'name' => '咖啡馆',     'name_en' => 'cafe workspace',      'prompt_keywords' => 'cozy cafe, laptop, latte art'],
                ],
            ],
            [
                'category_id'   => 'cultural',
                'category_name' => __('文化场所', 'linked3'),
                'scenes' => [
                    ['id' => 'museum_hall',      'name' => '博物馆',     'name_en' => 'museum hall',         'prompt_keywords' => 'museum gallery, exhibits, spotlight'],
                    ['id' => 'theater_stage',    'name' => '剧院',       'name_en' => 'theater stage',       'prompt_keywords' => 'theater stage, red curtain, spotlight beam'],
                    ['id' => 'library_reading',  'name' => '图书馆',     'name_en' => 'library reading room','prompt_keywords' => 'grand library, reading desks, green lamp light'],
                    ['id' => 'cathedral',        'name' => '教堂',       'name_en' => 'cathedral',           'prompt_keywords' => 'gothic cathedral, stained glass, nave light'],
                    ['id' => 'temple_censer',    'name' => '寺庙',       'name_en' => 'temple',              'prompt_keywords' => 'eastern temple, incense smoke, red lantern'],
                    ['id' => 'art_gallery',      'name' => '美术馆',     'name_en' => 'art gallery',         'prompt_keywords' => 'white cube gallery, framed art, polished floor'],
                ],
            ],
            [
                'category_id'   => 'outdoor',
                'category_name' => __('户外活动', 'linked3'),
                'scenes' => [
                    ['id' => 'city_park',        'name' => '城市公园',   'name_en' => 'city park',           'prompt_keywords' => 'urban park, lawn, tree-lined path'],
                    ['id' => 'central_square',   'name' => '中央广场',   'name_en' => 'central square',      'prompt_keywords' => 'city central square, fountain, crowd'],
                    ['id' => 'stadium_crowd',    'name' => '体育场',     'name_en' => 'stadium',             'prompt_keywords' => 'sports stadium, tiered seats, floodlights'],
                    ['id' => 'campus_quad',      'name' => '校园',       'name_en' => 'campus quad',         'prompt_keywords' => 'university campus, quadrangle, students'],
                    ['id' => 'amusement_park',   'name' => '游乐场',     'name_en' => 'amusement park',      'prompt_keywords' => 'amusement park, carousel, fairy lights'],
                    ['id' => 'seaside_promenade','name' => '海滨长廊',   'name_en' => 'seaside promenade',   'prompt_keywords' => 'seaside promenade, railings, sunset glow'],
                ],
            ],
            [
                'category_id'   => 'industrial',
                'category_name' => __('工业科技', 'linked3'),
                'scenes' => [
                    ['id' => 'modern_factory',   'name' => '现代工厂',   'name_en' => 'modern factory',      'prompt_keywords' => 'clean modern factory, robotic arms, production line'],
                    ['id' => 'science_lab',      'name' => '科学实验室', 'name_en' => 'science lab',         'prompt_keywords' => 'research lab, fume hood, glass beakers'],
                    ['id' => 'data_center',      'name' => '数据中心',   'name_en' => 'data center',         'prompt_keywords' => 'server room, blue LED rows, raised floor'],
                    ['id' => 'construction_site','name' => '建筑工地',   'name_en' => 'construction site',   'prompt_keywords' => 'construction site, tower crane, scaffolding'],
                    ['id' => 'port_dock',        'name' => '港口码头',   'name_en' => 'port dock',           'prompt_keywords' => 'cargo port, container stacks, gantry crane'],
                    ['id' => 'space_launchpad',  'name' => '航天发射场', 'name_en' => 'space launchpad',     'prompt_keywords' => 'rocket launchpad, gantry tower, dusk horizon'],
                    ['id' => 'smart_warehouse',  'name' => '智能仓储',   'name_en' => 'smart warehouse',     'prompt_keywords' => 'automated warehouse, AGV robots, rack aisles'],
                ],
            ],
        ];
    }

    public static function get_l1_flat(): array
    {
        $flat = [];
        foreach (self::get_l1_types() as $cat) {
            foreach ($cat['scenes'] as $s) {
                $flat[] = array_merge($s, [
                    'category_id'   => $cat['category_id'],
                    'category_name' => $cat['category_name'],
                ]);
            }
        }
        return $flat;
    }

    public static function get_l2_columns(): array
    {
        return [
            [
                'id'                  => 'healing',
                'name'                => __('治愈系', 'linked3'),
                'meta_signature'      => 'soft healing aesthetic, gentle pastel, slow tempo, restorative mood',
                'prompt_skeleton_id'  => 'watercolor_soft',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#F5E6D3'],
                    ['role' => 'secondary', 'hex' => '#B5C9C4'],
                    ['role' => 'accent',    'hex' => '#E8A87C'],
                ],
            ],
            [
                'id'                  => 'humor',
                'name'                => __('幽默系', 'linked3'),
                'meta_signature'      => 'playful comic timing, exaggerated expressions, witty visual gag',
                'prompt_skeleton_id'  => 'fashion_editorial',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#FFD93D'],
                    ['role' => 'secondary', 'hex' => '#FF6B6B'],
                    ['role' => 'accent',    'hex' => '#4ECDC4'],
                ],
            ],
            [
                'id'                  => 'variety',
                'name'                => __('花色专栏', 'linked3'),
                'meta_signature'      => 'eclectic style mix, bold color blocks, trend-driven composition',
                'prompt_skeleton_id'  => 'fashion_editorial',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#FF006E'],
                    ['role' => 'secondary', 'hex' => '#8338EC'],
                    ['role' => 'accent',    'hex' => '#FFBE0B'],
                ],
            ],
            [
                'id'                  => 'suspense',
                'name'                => __('悬疑暗黑', 'linked3'),
                'meta_signature'      => 'high-contrast chiaroscuro, ambiguous shadow, ominous negative space',
                'prompt_skeleton_id'  => 'dark_portrait',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#1A1A1A'],
                    ['role' => 'secondary', 'hex' => '#2F4F4F'],
                    ['role' => 'accent',    'hex' => '#8B0000'],
                ],
            ],
            [
                'id'                  => 'pet_heal',
                'name'                => __('萌宠治愈', 'linked3'),
                'meta_signature'      => 'anthropomorphic pet subject, soft fur detail, cozy domestic light',
                'prompt_skeleton_id'  => 'watercolor_soft',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#FFF4E6'],
                    ['role' => 'secondary', 'hex' => '#FFB4A2'],
                    ['role' => 'accent',    'hex' => '#B5838D'],
                ],
            ],
            [
                'id'                  => 'guochao',
                'name'                => __('国潮东方', 'linked3'),
                'meta_signature'      => 'modern oriental motif, vermilion + ink black, contemporary Chinese pattern',
                'prompt_skeleton_id'  => 'hanfu_photography',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#C8102E'],
                    ['role' => 'secondary', 'hex' => '#1A1A1A'],
                    ['role' => 'accent',    'hex' => '#D4AF37'],
                ],
            ],
            [
                'id'                  => 'cyber_future',
                'name'                => __('赛博未来', 'linked3'),
                'meta_signature'      => 'neon-lit cyberpunk, holographic interface, dystopian metropolis night',
                'prompt_skeleton_id'  => 'cyberpunk_neon',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#0A0E27'],
                    ['role' => 'secondary', 'hex' => '#FF00FF'],
                    ['role' => 'accent',    'hex' => '#00FFFF'],
                ],
            ],
            [
                'id'                  => 'documentary',
                'name'                => __('纪实人文', 'linked3'),
                'meta_signature'      => 'candid documentary realism, high ISO grain, natural on-site light',
                'prompt_skeleton_id'  => 'documentary_photo',
                'color_system'        => [
                    ['role' => 'primary',   'hex' => '#808080'],
                    ['role' => 'secondary', 'hex' => '#2C2C2C'],
                    ['role' => 'accent',    'hex' => '#D4A574'],
                ],
            ],
        ];
    }

    public static function get_l3_souls(): array
    {
        return [
            [
                'id'               => 'miyazaki_watercolor',
                'name'             => __('宫崎骏水彩', 'linked3'),
                'description'      => __('温暖手绘水彩, 蓝天绿野, 治愈叙事。', 'linked3'),
                'preview_image'    => 'preview/miyazaki_watercolor.jpg',
                'applicable_scenes'=> ['nature', 'outdoor', 'interior'],
                'disclaimer'       => __('致敬风格, 非作者授权, 商用须取得著作权许可。', 'linked3'),
            ],
            [
                'id'               => 'otomo_linework',
                'name'             => __('大友克洋线稿', 'linked3'),
                'description'      => __('精细硬朗线稿, 机械结构感, 反乌托邦氛围。', 'linked3'),
                'preview_image'    => 'preview/otomo_linework.jpg',
                'applicable_scenes'=> ['industrial', 'city_life', 'cultural'],
                'disclaimer'       => __('致敬风格, 非作者授权, 商用须取得著作权许可。', 'linked3'),
            ],
            [
                'id'               => 'monet_lightspot',
                'name'             => __('莫奈光斑', 'linked3'),
                'description'      => __('印象派光斑色彩, 模糊笔触, 户外光影流转。', 'linked3'),
                'preview_image'    => 'preview/monet_lightspot.jpg',
                'applicable_scenes'=> ['nature', 'outdoor', 'cultural'],
                'disclaimer'       => __('致敬风格, 公共领域参考, 商用建议衍生创作。', 'linked3'),
            ],
            [
                'id'               => 'zhang_zeduan_gongbi',
                'name'             => __('张择端工笔', 'linked3'),
                'description'      => __('宋人工笔白描, 长卷叙事, 界画线条精度。', 'linked3'),
                'preview_image'    => 'preview/zhang_zeduan_gongbi.jpg',
                'applicable_scenes'=> ['cultural', 'city_life', 'commercial'],
                'disclaimer'       => __('公共领域古画风格, 衍生创作可商用, 但需注明原作出处。', 'linked3'),
            ],
            [
                'id'               => 'van_gogh_brushwork',
                'name'             => __('梵高笔触', 'linked3'),
                'description'      => __('旋涡厚涂笔触, 强烈色彩对比, 情绪化夜空。', 'linked3'),
                'preview_image'    => 'preview/van_gogh_brushwork.jpg',
                'applicable_scenes'=> ['nature', 'outdoor'],
                'disclaimer'       => __('致敬风格, 公共领域参考, 商用建议衍生创作。', 'linked3'),
            ],
            [
                'id'               => 'hokusai_floating',
                'name'             => __('葛饰北斋浮世', 'linked3'),
                'description'      => __('浮世绘木版风格, 平面色块, 强烈轮廓线。', 'linked3'),
                'preview_image'    => 'preview/hokusai_floating.jpg',
                'applicable_scenes'=> ['nature', 'cultural', 'city_life'],
                'disclaimer'       => __('公共领域浮世绘参考, 衍生创作可商用。', 'linked3'),
            ],
            [
                'id'               => 'ando_minimal',
                'name'             => __('安藤忠雄极简', 'linked3'),
                'description'      => __('清水混凝土 + 几何光影, 极简空间叙事。', 'linked3'),
                'preview_image'    => 'preview/ando_minimal.jpg',
                'applicable_scenes'=> ['commercial', 'cultural', 'interior'],
                'disclaimer'       => __('致敬建筑美学, 非建筑师授权, 商用须取得相关许可。', 'linked3'),
            ],
            [
                'id'               => 'kusama_dots',
                'name'             => __('草间弥生波点', 'linked3'),
                'description'      => __('无限波点镜屋, 高饱和色块, 心理沉浸感。', 'linked3'),
                'preview_image'    => 'preview/kusama_dots.jpg',
                'applicable_scenes'=> ['commercial', 'cultural', 'interior'],
                'disclaimer'       => __('致敬风格, 艺术家在世, 商用须取得代理画廊授权。', 'linked3'),
            ],
            [
                'id'               => 'mucha_floral',
                'name'             => __('Mucha 繁花', 'linked3'),
                'description'      => __('新艺术运动装饰边框, 长卷发女性, 拜占庭色彩。', 'linked3'),
                'preview_image'    => 'preview/mucha_floral.jpg',
                'applicable_scenes'=> ['cultural', 'commercial'],
                'disclaimer'       => __('致敬风格, 部分作品公共领域, 商用建议衍生创作。', 'linked3'),
            ],
            [
                'id'               => 'hokusai_wave',
                'name'             => __('Hokusai 冲浪', 'linked3'),
                'description'      => __('神奈川冲浪里的巨浪图式, 靛蓝主调, 浮世海景。', 'linked3'),
                'preview_image'    => 'preview/hokusai_wave.jpg',
                'applicable_scenes'=> ['nature', 'outdoor'],
                'disclaimer'       => __('公共领域浮世绘参考, 衍生创作可商用。', 'linked3'),
            ],
            [
                'id'               => 'klimt_gold',
                'name'             => __('Klimt 金色', 'linked3'),
                'description'      => __('金箔装饰绘画, 拜占庭马赛克感, 情侣拥抱主题。', 'linked3'),
                'preview_image'    => 'preview/klimt_gold.jpg',
                'applicable_scenes'=> ['cultural', 'interior'],
                'disclaimer'       => __('致敬风格, 部分作品公共领域, 商用建议衍生创作。', 'linked3'),
            ],
            [
                'id'               => 'banksy_irony',
                'name'             => __('Banksy 反讽', 'linked3'),
                'description'      => __('街头模板喷漆, 黑白红主调, 政治反讽叙事。', 'linked3'),
                'preview_image'    => 'preview/banksy_irony.jpg',
                'applicable_scenes'=> ['city_life', 'outdoor'],
                'disclaimer'       => __('致敬风格, 匿名艺术家, 商用建议衍生创作避免直接复制。', 'linked3'),
            ],
        ];
    }

    public static function route_skeleton(string $l1, string $l2, string $l3): string
    {
        // 1) 委托 SkeletonLibrary (v8.1.0 引用, 防御性 class_exists)
        if (class_exists('\Linked3\Classes\Genesis\SkeletonLibrary') && method_exists('\Linked3\Classes\Genesis\SkeletonLibrary', 'route')) {
            try {
                $sk = \SkeletonLibrary::route($l1, $l2, $l3);
                if (is_string($sk) && $sk !== '') return $sk;
            } catch (\Throwable $e) {
                if (function_exists("linked3_log")) linked3_log("genesis", "warning", "Scene axis AI failed: " . $e->getMessage());
            }
        }

        // 2) L3 → 推荐骨架 (12 灵魂风格各自的强关联骨架)
        $l3_to_skeleton = [
            'miyazaki_watercolor'   => 'watercolor_soft',
            'otomo_linework'        => 'cyberpunk_neon',
            'monet_lightspot'       => 'watercolor_soft',
            'zhang_zeduan_gongbi'   => 'ukiyoe_washoku',
            'van_gogh_brushwork'    => 'watercolor_soft',
            'hokusai_floating'      => 'ukiyoe_washoku',
            'ando_minimal'          => 'zen_minimal_ink',
            'kusama_dots'           => 'fashion_editorial',
            'mucha_floral'          => 'ukiyoe_washoku',
            'hokusai_wave'          => 'ukiyoe_washoku',
            'klimt_gold'            => 'gothic_dark_stained',
            'banksy_irony'          => 'documentary_photo',
        ];
        if (isset($l3_to_skeleton[$l3])) {
            return $l3_to_skeleton[$l3];
        }

        // 3) L2 → 推荐骨架 (专栏自带 prompt_skeleton_id)
        foreach (self::get_l2_columns() as $col) {
            if ($col['id'] === $l2) {
                return $col['prompt_skeleton_id'] ?? 'documentary_photo';
            }
        }

        // 4) L1 → 兜底 (按 category_id 推荐骨架)
        $l1_category_to_skeleton = [
            'city_life'  => 'documentary_photo',
            'nature'     => 'watercolor_soft',
            'interior'   => 'portrait_photography',
            'commercial' => 'fashion_editorial',
            'cultural'   => 'ukiyoe_washoku',
            'outdoor'    => 'documentary_photo',
            'industrial' => 'cyberpunk_neon',
        ];
        foreach (self::get_l1_types() as $cat) {
            foreach ($cat['scenes'] as $s) {
                if ($s['id'] === $l1) {
                    return $l1_category_to_skeleton[$cat['category_id']] ?? 'documentary_photo';
                }
            }
        }

        // 5) 全部失败 → 纪实摄影兜底
        return 'documentary_photo';
    }

}
