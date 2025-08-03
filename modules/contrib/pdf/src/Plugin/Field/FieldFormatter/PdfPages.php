<?php

namespace Drupal\pdf\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * @FieldFormatter(
 *  id = "pdf_pages",
 *  label = @Translation("PDF: Continuous scroll (experimental)"),
 *  description = @Translation("Don&#039;t use this to display big PDF file."),
 *  field_types = {"file"}
 * )
 */
class PdfPages extends FormatterBase {

  public static function defaultSettings() {
    return [
      'scale' => 1,
    ] + parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['scale'] = [
      '#type' => 'textfield',
      '#title' => t('Set the scale of PDF pages'),
      '#default_value' => $this->getSetting('scale'),
    ];
    return $elements;
  }

  public function settingsSummary() {
    $summary = [];
    $scale = $this->getSetting('scale');
    if (empty($scale)) {
      $summary[] = $this->t('No settings');
    }
    else {
      $summary[] = t('Scale: @scale', ['@scale' => $scale]);
    }

    return $summary;
  }

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      if ($item->entity->getMimeType() == 'application/pdf') {
        $scale = $this->getSetting('scale');
        $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($item->entity->getFileUri());
        $html = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          //'#value' => TODO,
          '#attributes' => [
            'class' => ['pdf-pages'],
            'id' => ['pdf-pages-' . $delta],
            'file' => [$file_url],
            'scale' => [$scale],
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
