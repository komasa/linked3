<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisPatchHandlers
{
        public static function ajax_seed_generate_full() : mixed { return GenesisPatchStage2::ajax_seed_generate_full(); }

        public static function ajax_v9_stage1_fixed() : mixed { return GenesisPatchStage2::ajax_v9_stage1_fixed(); }

        public static function ajax_v9_stage2_fixed() : mixed { return GenesisPatchStage2::ajax_v9_stage2_fixed(); }

}
