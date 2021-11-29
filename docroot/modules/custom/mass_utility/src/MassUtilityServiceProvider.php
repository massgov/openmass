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

    if ($container->hasDefinition('entity_hierarchy.breadcrumb')) {
      $definition = $container->getDefinition('entity_hierarchy.breadcrumb');
      $definition->setClass('Drupal\mass_utility\MassUtilityBreadcrumb');
    }
  }

}
