<?php

namespace Drupal\remove_http_headers\StackMiddleware;

use Drupal\remove_http_headers\Config\ConfigManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Executes removal of HTTP response headers.
 *
 * Runs after the page caching middleware took over the request.
 * Because it adds, an additional, HTTP header.
 */
class RemoveHttpHeadersMiddleware implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected HttpKernelInterface $httpKernel;

  /**
   * The config manager service.
   *
   * @var \Drupal\remove_http_headers\Config\ConfigManager
   */
  protected ConfigManager $configManager;

  /**
   * Constructs a RemoveHttpHeadersMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Drupal\remove_http_headers\Config\ConfigManager $config_manager
   *   The config manager service.
   */
  public function __construct(HttpKernelInterface $http_kernel, ConfigManager $config_manager) {
    $this->httpKernel = $http_kernel;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE): Response {
    $response = $this->httpKernel->handle($request, $type, $catch);

    // Only allow removal of HTTP headers on master request.
    if ($type === static::MASTER_REQUEST) {
      $headersToRemove = $this->configManager->getHeadersToRemove();
      foreach ($headersToRemove as $httpHeaderToRemove) {
        $response->headers->remove($httpHeaderToRemove);
      }
    }

    return $response;
  }

}
