<?php

namespace Drupal\yoast_seo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * SettingsController.
 *
 * Provides the settings pages for the Real-Time SEO module.
 */
class SettingsController extends ControllerBase {

  /**
   * Settings page.
   *
   * @return array
   *   The configuration form.
   */
  public function index() {
    $form = [];

    $xmlsitemap_enabled = \Drupal::moduleHandler()->moduleExists('xmlsitemap');
    $simple_sitemap_enabled = \Drupal::moduleHandler()->moduleExists('simple_sitemap');

    // Check if a sitemap module is installed and enabled.
    if ($xmlsitemap_enabled && $simple_sitemap_enabled) {
      // Discourage users from enabling both sitemap modules as they
      // might interfere.
      $xmlsitemap_description
        = $this->t('It looks like you have both the XML Sitemap and Simple XML Sitemap module enabled. Please uninstall one of them as they could interfere with each other.');
    }
    elseif ($xmlsitemap_enabled) {
      // Inform the user about altering the XML Sitemap configuration on the
      // module configuration page if they have access to do so.
      if (\Drupal::currentUser()->hasPermission('administer xmlsitemap')) {
        $xmlsitemap_description = $this->t(
          'You can configure the XML Sitemap settings at the @url.',
          [
            '@url' => Link::fromTextAndUrl(
              $this->t('configuration page'),
              Url::fromRoute('xmlsitemap.admin_search')
            )->toString(),
          ]
        );
      }
      else {
        $xmlsitemap_description
          = $this->t('You do not have the permission to administer the XML Sitemap.');
      }
    }
    elseif (\Drupal::moduleHandler()->moduleExists('simple_sitemap')) {
      // Inform the user about altering the XML Sitemap configuration on the
      // module configuration page if they have access to do so.
      if (\Drupal::currentUser()->hasPermission('administer simple_sitemap')) {
        $xmlsitemap_description = $this->t(
          'You can configure the Simple XML Sitemap settings at the @url.',
          [
            '@url' => Link::fromTextAndUrl(
              $this->t('configuration page'),
              Url::fromRoute('simple_sitemap.settings')
            )->toString(),
          ]
        );
      }
      else {
        $xmlsitemap_description
          = $this->t('You do not have the permission to administer the Simple XML Sitemap.');
      }
    }
    else {
      // XML Sitemap is not enabled, inform the user they should think about
      // installing and enabling it.
      $xmlsitemap_description = $this->t(
        'You currently do not have a sitemap module enabled. We strongly recommend you to install a sitemap module. You can download the <a href="@project1-url">@project1-name</a> or <a href="@project2-url">@project2-name</a> module to use as sitemap generator.',
        [
          '@project1-url' => 'https://www.drupal.org/project/simple_sitemap',
          '@project1-name' => 'Simple Sitemap',
          '@project2-url' => 'https://www.drupal.org/project/xmlsitemap',
          '@project2-name' => 'XML Sitemap',
        ]
       );
    }

    $form['xmlsitemap'] = [
      '#type' => 'details',
      '#title' => $this->t('Sitemap'),
      '#markup' => $xmlsitemap_description,
      '#open' => TRUE,
    ];

    // Inform the user about altering the Metatag configuration on the module
    // configuration page if they have access to do so.
    // We do not check if the module is enabled since it is our dependency.
    if (\Drupal::currentUser()->hasPermission('administer meta tags')) {
      $metatag_description = $this->t(
        'You can configure and override the Metatag title & description default settings at the @url.',
        [
          '@url' => Link::fromTextAndUrl(
            $this->t('Metatag configuration page'),
            Url::fromRoute('entity.metatag_defaults.collection')
          )->toString(),
        ]
      );
    }
    else {
      $metatag_description
        = $this->t('You currently do not have the permission to administer Metatag.');
    }

    $form['metatag'] = [
      '#type' => 'details',
      '#title' => $this->t('Configure Metatag default templates'),
      '#markup' => $metatag_description,
      '#open' => TRUE,
    ];

    return $form;
  }

}
