<?php

namespace Drupal\pdf\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * @FieldFormatter(
 *  id = "pdf_thumbnail",
 *  label = @Translation("PDF: Display the first page"),
 *  description = @Translation("Display the first page of the PDF file."),
 *  field_types = {"file"}
 * )
 */
class PdfThumbnail extends FormatterBase {
  public static function defaultSettings() {
    return [
      'scale' => 1,
      'width' => '',
      'height' => '',
    ] + parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['scale'] = [
      '#type' => 'textfield',
      '#title' => t('Set the scale of PDF pages'),
      '#default_value' => $this->getSetting('scale'),
    ];
    $elements['width'] = [
      '#type' => 'textfield',
      '#title' => 'Width',
      '#default_value' => $this->getSetting('width'),
      '#description' => t('Width of the viewer. Ex: 250px or 100%'),
    ];
    $elements['height'] = [
      '#type' => 'textfield',
      '#title' => 'Height',
      '#default_value' => $this->getSetting('height'),
      '#description' => t('Height of the viewer. Ex: 250px or 100%'),
    ];
    return $elements;
  }

  public function settingsSummary() {
    $summary = [];

    $scale = $this->getSetting('scale');
    $width = $this->getSetting('width');
    $height = $this->getSetting('height');
    if (empty($scale) && empty($width) && empty($height)) {
      $summary[] = $this->t('No settings');
    }
    else {
      $summary[] = t('Scale: @scale, Width: @width, Height: @height', [
        '@scale' => $scale,
        '@width' => $width,
        '@height' => $height
      ]);
    }
    return $summary;
  }

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      if ($item->entity->getMimeType() == 'application/pdf') {
        $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($item->entity->getFileUri());
        $html = [
          '#type' => 'html_tag',
          '#tag' => 'canvas',
          //'#value' => ,
          '#attributes' => [
            'class' => ['pdf-thumbnail', 'pdf-canvas'],
            'id' => ['pdf-thumbnail-' . $delta],
            'file' => $file_url,
            'scale' => $this->getSetting('scale'),
            'style' => 'width:' . $this->getSetting('width') . ';height:' . $this->getSetting('height') . ';',
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
    $elements['#attached']['library'][] = 'pdf/drupal.pdf';
    $worker = \Drupal::service('file_url_generator')->generateAbsoluteString(base_path() . 'libraries/pdf.js/build/pdf.worker.js');
    $elements['#attached']['drupalSettings'] = [
      'pdf' => [
        'workerSrc' => $worker,
      ],
    ];

    return $elements;
  }
}
