<?php

namespace Drupal\mass_utility;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * DI Container alterations for Mass Utility.
 */
class MassUtilityServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Add a Monolog processor for backtrace removal.
    if ($container->hasParameter('monolog.processors')) {
      $processors = $container->getParameter('monolog.processors');
      $processors[] = 'backtrace_removal';
      $container->setParameter('monolog.processors', $processors);
    }
  }

}
