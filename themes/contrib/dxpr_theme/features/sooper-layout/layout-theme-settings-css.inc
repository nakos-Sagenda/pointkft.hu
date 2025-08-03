<?php

/**
 * @file
 * Layout settings css styles.
 */

/**
 * Generate layouts theme css styles.
 *
 * @param string $theme
 *   Theme machine name.
 * @param string $css
 *   Generated css styles string.
 * @param array $palette
 *   Theme palette array.
 */
function layout_theme_settings_css($theme, &$css, array $palette) {
  $css .= "/* Layout Styles */\n";

  if (theme_get_setting('boxed_layout', $theme) && theme_get_setting('boxed_layout_boxbg', $theme)) {
    $cnt_bg = _dxpr_theme_setting_get_css_var('boxed_layout_boxbg');
    $css .= ".dxpr-theme-boxed-container { background: $cnt_bg; }\n";
    $css .= ".dxpr-theme-boxed-container .nav-tabs >li.active a { border-bottom-color: $cnt_bg; }\n";
    $css .= ".dxpr-theme-boxed-container .tabs-left > .nav-tabs .active > a { border-right-color: $cnt_bg; }\n";
    $css .= ".dxpr-theme-boxed-container .tabs-right > .nav-tabs .active > a { border-left-color: $cnt_bg; }\n\n";
  }

  $css_body = '';

  if ($background_image_position = _dxpr_theme_settings_add_css('background_image_position', 'background-position')) {
    $css_body .= $background_image_position;
  }

  if ($background_image_attachment = _dxpr_theme_settings_add_css('background_image_attachment', 'background-attachment')) {
    $css_body .= $background_image_attachment;
  }

  if ($background_image_style = theme_get_setting('background_image_style')) {
    switch ($background_image_style) {
      case 'no_repeat':
        $css_body .= "  background-size: auto;\nbackground-repeat: no-repeat;\n";
        break;

      case 'repeat':
        $css_body .= "  background-size: auto;\n";
        break;

      case 'contain':
        $css_body .= "  background-size: contain;\n";
        break;
    }
  }

  if ($background_image_path = theme_get_setting('background_image_path', $theme)) {
    $css_body .= "  background-image: url('" . \Drupal::service('file_url_generator')->generateString($background_image_path) . "'); }\n";
  }

  if ($css_body) {
    $css .= "body {\n$css_body}\n\n";
  }
}
