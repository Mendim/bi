<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WP_File_Download\Admin\Fields;

use Joomunited\WPFramework\v1_0_5\Field;
use Joomunited\WPFramework\v1_0_5\Factory;
use Joomunited\WPFramework\v1_0_5\Application;

defined('ABSPATH') || die();

/**
 * Class Select2full
 */
class Select2full extends Field
{
    /**
     * Display select
     *
     * @param array $field Fields
     * @param array $data  Data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        $attributes = $field['@attributes'];
        $html       = '<div class="ju-settings-option full-width">';
        $tooltip    = isset($attributes['tooltip']) ? $attributes['tooltip'] : '';
        if (empty($attributes['hidden']) || (!empty($attributes['hidden']) && $attributes['hidden'] !== 'true')) {
            if (!empty($attributes['label']) && $attributes['label'] !== '' &&
                !empty($attributes['name']) && $attributes['name'] !== '') {
                $html .= '<label title="' . $tooltip . '" class="ju-setting-label" for="' . $attributes['name'] . '">';
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic translate
                $html .= esc_html__($attributes['label'], 'wpfd') . '</label>';
            }
        }

        $html .= '<select';
        if (!empty($attributes)) {
            foreach ($attributes as $attribute => $value) {
                if (in_array($attribute, array('id', 'class', 'onchange', 'name')) && isset($value)) {
                    $html .= ' ' . $attribute . '="' . $value . '"';
                }
            }
        }
        $html       .= ' >';
        $cleanfield = $field;
        unset($cleanfield['@attributes']);
        if (!empty($cleanfield[0])) {
            $attributearray = array('type', 'id', 'class', 'name', 'onchange', 'value');
            foreach ($cleanfield[0] as $child) {
                if (!empty($child['option']['@attributes'])) {
                    $html .= '<option ';
                    foreach ($child['option']['@attributes'] as $childAttribute => $childValue) {
                        if (in_array($childAttribute, $attributearray) && isset($childValue)) {
                            $html .= ' ' . $childAttribute . '="' . $childValue . '"';
                            if ($childAttribute === 'value' && isset($attributes['value']) &&
                                $attributes['value'] === $childValue) {
                                $html .= ' selected="selected"';
                            }
                        }
                    }
                    $html .= '>';
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic translate
                    $html .= esc_html__($child['option'][0], 'wpfd');
                    $html .= '</option>';
                }
            }
        }
        $html .= '</select>';
        if (!empty($attributes['help']) && $attributes['help'] !== '') {
            $html .= '<div class="ju-settings-help">' . $attributes['help'] . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
