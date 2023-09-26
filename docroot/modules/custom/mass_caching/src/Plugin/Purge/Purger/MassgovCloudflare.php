<?php

namespace Drupal\mass_caching\Plugin\Purge\Purger;

use CloudFlarePhpSdk\ApiEndpoints\ZoneApi;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom purger for mass.gov's Cloudflare domains.
 *
 * This purger exists to solve a couple of problems:
 *
 * 1. The built-in Cloudflare purger has the wrong API call limits
 *   (see https://www.drupal.org/node/3062379).
 * 2. Hitting API limits causes all purgers to stop due to diagnostics.
 *   (see https://www.drupal.org/node/3062616)
 * 3. We're not sure how badly we're gonna exceed rate limits on tag purges,
 *   so we need a way to count tag purge requests to make sure we're falling
 *   within the API rate limits before we implement tag purging.
 *
 * This purger can probably be replaced with the standard Cloudflare purger
 * once these three issues are resolved.
 *
 * @PurgePurger(
 *   id = "massgov_cloudflare",
 *   label = @Translation("Mass.gov Cloudflare"),
 *   description = @Translation("Purger for CloudFlare."),
 *   types = {"url", "tag"},
 *   multi_instance = FALSE,
 * )
 */
class MassgovCloudflare extends PurgerBase {

  private $config;
  private $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('state')
    );
  }

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory->get('cloudflare.settings');
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRuntimeMeasurement() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    // Nothing to do here, since we've implemented
    // routeTypeToMethod().
  }

  /**
   * {@inheritdoc}
   */
  public function routeTypeToMethod($type) {
    $method = 'invalidate' . ucfirst($type);
    if (method_exists($this, 'invalidate' . ucfirst($type))) {
      return $method;
    }
  }

  /**
   * Executes tag invalidations.
   */
  public function invalidateTag(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
    }
    // We only want to collect data on tag invalidations, not actually
    // execute them for now.
    $this->incrementInvalidations('tag', count($invalidations));

    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
    }
  }

  /**
   * Executes URL invalidations.
   */
  public function invalidateUrl(array $invalidations) {
    $api_key = $this->config->get('apikey');
    $email = $this->config->get('email');
    $zone = $this->config->get('zone_id');

    // Prevent actual invalidation from working without the proper
    // settings. This allows us to skip Cloudflare invalidation
    // for environments that do not have credentials.
    if (!empty($api_key) && !empty($email) && !empty($zone)) {
      $zoneApi = new ZoneApi($api_key, $email);

      $urls = [];
      foreach ($invalidations as $invalidation) {
        $urls[] = $invalidation->getExpression();
        $invalidation->setState(InvalidationInterface::PROCESSING);
      }

      try {
        $zoneApi->purgeIndividualFiles($zone, $urls);
        $this->incrementInvalidations('url', count($invalidations));
      }
      catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        foreach ($invalidations as $invalidation) {
          $invalidation->setState(InvalidationInterface::FAILED);
        }
        return;
      }
    }

    // If we've made it here, invalidation was either skipped, or happened
    // successfully.
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
    }

  }

  /**
   * In order to track the number of invalidations happening, keep a record.
   *
   * This is a poor man's solution to metrics, using state to keep a count of
   * invalidations of each type. Since invalidation happens relatively rarely
   * (ie: not on every request), and already involves a lot of writing, one
   * state write per request that has invalidations seems unlikely to cause a
   * meltdown.
   *
   * @todo Remove this once and clean up state we've collected our data.
   */
  private function incrementInvalidations(string $type, int $count) {
    $stateKey = "mass.cloudflare.${type}.counts";
    $allTypeInvalidations = $this->state->get($stateKey, []);

    $date = \Drupal::service('date.formatter')->format(
      \Drupal::time()->getRequestTime(),
      'custom',
      'Y-m-d'
    );
    if (isset($allTypeInvalidations[$date])) {
      $allTypeInvalidations[$date] += $count;
    }
    else {
      $allTypeInvalidations[$date] = $count;
    }
    $this->state->set($stateKey, $allTypeInvalidations);
  }

}
