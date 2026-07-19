<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
/**
 * Linked3_Genesis_V7_Loader — G8 extraction.
 * @since 27.13.0
 */
class Linked3_Genesis_V7_Loader
{
    public function loadAll(): void {
        $charDir = $this->libDir . '/seeds/characters';
        if (is_dir($charDir)) {
            foreach (glob($charDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['id'])) {
                    $this->characters[$data['id']] = $data;
                }
            }
        }
        $sceneDir = $this->libDir . '/seeds/scenes';
        if (is_dir($sceneDir)) {
            foreach (glob($sceneDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['id'])) {
                    $this->scenes[$data['id']] = $data;
                }
            }
        }
        $styleDir = $this->libDir . '/seeds/styles';
        if (is_dir($styleDir)) {
            foreach (glob($styleDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['id'])) {
                    $this->styles[$data['id']] = $data;
                }
            }
        }
        $opDir = $this->libDir . '/operators';
        if (is_dir($opDir)) {
            foreach (glob($opDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['id'])) {
                    $this->operators[$data['id']] = $data;
                }
            }
        }
    }

}
