<?php

namespace Drupal\mass_scheduled_transitions;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Defines a service provider for the Mass Scheduled Transitions module.
 */
class MassScheduledTransitionsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides ScheduledTransitionsNewRevision event subscriber.
    $definition = $container->getDefinition('scheduled_transitions.new_revision');
    $definition->setClass('\Drupal\mass_scheduled_transitions\EventSubscriber\MassScheduledTransitionsNewRevision');
  }

}
