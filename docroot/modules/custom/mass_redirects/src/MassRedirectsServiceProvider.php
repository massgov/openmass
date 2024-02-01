<?php

namespace Drupal\mass_redirects;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * DI Container alterations for Mass Redirects.
 */
class MassRedirectsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Override \Drupal\r4032login\EventSubscriber\R4032LoginSubscriber.
    if ($container->hasDefinition('r4032login.subscriber')) {
      $definition = $container->getDefinition('r4032login.subscriber');
      $definition->setClass('Drupal\mass_redirects\EventSubscriber\MassRedirectsLoginSubscriber');
    }
  }

}
