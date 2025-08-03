<?php

/**
 * @file
 * Fonts settings css styles.
 */

/**
 * Generate fonts theme css styles.
 *
 * @param string $theme
 *   Theme machine name.
 * @param string $css
 *   Generated css styles string.
 * @param array $palette
 *   Theme palette array.
 */
function fonts_theme_settings_css(string $theme, &$css, array $palette) {

  // Font theme settings keys.
  $fonts = [
    'body_font_face',
    'nav_font_face',
    'headings_font_face',
    'sitename_font_face',
    'blockquote_font_face',
  ];

  foreach ($fonts as $font_key) {
    if ($font = theme_get_setting($font_key, $theme)) {
      $style = _dxpr_theme_font_get_style($font);
      $weight = _dxpr_theme_font_get_weight($font);
      $selector = theme_get_setting($font_key . '_selector', $theme);

      // Add additional selector for the body font.
      if ($font_key === 'body_font_face') {
        $selector .= ', .tooltip';
      }

      $css .= "/* " . str_replace('_', ' ', $font_key) . " */\n";
      $css .= $selector . " {\n";
      $css .= _dxpr_theme_settings_add_css($font_key, 'font-family');
      $css .= "  font-style: $style;\n";
      $css .= "  font-weight: $weight;\n";
      $css .= "}\n\n";
    }
  }
}
