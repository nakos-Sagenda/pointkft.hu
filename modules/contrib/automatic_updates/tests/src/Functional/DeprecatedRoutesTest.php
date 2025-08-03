<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\Core\Url;

/**
 * @covers \Drupal\automatic_updates\Controller\UpdateController::redirectDeprecatedRoute
 * @covers \Drupal\automatic_updates\Routing\RouteSubscriber
 * @group automatic_updates
 * @group legacy
 * @internal
 */
class DeprecatedRoutesTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that deprecated routes are redirected with an informative message.
   */
  public function testDeprecatedRoutesAreRedirected(): void {
    $account = $this->createUser([
      'administer software updates',
      'administer site configuration',
    ]);
    $this->drupalLogin($account);

    $routes = [
      'automatic_updates.module_update' => ['update.module_update', NULL],
      'automatic_updates.report_update' => ['update.report_update', NULL],
      'automatic_updates.theme_update' => ['update.theme_update', NULL],
      'automatic_updates.update_readiness' => ['automatic_updates.status_check', 'system.status'],
    ];
    $assert_session = $this->assertSession();

    foreach ($routes as $deprecated_route => [$redirect_route, $final_route]) {
      $deprecated_url = Url::fromRoute($deprecated_route)
        ->setAbsolute()
        ->toString();
      $redirect_url = Url::fromRoute($redirect_route)
        ->setAbsolute()
        ->toString();
      if ($final_route) {
        $final_url = Url::fromRoute($final_route)
          ->setAbsolute()
          ->toString();
      }

      $this->drupalGet($deprecated_url);
      $assert_session->statusCodeEquals(200);
      $assert_session->addressEquals($final_url ?? $redirect_url);
      $assert_session->responseContains("This page was accessed from $deprecated_url, which is deprecated and will not work in the next major version of Automatic Updates. Please use <a href=\"$redirect_url\">$redirect_url</a> instead.");
    }
  }

}
