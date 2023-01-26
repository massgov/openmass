<?php

namespace Drupal\mass_translations;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Defines a service provider for the Mass Translations module.
 */
class MassTranslationsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('language_manager');
    $definition->setClass('Drupal\mass_translations\MassLanguageManager');
  }

}
