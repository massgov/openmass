<?php

namespace Drupal\mass_hierarchy;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * DI Container alterations for Mass Hierarchy.
 */
class MassHierarchyServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Override entity_hierarchy.breadcrumb service.
    if ($container->hasDefinition('entity_hierarchy.breadcrumb')) {
      $definition = $container->getDefinition('entity_hierarchy.breadcrumb');
      $definition->setClass('Drupal\mass_hierarchy\MassHierarchyBasedBreadcrumbBuilder');
      $definition->addArgument(new Reference('mass_microsites.nearest_microsite_lookup'));
    }
  }

}
