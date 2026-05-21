<?php

namespace Drupal\mass_caching;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\mass_caching\EventSubscriber\AkamaiCacheableResponseSubscriber;

/**
 * Alters service definitions for Mass.gov caching integrations.
 */
class MassCachingServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('akamai.cacheable_response_subscriber')) {
      $container
        ->getDefinition('akamai.cacheable_response_subscriber')
        ->setClass(AkamaiCacheableResponseSubscriber::class);
    }
  }

}
