<?php

namespace MassGov\Behat;

use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Reference;

class Extension implements ExtensionInterface {

  public function process(ContainerBuilder $container) {
    $container->setParameter('drupal.driver.drupal.class', 'MassGov\Behat\Driver\EnhancedDriver');
    $container->setParameter('drupal.driver.cores.8.class', 'MassGov\Behat\Driver\Cores\Drupal8');
    $container->setParameter('drupal.authentication_manager.class', 'MassGov\Behat\DirectAuthenticationManager');
    $container->getDefinition('drupal.authentication_manager')
      ->addMethodCall('setDriver', [new Reference('drupal.drupal')]);
  }

  public function getConfigKey() {
    return 'massgov';
  }

  public function initialize(ExtensionManager $extensionManager) {
    // Nothing.
  }

  public function configure(ArrayNodeDefinition $builder) {
    // Nothing.
  }

  public function load(ContainerBuilder $container, array $config) {
    // Nothing.
  }
}
