<?php

/**
 * @file
 * Header settings css styles.
 */

/**
 * Generate header theme css styles.
 *
 * @param string $theme
 *   Theme machine name.
 * @param string $css
 *   Generated css styles string.
 * @param array $palette
 *   Theme palette array.
 */
function header_theme_settings_css(string $theme, string &$css, array $palette) {
  $css .= "/* Header Styling */\n";

  // Header Vars.
  $header_position = theme_get_setting('header_position', $theme);
  $header_top_height_var = _dxpr_theme_setting_get_css_var('header_top_height');

  if (theme_get_setting('header_side_align', $theme) === 'right') {
    $css .= ".dxpr-theme-header--side #dxpr-theme-main-menu .menu__link::after {\n";
    $css .= "  right: auto;\n";
    $css .= "  left: 1.25rem;\n";
    $css .= "}\n\n";
  }

  if (!$header_position) {
    $header_var = $header_scroll_var = 'var(' . _dxpr_theme_get_const('cssColorPrefix') . 'header)';

    // Check if menu should be initially hidden and shown after scroll.
    $show_after_scroll =
      theme_get_setting('header_top_sticky', $theme) &&
      theme_get_setting('header_top_sticky_show', $theme) == 'after_scroll';

    if (in_array(theme_get_setting('header_style', $theme), [
      'normal',
      'overlay',
    ])) {
      $color_header_var = 'var(' . _dxpr_theme_get_const('cssColorPrefix') . 'header-rgb)';

      $header_var = sprintf("rgba(%s, %s)",
        $color_header_var,
        _dxpr_theme_setting_get_css_var('header_top_bg_opacity'),
      );
      $header_scroll_var = sprintf("rgba(%s, %s)",
        $color_header_var,
        _dxpr_theme_setting_get_css_var('header_top_bg_opacity_scroll'),
      );
    }

    $css .= ".body--dxpr-theme-nav-desktop #navbar.dxpr-theme-header--top {\n";
    $css .= "  background-color: $header_var;\n";
    if ($show_after_scroll) {
      $css .= "  opacity: 0;\n";
    }
    $css .= "}\n";

    $css .= ".body--dxpr-theme-nav-desktop #navbar.dxpr-theme-header--top.affix {\n";
    $css .= "  background-color: $header_scroll_var;\n";
    if ($show_after_scroll) {
      $css .= "  opacity: 1;\n";
    }
    $css .= "}\n\n";

    if (theme_get_setting('header_style', $theme) == 'overlay') {
      // For overlay header add a big gap in the top of the page title
      // to make room for the header.
      $css .= ".body--dxpr-theme-nav-desktop.body--dxpr-theme-header-overlay .page-title-full-width-container {\n";
      $css .= "  padding-top: calc($header_top_height_var + 30px);\n";
      $css .= "}\n\n";
    }
    elseif (theme_get_setting('header_top_fixed', $theme)) {
      // For fixed headers create small padding to prevent fixed
      // header from hiding toolbars.
      $css .= ".body--dxpr-theme-nav-desktop.body--dxpr-theme-header-fixed.body--dxpr-theme-header-not-overlay {\n";
      $css .= "  padding-top: $header_top_height_var !important;\n";
      $css .= "}\n\n";

      $css .= "html.js .body--dxpr-theme-nav-desktop.toolbar-fixed.toolbar-horizontal.body--dxpr-theme-header-fixed.body--dxpr-theme-header-not-overlay {\n";
      $css .= "  padding-top: calc($header_top_height_var + 39px) !important;\n";
      $css .= "}\n\n";

      $css .= "html.js .body--dxpr-theme-nav-desktop.toolbar-fixed.toolbar-horizontal.toolbar-tray-open.toolbar-horizontal.body--dxpr-theme-header-fixed.body--dxpr-theme-header-not-overlay {\n";
      $css .= "  padding-top: calc($header_top_height_var + 80px) !important;\n";
      $css .= "}\n\n";
    }

    if (theme_get_setting('header_style', $theme) == 'navbar_pull_down') {
      $css .= ".body--dxpr-theme-nav-desktop.body--dxpr-theme-header-not-overlay .dxpr-theme-header--top .col-sm-12 {\n";
      $css .= "  margin-bottom: calc(-1 * $header_top_height_var / 2);\n";
      $css .= "}\n\n";
    }
  }

  // Sticky footer settings.
  if (theme_get_setting('sticky_footer', $theme)) {
    $header_top_height = $header_top_height_var;

    if (theme_get_setting('header_top_layout', $theme) == 'centered') {
      $header_top_height = "calc($header_top_height_var * 2)";
    }
    if (theme_get_setting('header_style', $theme) == 'navbar_pull_down') {
      $header_top_height = "calc($header_top_height_var / 2)";
    }

    $css .= ".html--dxpr-theme-sticky-footer .body--dxpr-theme-nav-desktop .dxpr-theme-header--top {\n";
    $css .= "  height: $header_top_height;\n";
    $css .= "}\n\n";

    $header_mobile_height_var = _dxpr_theme_setting_get_css_var('header_mobile_height');
    $header_top_height_scroll_var = _dxpr_theme_setting_get_css_var('header_top_height_scroll');

    $css .= ".html--dxpr-theme-sticky-footer .body--dxpr-theme-nav-desktop .dxpr-theme-header--top.affix {\n";
    $css .= "  min-height: $header_top_height_scroll_var;\n";
    $css .= "}\n\n";

    $css .= ".html--dxpr-theme-sticky-footer .body--dxpr-theme-nav-mobile .dxpr-theme-header {\n";
    $css .= "  min-height: $header_mobile_height_var;\n";
    $css .= "}\n\n";

    $css .= ".html--dxpr-theme-sticky-footer .body--dxpr-theme-nav-mobile .dxpr-theme-header.affix {\n";
    $css .= "  min-height: $header_mobile_height_var;\n";
    $css .= "}\n\n";
  }

  /*
   * MENU OPTIONS
   */
  $css .= "/* Menu Options */\n";

  if ($dropdown_width = _dxpr_theme_settings_add_css('dropdown_width', 'min-width')) {
    $css .= ".dropdown-menu, .dropdown-menu li {\n";
    $css .= $dropdown_width;
    $css .= "}\n\n";
  }

  if ($menu_type = theme_get_setting('menu_type', $theme)) {
    $menu_type_selector = ".dxpr-theme-header .menu a";
    $menu_type_value = "";

    switch ($menu_type) {
      case 'uppercase':
        $menu_type_value = "text-transform: uppercase;";
        break;

      case 'bold':
        $menu_type_value = "font-weight: bold;";
        break;

      case 'lead':
        $menu_type_selector .= ",\n.dxpr-theme-header .menu span";
        $menu_type_value = "font-size: 1.3125rem;\n  font-weight: 300;";
        break;
    }

    $css .= "$menu_type_selector {\n  ";
    $css .= "$menu_type_value\n";
    $css .= "}\n\n";
  }

  // We only add border on header side if this is the desktop view too
  // To prevent header-top configuration and spacing
  // to look broken in mobile menus.
  $menu_border_position = theme_get_setting('menu_border_position', $theme);

  if (theme_get_setting('menu_hover', $theme) == 'border') {
    $css .= ".body--dxpr-theme-header-side .dxpr-theme-header--side.dxpr-theme-header--hover-border .menu a::before,\n";
    $css .= ".dxpr-theme-header--top.dxpr-theme-header--hover-border .nav > li > a::before {\n";

    if ($menu_border_size = _dxpr_theme_settings_add_css('menu_border_size', 'height')) {
      $css .= $menu_border_size;
    }

    if ($menu_border_position) {
      $css .= "  $menu_border_position: 0; \n";

      if (!$header_position &&
        $menu_margin = _dxpr_theme_settings_add_css('menu_border_position_offset', 'margin-' . $menu_border_position)
      ) {
        $css .= $menu_margin;
      }
    }

    if ($menu_border_color = _dxpr_theme_settings_add_css('menu_border_color', 'background-color')) {
      $css .= $menu_border_color;
    }

    if ($animation = theme_get_setting('menu_border_animate', $theme)) {
      $css .= "  transition: transform 300ms;\n  ";
      $transform_origin = '0';

      switch ($animation) {
        case 'ltr':
          $transform_origin = '0 50%';
          break;

        case 'rtl':
          $transform_origin = '100% 50%';
          break;

        case 'drop':
          $css .= "transform: scale(0, 0) translate(10px, -10px);\n  ";
          $transform_origin = '50% 0';
          break;
      }

      $css .= "transform-origin: $transform_origin;\n";
    }
    $css .= "}\n\n";
  }

  if (!$header_position && $menu_border_position &&
    $menu_border_pos_sticky = _dxpr_theme_settings_add_css('menu_border_position_offset_sticky', "margin-$menu_border_position")
  ) {
    $css .= ".dxpr-theme-header--top.dxpr-theme-header--hover-border.affix .nav > li > a::before {\n";
    $css .= $menu_border_pos_sticky;
    $css .= "}\n\n";
  }

}
