<?php

/**
 * @file
 * Page title settings css styles.
 */

/**
 * Generate page title theme css styles.
 *
 * @param string $theme
 *   Theme machine name.
 * @param string $css
 *   Generated css styles string.
 * @param array $palette
 *   Theme palette array.
 */
function page_title_theme_settings_css($theme, &$css, array $palette) {
  $css .= "/* Page Title Styles */\n";

  // Page Title container.
  $css .= ".page-title-full-width-container:after {\n";
  $css .= _dxpr_theme_settings_add_css('page_title_image_opacity', 'opacity');

  if ($page_title_img_pos = _dxpr_theme_settings_add_css('page_title_image_position', 'background-position')) {
    $css .= $page_title_img_pos;
  }

  if (theme_get_setting('page_title_image_mode') == 'fixed') {
    $css .= "  background-attachment: fixed;\n";
  }

  if ($page_title_image_style = theme_get_setting('page_title_image_style')) {
    switch ($page_title_image_style) {
      case 'no_repeat':
        $css .= "  background-size: auto; background-repeat: no-repeat;\n";
        break;

      case 'repeat':
        $css .= "  background-size: auto;\n";
        break;

      case 'contain':
        $css .= "  background-size: contain;\n";
        break;
    }
  }

  if ($page_title_image_path = theme_get_setting('page_title_image_path', $theme)) {
    $css .= "  background-image: url('" . \Drupal::service('file_url_generator')->generateString($page_title_image_path) . "');\n";
  }

  $css .= "}\n\n";

}
