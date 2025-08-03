<?php

namespace Drupal\pdf\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * @FieldFormatter(
 *  id = "pdf_default",
 *  label = @Translation("PDF: Default viewer of PDF.js"),
 *  description = @Translation("Use the default viewer like http://mozilla.github.io/pdf.js/web/viewer.html."),
 *  field_types = {"file"}
 * )
 */
class PdfDefault extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'keep_pdfjs' => TRUE,
      'width' => '100%',
      'height' => '',
      'page' => NULL,
      'zoom' => NULL,
      'custom_zoom' => NULL,
      'pagemode' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $field_name = $this->fieldDefinition->getName();

    $elements['keep_pdfjs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always use pdf.js'),
      '#default_value' => $this->getSetting('keep_pdfjs'),
      '#description' => t("Use pdf.js even when the browser has Adobe Reader Plugin, WebKit PDF Reader for Safari or the PDF Reader for Chrome (Chrome's default alternative to the Adobe Reader Plugin) installed."),
    ];

    $elements['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#default_value' => $this->getSetting('width'),
      '#description' => $this->t('Width of the viewer. Ex: 250px or 100%'),
    ];

    $elements['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#default_value' => $this->getSetting('height'),
      '#description' => $this->t('Height of the viewer. Ex: 250px or 100%'),
    ];
    // Extra Options.
    $elements['page'] = [
      '#type' => 'number',
      '#title' => $this->t('Initial page'),
      '#default_value' => $this->getSetting('page'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][keep_pdfjs]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $elements['zoom'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom Level'),
      '#default_value' => $this->getSetting('zoom'),
      '#options' => [
        '' => $this->t('- None -'),
        'auto' => $this->t('Automatic Zoom'),
        'page-actual' => $this->t('Actual Size'),
        'page-fit' => $this->t('Fit Page'),
        'page-width' => $this->t('Full Width'),
        'custom' => $this->t('Custom Scale'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][keep_pdfjs]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $elements['custom_zoom'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom Zoom Level (%)'),
      '#default_value' => $this->getSetting('custom_zoom'),
      '#min' => 5,
      '#step' => 1,
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][zoom]"]' => ['value' => 'custom'],
        ],
        'required' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][zoom]"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $elements['pagemode'] = [
      '#type' => 'select',
      '#group' => 'extra_options',
      '#title' => $this->t('Page Mode'),
      '#default_value' => $this->getSetting('pagemode'),
      '#options' => [
        '' => $this->t('- None -'),
        'thumbs' => $this->t('Thumbnails'),
        'bookmarks' => $this->t('Bookmarks'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][keep_pdfjs]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $keep_pdfjs = $this->getSetting('keep_pdfjs');
    $width = $this->getSetting('width');
    $height = $this->getSetting('height');
    if (empty($keep_pdfjs) && empty($width) && empty($height)) {
      $summary[] = $this->t('No settings');
    }
    else {
      $summary[] = t('Use pdf.js even when users have PDF reader plugin: @keep_pdfjs', ['@keep_pdfjs' => $keep_pdfjs ? t('Yes') : t('No')]) . '. ' . t('Width: @width , Height: @height', [
        '@width' => $width,
        '@height' => $height,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $config = \Drupal::config('pdf.settings');
    $viewer_path = $config->get('custom_viewer') ? $config->get('custom_viewer') : base_path() . 'libraries/pdf.js/web/viewer.html';
    $keep_pdfjs = $this->getSetting('keep_pdfjs');
    $extra_options = array_filter(array_intersect_key($this->getSettings(), array_flip([
      'page',
      'zoom',
      'custom_zoom',
      'pagemode',
    ])));
    if (isset($extra_options['zoom']) && $extra_options['zoom'] == 'custom') {
      $extra_options['zoom'] = $extra_options['custom_zoom'];
    }
    unset($extra_options['custom_zoom']);
    $query = UrlHelper::buildQuery($extra_options);
    foreach ($items as $delta => $item) {
      if ($item->entity->getMimeType() == 'application/pdf') {
        $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($item->entity->getFileUri());
        $iframe_src = \Drupal::service('file_url_generator')->generateAbsoluteString($viewer_path) . '?file=' . rawurlencode($file_url);
        $iframe_src = !empty($query) && $keep_pdfjs ? $iframe_src . '#' . $query : $iframe_src;
        $html = [
          '#theme' => 'file_pdf',
          '#attributes' => [
            'class' => ['pdf'],
            'webkitallowfullscreen' => '',
            'mozallowfullscreen' => '',
            'allowfullscreen' => '',
            'frameborder' => 'no',
            'width' => $this->getSetting('width'),
            'height' => $this->getSetting('height'),
            'src' => $iframe_src,
            'data-src' => $file_url,
            'title' => $item->entity->label(),
          ],
        ];
        $elements[$delta] = $html;
      }
      else {
        $elements[$delta] = [
          '#theme' => 'file_link',
          '#file' => $item->entity,
        ];
      }
    }
    if ($keep_pdfjs != TRUE) {
      $elements['#attached']['library'][] = 'pdf/default';
    }
    return $elements;
  }

}
