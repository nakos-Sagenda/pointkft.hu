<?php

/**
 * @file
 * Typography settings css styles.
 */

/**
 * Generate typography theme css styles.
 *
 * @param string $theme
 *   Theme machine name.
 * @param string $css
 *   Generated css styles string.
 * @param array $palette
 *   Theme palette array.
 */
function typography_theme_settings_css($theme, &$css, array $palette) {
  $css .= "/* Typography Styles */\n";

  if (theme_get_setting('menu_type', $theme) != 'lead') {
    $css .=
      ".dxpr-theme-header--top #dxpr-theme-main-menu .nav > li > a,\n" .
      ".dxpr-theme-header--top #dxpr-theme-main-menu .nav > li > span,\n" .
      ".dxpr-theme-header--side #dxpr-theme-main-menu .nav a,\n" .
      ".dxpr-theme-header--side #dxpr-theme-main-menu .nav span,\n" .
      ".body--dxpr-theme-nav-desktop .dxpr-theme-header--top .nav .dropdown-menu li > a,\n" .
      ".body--dxpr-theme-nav-desktop .dxpr-theme-header--top .nav .dropdown-menu li > span {\n" .
      _dxpr_theme_settings_add_css('nav_font_size', 'font-size') .
      "}\n\n";

    // Media query.
    $css .=
      "@media screen and (max-width: 768px) {\n" .
      "  .dxpr-theme-header--top #dxpr-theme-main-menu .nav > li > a,\n" .
      "  .dxpr-theme-header--top #dxpr-theme-main-menu .nav > li > span,\n" .
      "  .dxpr-theme-header--side #dxpr-theme-main-menu .nav a,\n" .
      "  .dxpr-theme-header--side #dxpr-theme-main-menu .nav span,\n" .
      "  .body--dxpr-theme-nav-desktop .dxpr-theme-header--top .nav .dropdown-menu li > a,\n" .
      "  .body--dxpr-theme-nav-desktop .dxpr-theme-header--top .nav .dropdown-menu li > span {\n  " .
      _dxpr_theme_settings_add_css('nav_mobile_font_size', 'font-size') .
      "  }\n";
    $css .= "}\n\n";
  }
}
