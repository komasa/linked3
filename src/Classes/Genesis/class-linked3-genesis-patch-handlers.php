<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class Linked3_Genesis_Patch_Handlers
{
        public static function ajax_seed_generate_full() : mixed { return Linked3_Genesis_Patch_Stage2::ajax_seed_generate_full(); }

        public static function ajax_v9_stage1_fixed() : mixed { return Linked3_Genesis_Patch_Stage2::ajax_v9_stage1_fixed(); }

        public static function ajax_v9_stage2_fixed() : mixed { return Linked3_Genesis_Patch_Stage2::ajax_v9_stage2_fixed(); }

}
