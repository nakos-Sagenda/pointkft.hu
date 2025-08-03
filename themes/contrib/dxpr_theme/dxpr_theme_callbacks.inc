<?php

/**
 * @file
 * DXPR Theme helper functions.
 */

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;

/**
 * Helper function returns path of css cache file.
 *
 * @param string $theme
 *   Theme machine name.
 *
 * @return string
 *   A valid path of css cache file.
 */
function _dxpr_theme_css_cache_file($theme) {
  return 'public://dxpr_theme/css/themesettings-' . $theme . '.css';
}

/**
 * Write css files for the color settings.
 *
 * @param string $theme
 *   Theme machine name.
 * phpcs:ignore
 * @deprecated This function is no longer needed. Kept for legacy reasons.
 */
function dxpr_theme_color_module_css_write($theme) {

}

/**
 * Theme CSS generator from DXPR Theme-settings.php.
 *
 * Custom theme settings might need a lot of CSS.
 * So we put it in a file for optimal performance.
 *
 * @param string $theme
 *   Theme name.
 */
function dxpr_theme_css_cache_build(string $theme) {
  $dxpr_theme_css_file = _dxpr_theme_css_cache_file($theme);
  $dir = 'public://dxpr_theme/css/';
  $dir_writable = \Drupal::service('file_system')
    ->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);

  $palette = unserialize((theme_get_setting('color_palette', $theme) ?? ''), [
    'allowed_classes' => FALSE,
  ]) ?: NULL;

  // Fetch default palette.
  if (empty($palette)) {
    $path = \Drupal::service('extension.list.theme')->getPath('dxpr_theme');
    $filepath = sprintf('%s/%s/features/sooper-colors/color-settings.json', DRUPAL_ROOT, $path);

    if ($path && file_exists($filepath)) {
      $json = file_get_contents($filepath);
      $settings = Json::decode($json);
      $palette = $settings['schemes']['default']['colors'] ?? [];
    }

    if (empty($palette)) {
      \Drupal::messenger()->addWarning(t('Could not create theme styles; please resave theme settings.'));
    }
  }

  // Construct CSS file:
  $css = '';
  // Load Sooper Features CSS.
  foreach (\Drupal::service('file_system')->scanDirectory(\Drupal::service('extension.list.theme')->getPath('dxpr_theme') . '/features', '/css.inc/i') as $file) {
    require_once $file->uri;
    $function_name = basename($file->filename, '.inc');
    $function_name = str_replace('-', '_', $function_name);
    if (function_exists($function_name)) {
      $function_name($theme, $css, $palette);
    }
  }
  $file_object = \Drupal::service('file.repository')->writeData($css, $dxpr_theme_css_file, FileSystemInterface::EXISTS_REPLACE);
  if ($dir_writable && $file_object) {
    $message = t('DXPR Theme CSS file cache built for %theme', ['%theme' => $theme]);
    \Drupal::messenger()->addMessage($message);
    \Drupal::logger('dxpr_theme')->notice($message);
  }
  else {
    $message = t('Cannot write theme-settings file, please check your file system. (See status report page)');
    \Drupal::messenger()->addMessage($message, 'error');
    \Drupal::logger('dxpr_theme')->error($message);
  }

  // If the CSS & JS aggregation are enabled we need to clear the caches.
  drupal_flush_all_caches();
  \Drupal::service('asset.css.collection_optimizer')->deleteAll();
  \Drupal::service('asset.js.collection_optimizer')->deleteAll();
}

/**
 * Returns a settings value.
 *
 * @param string $key
 *   Key index in color.inc $info array.
 *
 * @return mixed
 *   Value for the given setting.
 */
function _dxpr_theme_get_const(string $key) {
  $settings = &drupal_static(__FUNCTION__, []);

  if (empty($settings)) {
    $path = \Drupal::service('extension.list.theme')->getPath('dxpr_theme');
    $filepath = sprintf('%s/%s/features/sooper-settings/theme-settings.json', DRUPAL_ROOT, $path);

    if ($path && file_exists($filepath)) {
      $json = file_get_contents($filepath);
      $settings = Json::decode($json);
    }
  }

  return $settings[$key] ?? NULL;
}

/**
 * Adds the specified theme setting as a css variable for given property.
 *
 * @param string $setting
 *   Theme setting machine name.
 * @param string $property
 *   CSS property to add.
 *
 * @return string
 *   CSS formatted property and value for given setting, or FALSE.
 */
function _dxpr_theme_settings_add_css(string $setting, string $property): string {
  if ($css_var = _dxpr_theme_setting_get_css_var($setting)) {
    return sprintf("  %s: %s;\n", $property, $css_var);
  }
  return '';
}

/**
 * Returns the CSS variable corresponding to given theme setting.
 *
 * Note that this only works for theme settings and not colors.
 *
 * @param string $setting
 *   Theme setting machine name.
 *
 * @return string
 *   CSS variable, e.g. "var(--prefix-name)".
 */
function _dxpr_theme_setting_get_css_var(string $setting): string {
  if (theme_get_setting($setting)) {
    $css_var = str_replace('_', '-', $setting);
    $prefix = _dxpr_theme_get_const('cssSettingPrefix');
    return sprintf("var(%s%s)", $prefix, $css_var);
  }
  return '';
}

/**
 * Helper determine font type.
 *
 * @param string $font
 *   The font name.
 * @param string $theme
 *   The theme machine name.
 *
 * @return string
 *   The font stack string.
 */
function _dxpr_theme_get_font($font, $theme) {
  if (substr($font, 0, 1) === '0') {
    $font = _dxpr_theme_format_google_font_name($font);
  }
  elseif (substr($font, 0, 1) === '1') {
    $font = _dxpr_theme_format_local_font_name($font);
  }
  else {
    $font = _dxpr_theme_font_stack($font);
  }
  return $font;
}

/**
 * Helper function to format the font name.
 *
 * Following a pattern that seems to hold for all google web fonts.
 *
 * @param string $key
 *   The google font name.
 *
 * @return string
 *   Formatted font string.
 */
function _dxpr_theme_format_google_font_name($key) {
  $font = explode(':', substr($key, 1));
  $font_basename = str_replace('+', ' ', $font[0]);
  $variant = $font[1];
  $goog_weight = '';
  switch ($variant) {
    case '100':
    case '100italic':
    case 'hairline':
      $goog_weight = ' Hairline';
      break;

    case '200':
    case '200italic':
    case 'extralight':
      $goog_weight = ' Extralight';
      break;

    case '300':
    case '300italic':
    case 'light':
      $goog_weight = ' Light';
      break;

    case '400':
    case '400italic':
    case 'regular':
      $goog_weight = ' Regular';
      break;

    case '500':
    case '500italic':
    case 'medium':
      $goog_weight = ' Medium';
      break;

    case '600':
    case '600italic':
    case 'semibold':
      $goog_weight = ' SemiBold';
      break;

    case '700':
    case '700italic':
    case 'bold':
    case 'bolditalic':
      $goog_weight = ' Bold';
      break;

    case '800':
    case '800italic':
    case 'extrabold':
      $goog_weight = ' Extrabold';
      break;

    case '900':
    case '900italic':
    case 'Black':
      $goog_weight = ' Black';
      break;
  }
  if (stristr($variant, 'italic') !== FALSE) {
    $goog_style = ' Italic';
  }
  else {
    $goog_style = '';
  }

  $goog_name = '"' . $font_basename . $goog_weight . $goog_style . '", "' . $font_basename . '"';
  return $goog_name;
}

/**
 * Helper function to read the font name from the key.
 *
 * The hard part was already done in theme-settings.php.
 *
 * @param string $key
 *   The local font key.
 *
 * @return string
 *   The local font name..
 */
function _dxpr_theme_format_local_font_name($key) {
  $font = explode('|', $key);
  return $font[2];
}

/**
 * Helper function for getting font style from font theme setting.
 *
 * @param string $font
 *   Theme setting font value.
 *
 * @return string
 *   Font style value.
 */
function _dxpr_theme_font_get_style($font): string {
  return (strpos($font, 'italic')) ? 'italic' : 'normal';
}

/**
 * Helper function for getting font weight form font theme setting.
 *
 * @param string $font
 *   Theme setting font value.
 *
 * @return string
 *   Font weight value.
 */
function _dxpr_theme_font_get_weight($font): string {
  return (preg_match('/[0-9]{3}/', $font, $matches)) ? $matches[0] : '400';
}

/**
 * Provides Font Stack values for theme settings.
 *
 * @param string $font
 *   The font name.
 *
 * @return string
 *   The font stack string.
 *
 * @see font_list()
 */
function _dxpr_theme_font_stack($font = 'helvetica') {
  $fonts = [
    'SF_Segoe_Roboto' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"',
    'helvetica' => 'Arial, Helvetica, "Nimbus Sans L", "Liberation Sans", "FreeSans", sans-serif',
    'myriad' => '"myriad pro",myriad,calibri,arial,tahoma,verdana,sans-serif',
    'verdana' => 'Verdana, "Bitstream Vera Sans", Arial, sans-serif',
    'lucida' => '"Lucida Sans Unicode", "Lucida Sans", "Lucida Grande", "DejaVu Sans", Arial, sans-serif',
    'geneva' => '"Geneva", "Bitstream Vera Serif", "Tahoma", sans-serif',
    'tahoma' => 'Tahoma, Geneva, "DejaVu Sans Condensed", sans-serif',
    'trebuchet' => '"Trebuchet MS",Trebuchet,Tahoma,arial,verdana,sans',
    'century' => '"Century Gothic", "URW Gothic L", Helvetica, Arial, sans-serif',
    'garamond' => 'Garamond,"Times New Roman",Times,Century,Cambria,Georgia,serif',
    'georgia' => 'Georgia, "Bitstream Vera Serif", serif',
    'palatino' => '"Palatino Linotype", "URW Palladio L", "Book Antiqua", "Palatino", serif',
    'times' => '"Free Serif", "Times New Roman", Times, serif',
  ];
  if (!isset($fonts[$font])) {
    $font = 'helvetica';
  }
  return $fonts[$font];
}

/**
 * Returns data from the color-settings.json file.
 *
 * @param string|null $key
 *   Key index in color.inc $info array.
 *
 * @return array
 *   Array containing requested data.
 */
function _dxpr_theme_get_color_inc(?string $key = NULL): array {
  $path = \Drupal::service('extension.list.theme')->getPath('dxpr_theme');
  $filepath = sprintf('%s/%s/features/sooper-colors/color-settings.json', DRUPAL_ROOT, $path);

  if ($path && file_exists($filepath)) {
    $json = file_get_contents($filepath);
    $settings = Json::decode($json);

    if ($settings) {
      $data = $key ? ($settings[$key] ?? []) : $settings;
    }
  }

  return $data ?? [];
}

/**
 * Returns the color field keys.
 *
 * @return array
 *   The 'fields' sub-array.
 */
function _dxpr_theme_get_color_names(): array {
  return _dxpr_theme_get_color_inc('fields');
}

/**
 * Returns the color schemes.
 *
 * @return array
 *   The 'schemes' sub-array.
 */
function _dxpr_theme_get_color_schemes(): array {
  return _dxpr_theme_get_color_inc('schemes');
}

/**
 * Returns the specified color scheme, defaults to 'default'.
 *
 * @param string $scheme
 *   The color scheme machine name.
 *
 * @return array
 *   The specified color scheme indexed by color machine names.
 */
function _dxpr_theme_get_color_scheme(string $scheme = 'default'): array {
  $schemes = _dxpr_theme_get_color_schemes();
  return $schemes['default'] ?? [];
}

/**
 * Color options for theme settings.
 *
 * @param string $theme
 *   Theme machine name.
 *
 * @return array
 *   Color options.
 */
function _dxpr_theme_color_options($theme) {
  $colors = [
    '' => t('None (Theme Default)'),
    'white' => t('White'),
    'custom' => t('Custom Color'),
  ];
  $theme_colors = _dxpr_theme_get_color_names($theme);
  $colors = array_merge($colors, $theme_colors);
  return $colors;
}
