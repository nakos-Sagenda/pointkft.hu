<?php

namespace Drupal\remove_http_headers\Config;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Manages module configuration.
 *
 * @package Drupal\remove_http_headers\Configuration
 */
class ConfigManager {

  const HEADERS_TO_REMOVE_CACHE_ID = 'remove_http_headers.settings.headers_to_remove';
  const HEADERS_TO_REMOVE_CACHE_TAG = self::HEADERS_TO_REMOVE_CACHE_ID;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The module configuration.
   *
   * Should not be accessed directly.
   * Instead, ConfigManager::getModuleConfig() should be used.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $moduleConfig;

  /**
   * The default cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * ConfigManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The default cache backend.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache) {
    $this->configFactory = $config_factory;
    $this->cache = $cache;
    $this->moduleConfig = $config_factory->getEditable('remove_http_headers.settings');
  }

  /**
   * Gets the HTTP headers that should be removed.
   *
   * @param bool $skip_cache
   *   Flag whether the data should be retrieved directly from config.
   *
   * @return string[]
   *   HTTP headers that should be removed.
   *   An empty array if the header data is not saved in the correct format.
   */
  public function getHeadersToRemove(bool $skip_cache = FALSE): array {
    $headersToRemove = [];

    if ($skip_cache === FALSE) {
      $cachedHeadersToRemoveData = $this->getHeadersToRemoveFromCache();

      if (is_array($cachedHeadersToRemoveData)) {
        // Use the cached data.
        return $cachedHeadersToRemoveData;
      }
    }

    /* Load the headers that should be removed from config
    if they are not in the cache or the cache is not used. */
    $headersToRemoveData = $this->getHeadersToRemoveFromConfig();

    // Only use the data if the format is correct.
    if (is_array($headersToRemoveData)) {
      $headersToRemove = $headersToRemoveData;

      /* Save the data to cache, so it is cached for the next access. */
      $this->saveHeadersToRemoveToCache($headersToRemove);
    }

    return $headersToRemove;
  }

  /**
   * Returns the configured headers to remove from cache.
   *
   * @return array|null
   *   The cached data.
   *   NULL if not in cache or invalid format.
   */
  protected function getHeadersToRemoveFromCache(): ?array {
    $cachedHeadersToRemove = FALSE;

    $headersToRemoveCacheData = $this->cache->get(self::HEADERS_TO_REMOVE_CACHE_ID);

    if ($headersToRemoveCacheData instanceof \stdClass) {
      if (property_exists($headersToRemoveCacheData, 'data')) {
        $cachedHeadersToRemove = $headersToRemoveCacheData->data;
      }
    }

    return $this->validateHeadersToRemoveDataFormat($cachedHeadersToRemove);
  }

  /**
   * Returns the configured headers to remove from configuration.
   *
   * @return array|null
   *   The config data.
   *   NULL if not in cache or invalid format.
   */
  protected function getHeadersToRemoveFromConfig(): ?array {
    $headersToRemoveConfigData = $this->getModuleConfig()->get('headers_to_remove');

    return $this->validateHeadersToRemoveDataFormat($headersToRemoveConfigData);
  }

  /**
   * Checks if given array containing headers to remove has the correct format.
   *
   * @param mixed|null $headersToRemove
   *   Expected: Array with headers that should be removed.
   *
   * @return array|null
   *   The given array.
   *   NULL if not every array item is a string.
   */
  protected function validateHeadersToRemoveDataFormat(mixed $headersToRemove = NULL): ?array {
    if (!is_array($headersToRemove)) {
      return NULL;
    }

    foreach ($headersToRemove as $headerToRemove) {
      if (!is_string($headerToRemove)) {
        return NULL;
      }
    }

    return $headersToRemove;
  }

  /**
   * Saves given array to the cache.
   *
   * @param array $headersToRemove
   *   Array of headers that should be removed.
   */
  protected function saveHeadersToRemoveToCache(array $headersToRemove): void {
    $this->cache->set(self::HEADERS_TO_REMOVE_CACHE_ID, $headersToRemove, CacheBackendInterface::CACHE_PERMANENT, [self::HEADERS_TO_REMOVE_CACHE_TAG]);
  }

  /**
   * Returns the module config.
   *
   * Gets the config from the object property if it is set.
   * Otherwise, sets and returns it.
   *
   * @return \Drupal\Core\Config\Config
   *   The module config.
   */
  protected function getModuleConfig(): Config {
    return $this->moduleConfig;
  }

  /**
   * Saves the HTTP headers that should be removed to the configuration.
   *
   * @param string[] $headersToRemove
   *   HTTP headers that should be removed.
   */
  public function saveHeadersToRemoveToConfig(array $headersToRemove): void {
    $this->getModuleConfig()->set('headers_to_remove', $headersToRemove);
    $this->getModuleConfig()->save();

    $this->invalidateHeadersToRemoveCache();
  }

  /**
   * Invalidates the headers to remove cache.
   */
  protected function invalidateHeadersToRemoveCache(): void {
    $this->cache->invalidate(self::HEADERS_TO_REMOVE_CACHE_TAG);
  }

  /**
   * Whether, or not, the route with given name should be protected.
   *
   * @param string $headerName
   *   A HTTP header name.
   *
   * @return bool
   *   Should HTTP header with given name be removed.
   */
  public function shouldHeaderBeRemoved(string $headerName): bool {
    return in_array($headerName, $this->getHeadersToRemove());
  }

}
