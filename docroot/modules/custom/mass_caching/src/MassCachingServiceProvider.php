<?php

namespace Drupal\mass_caching;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Helper Class MassCachingServiceProvider.
 *
 * Class to override Drupal core CSSCollectionRenderer
 * and JsCollectionRenderer services.
 */
class MassCachingServiceProvider extends ServiceProviderBase implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Override CSS collection renderer service.
    $current_css_service_def = $container->getDefinition('asset.css.collection_renderer');
    $current_css_service_def->setClass("Drupal\mass_caching\MassCachingCSSCollectionRenderer");
    $current_css_service_def->addArgument(new Reference("state"));

    // Override JS collection renderer service.
    $current_js_service_def = $container->getDefinition('asset.js.collection_renderer');
    $current_js_service_def->setClass("Drupal\mass_caching\MassCachingJSCollectionRenderer");
    $current_js_service_def->addArgument(new Reference("state"));
  }

}
