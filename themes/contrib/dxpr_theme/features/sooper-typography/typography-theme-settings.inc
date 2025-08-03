<?php

/**
 * @file
 * Theme typography settings.
 */

/**
 * Add typography options to the theme settings form.
 *
 * @param array $form
 *   Form array.
 * @param string $theme
 *   Theme machine name.
 */
function typography_theme_settings(array &$form, $theme) {
  $form['#attributes']['novalidate'] = 'novalidate';
  $form['dxpr_theme_settings']['typography'] = [
    '#title' => t('Typography'),
    '#type' => 'details',
    '#group' => 'dxpr_theme_settings',
    '#weight' => 15,
  ];

  $form['dxpr_theme_settings']['typography']['body_line_height'] = [
    '#type' => 'range',
    '#title' => t('Body Line Height'),
    '#default_value' => ((theme_get_setting('body_line_height') !== NULL)) ? theme_get_setting('body_line_height') : 1.5,
    '#min' => 0,
    '#max' => 3,
    '#step' => 0.01,
    '#attributes' => [
      'class' => ['dxb-slider', 'body-line-height-range', 'line-height-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['body_font_size'] = [
    '#type' => 'range',
    '#title' => t('Body Font Size'),
    '#default_value' => ((theme_get_setting('body_font_size') !== NULL)) ? theme_get_setting('body_font_size') : 14,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'body-font-size-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['nav_font_size'] = [
    '#type' => 'range',
    '#title' => t('Main menu Font Size'),
    '#default_value' => ((theme_get_setting('nav_font_size') !== NULL)) ? theme_get_setting('nav_font_size') : 14,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'nav-font-size-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['headings_line_height'] = [
    '#type' => 'range',
    '#title' => t('Headings Line Height'),
    '#default_value' => ((theme_get_setting('headings_line_height') !== NULL)) ? theme_get_setting('headings_line_height') : 1.05,
    '#min' => 0,
    '#max' => 3,
    '#step' => 0.1,
    '#attributes' => [
      'class' => ['dxb-slider', 'headings-line-height-range', 'line-height-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['scale_factor'] = [
    '#type' => 'range',
    '#title' => t('Typography Scale Factor') . ' <small>' . t('(This overrides advanced type controls)') . '</small>',
    '#default_value' => ((theme_get_setting('scale_factor') !== NULL)) ? theme_get_setting('scale_factor') : 1,
    '#min' => 1,
    '#max' => 2,
    '#step' => 0.01,
    '#attributes' => [
      'class' => ['dxb-slider', 'scale-factor-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type'] = [
    '#title' => t('Advanced Type Controls'),
    '#description' => t('If you change the main contorls above these advanced controls are automatically generated and need might re-adjustment.'),
    '#type' => 'details',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['h1_font_size'] = [
    '#type' => 'range',
    '#title' => t('H1 Font Size (Page Title)'),
    '#default_value' => ((theme_get_setting('h1_font_size') !== NULL)) ? theme_get_setting('h1_font_size') : 36,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h1-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['h2_font_size'] = [
    '#type' => 'range',
    '#title' => t('H2 Font Size'),
    '#default_value' => ((theme_get_setting('h2_font_size') !== NULL)) ? theme_get_setting('h2_font_size') : 16,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h2-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['h3_font_size'] = [
    '#type' => 'range',
    '#title' => t('H3 Font Size'),
    '#default_value' => ((theme_get_setting('h3_font_size') !== NULL)) ? theme_get_setting('h3_font_size') : 14,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h3-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['h4_font_size'] = [
    '#type' => 'range',
    '#title' => t('H4 Font Size'),
    '#default_value' => ((theme_get_setting('h4_font_size') !== NULL)) ? theme_get_setting('h4_font_size') : 12,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h4-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['blockquote_font_size'] = [
    '#type' => 'range',
    '#title' => t('Blockquote Font Size'),
    '#default_value' => ((theme_get_setting('blockquote_font_size') !== NULL)) ? theme_get_setting('blockquote_font_size') : 16,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'blockquote-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['blockquote_line_height'] = [
    '#type' => 'range',
    '#title' => t('Blockquote Line Height'),
    '#default_value' => ((theme_get_setting('blockquote_line_height') !== NULL)) ? theme_get_setting('blockquote_line_height') : 1.5,
    '#min' => 0,
    '#max' => 3,
    '#step' => 0.1,
    '#attributes' => [
      'class' => ['dxb-slider', 'blockquote-line-height-range', 'line-height-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['headings_letter_spacing'] = [
    '#type' => 'range',
    '#title' => t('Headings Letter Spacing'),
    '#default_value' => ((theme_get_setting('headings_letter_spacing') !== NULL)) ? theme_get_setting('headings_letter_spacing') : 0,
    '#min' => -0.1,
    '#max' => 0.3,
    '#step' => 0.01,
    '#attributes' => [
      'class' => ['dxb-slider', 'headings-letter-spacing-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['headings_uppercase'] = [
    '#type' => 'checkbox',
    '#title' => t('Headings Uppercase'),
    '#default_value' => ((theme_get_setting('headings_uppercase') !== NULL)) ? theme_get_setting('headings_uppercase') : FALSE,
    '#size' => 9,
    '#maxlength' => 9,
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['headings_bold'] = [
    '#type' => 'checkbox',
    '#title' => t('Headings Bold'),
    '#default_value' => ((theme_get_setting('headings_bold') !== NULL)) ? theme_get_setting('headings_bold') : FALSE,
    '#size' => 9,
    '#maxlength' => 9,
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type'] = [
    '#title' => t('Mobile Type Controls'),
    '#type' => 'details',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type']['body_mobile_font_size'] = [
    '#type' => 'range',
    '#title' => t('Body Font Size'),
    '#default_value' => ((theme_get_setting('body_mobile_font_size') !== NULL)) ? theme_get_setting('body_mobile_font_size') : 14,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'body-mobile-font-size-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type']['nav_mobile_font_size'] = [
    '#type' => 'range',
    '#title' => t('Nav Font Size'),
    '#default_value' => ((theme_get_setting('nav_mobile_font_size') !== NULL)) ? theme_get_setting('nav_mobile_font_size') : 14,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'nav-mobile-font-size-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type']['h1_mobile_font_size'] = [
    '#type' => 'range',
    '#title' => t('H1 Font Size (Page Title)'),
    '#default_value' => ((theme_get_setting('h1_mobile_font_size') !== NULL)) ? theme_get_setting('h1_mobile_font_size') : 36,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h1-mobile-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type']['h2_mobile_font_size'] = [
    '#type' => 'range',
    '#title' => t('H2 Font Size'),
    '#default_value' => ((theme_get_setting('h2_mobile_font_size') !== NULL)) ? theme_get_setting('h2_mobile_font_size') : 30,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h2-mobile-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type']['h3_mobile_font_size'] = [
    '#type' => 'range',
    '#title' => t('H3 Font Size'),
    '#default_value' => ((theme_get_setting('h3_mobile_font_size') !== NULL)) ? theme_get_setting('h3_mobile_font_size') : 16,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h3-mobile-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type']['h4_mobile_font_size'] = [
    '#type' => 'range',
    '#title' => t('H4 Font Size'),
    '#default_value' => ((theme_get_setting('h4_mobile_font_size') !== NULL)) ? theme_get_setting('h4_mobile_font_size') : 14,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'h4-mobile-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['advanced_type']['mobile_type']['blockquote_mobile_font_size'] = [
    '#type' => 'range',
    '#title' => t('Blockquote Font Size'),
    '#default_value' => ((theme_get_setting('blockquote_mobile_font_size') !== NULL)) ? theme_get_setting('blockquote_mobile_font_size') : 18,
    '#min' => 8,
    '#max' => 100,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'blockquote-mobile-font-size-range', 'font-size-slider'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['divider'] = [
    '#title' => t('Divider'),
    '#type' => 'details',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#suffix' => _dxpr_theme_type_preview(),
  ];

  $form['dxpr_theme_settings']['typography']['divider']['divider_thickness'] = [
    '#type' => 'range',
    '#title' => t('Divider Thickness'),
    '#default_value' => ((theme_get_setting('divider_thickness') !== NULL)) ? theme_get_setting('divider_thickness') : 4,
    '#min' => 8,
    '#max' => 20,
    '#step' => 1,
    '#attributes' => [
      'class' => ['dxb-slider', 'divider-thickness-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['divider']['divider_length'] = [
    '#type' => 'range',
    '#title' => t('Divider Length') . ' <small>' . t('(0 = full width)') . '</small>',
    '#default_value' => ((theme_get_setting('divider_length') !== NULL)) ? theme_get_setting('divider_length') : 100,
    '#min' => 0,
    '#max' => 500,
    '#step' => 10,
    '#attributes' => [
      'class' => ['dxb-slider', 'divider-length-range'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['divider']['divider_position'] = [
    '#type' => 'range',
    '#title' => t('Divider Position'),
    '#default_value' => ((theme_get_setting('divider_position') !== NULL)) ? theme_get_setting('divider_position') : 1,
    '#min' => 1,
    '#max' => 3,
    '#step' => 1,
    '#states' => [
      'invisible' => [
        ':input[name="divider_length"]' => ['value' => '0'],
      ],
    ],
    '#attributes' => [
      'class' => ['dxb-slider', 'divider-position-range', 'element-invisible'],
      'data-dxb-slider' => TRUE,
    ],
  ];

  $form['dxpr_theme_settings']['typography']['divider']['divider_color'] = [
    '#type' => 'select',
    '#title' => t('Divider Color'),
    '#default_value' => theme_get_setting('divider_color'),
    '#options' => _dxpr_theme_color_options($theme),
  ];

  $form['dxpr_theme_settings']['typography']['divider']['divider_color_custom'] = [
    '#type' => 'textfield',
    '#title' => t('Custom Value'),
    '#default_value' => ((theme_get_setting('divider_color_custom') !== NULL)) ? theme_get_setting('divider_color_custom') : '#eeeeee',
    '#states' => [
      'visible' => [
        'select[name="divider_color"]' => ['value' => 'custom'],
      ],
    ],
  ];
}
