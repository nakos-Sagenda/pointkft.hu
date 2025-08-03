<?php

declare(strict_types=1);

namespace Drupal\composer_deploy;

use Drupal\Component\Utility\Html;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension.
 */
final class ComposerDeployTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    $functions[] = new TwigFunction(
      'composer_deploy_append',
      static function (Markup $template, string $selector, Markup $content) {
        $dom = Html::load($template);
        $xpath = new \DOMXPath($dom);

        /** @var \DOMNodeList $element */
        $element = $xpath->query("//ul[contains(@class, 'project-update__version-links')]");
        if ($element instanceof \DOMNodeList) {
          $fragment = $dom->createDocumentFragment();
          $fragment->appendXML((string) $content);
          $element->item(0)->appendChild($fragment);
        }

        return new Markup(Html::serialize($dom), 'UTF-8');
      },
    );
    return $functions;
  }

}
