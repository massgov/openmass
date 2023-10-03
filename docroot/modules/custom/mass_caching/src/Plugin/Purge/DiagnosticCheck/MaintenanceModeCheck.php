<?php

namespace Drupal\mass_caching\Plugin\Purge\DiagnosticCheck;

use Drupal\Core\State\StateInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks if the system is in Maintenance mode.
 *
 * NOTE: All purging is paused when this check fails, including Acquia purging.
 * During this time, invalidations may pile up.
 *
 * @PurgeDiagnosticCheck(
 *   id = "maintenance_mode",
 *   title = @Translation("Maintenance Mode"),
 *   description = @Translation("Checks to see if we are in maintenance mode before running purges"),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {"massgov_cloudflare"}
 * )
 */
class MaintenanceModeCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {
  /**
   * The settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The state, which provides us maintenance mode status.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    if ($this->state->get('system.maintenance_mode')) {
      $this->recommendation = $this->t('In maintenance mode');
      return self::SEVERITY_ERROR;
    }
    $this->recommendation = $this->t('Not in maintenance mode');
    return self::SEVERITY_OK;
  }

}
