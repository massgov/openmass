<?php
/**
 * Drupal settings file.
 *
 * This is the entrypoint for loading Drupal's configuration.  This file should
 * be used for any configuration that applies to ALL environments.  If you want
 * to apply configuration for Docker or Acquia, use settings.vm.php or
 * settings.acquia.php.
 */

// see: https://docs.acquia.com/acquia-cloud/develop/env-variable
// for why this is first.
if (file_exists('/var/www/site-php')) {
  require "/var/www/site-php/massgov/massgov-settings.inc";
}

// Include deployment identifier to invalidate internal twig cache.
if (file_exists($app_root . '/sites/deployment_id.php')) {
  require $app_root . '/sites/deployment_id.php';
}

/**
 * Anonymous function to configure memcache for an environment.
 *
 * We keep this here so it can be invoked in settings.vm and settings.acquia.
 *
 * @see https://docs.acquia.com/article/resolving-installation-problems-drupal-8-memcache
 */
$configureMemcache = function($settings) use ($app_root, $site_path, $class_loader) {

  $class_loader->addPsr4('Drupal\\memcache\\', 'modules/contrib/memcache/src');
  $class_loader->register();
  $settings['container_yamls'][] = DRUPAL_ROOT . '/modules/contrib/memcache/memcache.services.yml';

  // Define custom bootstrap container definition to use Memcache for cache.container.
  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'database' => [
        'class' => 'Drupal\Core\Database\Connection',
        'factory' => 'Drupal\Core\Database\Database::getConnection',
        'arguments' => ['default'],
      ],
      'settings' => [
        'class' => 'Drupal\Core\Site\Settings',
        'factory' => 'Drupal\Core\Site\Settings::getInstance',
      ],
      'memcache.settings' => [
        'class' => 'Drupal\memcache\MemcacheSettings',
        'arguments' => ['@settings'],
      ],
      'memcache.factory' => [
        'class' => 'Drupal\memcache\Driver\MemcacheDriverFactory',
        'arguments' => ['@memcache.settings'],
      ],
      'memcache.timestamp.invalidator.bin' => [
        'class' => 'Drupal\memcache\Invalidator\MemcacheTimestampInvalidator',
        # Adjust tolerance factor as appropriate when not running memcache on localhost.
        'arguments' => ['@memcache.factory', 'memcache_bin_timestamps', 0.001],
      ],
      'memcache.backend.cache.container' => [
        'class' => 'Drupal\memcache\DrupalMemcacheInterface',
        'factory' => ['@memcache.factory', 'get'],
        'arguments' => ['container'],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\Core\Cache\DatabaseCacheTagsChecksum',
        'arguments' => ['@database'],
      ],
      'cache.container' => [
        'class' => 'Drupal\memcache\MemcacheBackend',
        'arguments' => ['container', '@memcache.backend.cache.container', '@cache_tags_provider.container', '@memcache.timestamp.invalidator.bin', '@memcache.settings'],
      ],
    ],
  ];

  // We know we use the Memcached extension in all environments.
  $settings['memcache']['extension'] = 'Memcached';

  // Replace lock implementation with the memcache version:
  $settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.memcache.yml';

  // Decrease latency.
  $settings['memcache']['options'][Memcached::OPT_TCP_NODELAY] = TRUE;

  // Override default configuration for static cache bins.
  // Specialized cache bin configuration.
  // See https://jira.state.ma.us/browse/DP-5906 for how this was selected.
  $settings['cache']['bins']['bootstrap'] = 'cache.backend.memcache';
  $settings['cache']['bins']['default'] = 'cache.backend.memcache';
  $settings['cache']['bins']['config'] = 'cache.backend.memcache';
  $settings['cache']['bins']['discovery'] = 'cache.backend.memcache';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.database';

  // Acquia doesn't recommend these in memcache and we are seeing Revision confusion at https://edit.mass.gov/info-details/covid-19-response-reporting/revisions
  // See https://github.com/acquia/memcache-settings/blob/main/memcache.settings.php
  // $settings['cache']['bins']['entity'] = 'cache.backend.memcache';
  // $settings['cache']['bins']['render'] = 'cache.backend.memcache';
  // $settings['cache']['bins']['data'] = 'cache.backend.memcache';
  // $settings['cache']['bins']['menu'] = 'cache.backend.memcache';
  // All other cache bins are stored in the database.

  return $settings;
};

$settings['config_sync_directory'] = '../conf/drupal/config';

$settings['state_cache'] = TRUE;

/**
 * Page caching:
 *
 * By default, Drupal sends a "Vary: Cookie" HTTP header for anonymous page
 * views. This tells a HTTP proxy that it may return a page from its local
 * cache without contacting the web server, if the user sends the same Cookie
 * header as the user who originally requested the cached page. Without "Vary:
 * Cookie", authenticated users would also be served the anonymous page from
 * the cache. If the site has mostly anonymous users except a few known
 * editors/administrators, the Vary header can be omitted. This allows for
 * better caching in HTTP proxies (including reverse proxies), i.e. even if
 * clients send different cookies, they still get content served from the cache.
 * However, authenticated users should access the site directly (i.e. not use an
 * HTTP proxy, and bypass the reverse proxy if one is used) in order to avoid
 * getting cached pages from the proxy.
 *
 * @see \Drupal\Core\EventSubscriber\FinishResponseSubscriber::setResponseCacheable.
 */
$settings['omit_vary_cookie'] = TRUE;

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

// Database cache bins size
// -1 for unlimited similar to 8.3 OOB
$settings['database_cache_max_rows']['default'] = -1;
// Speed up entity updates by taking advantage of available memory.
$settings['entity_update_batch_size'] = 250;

// Start with stage for all envs. This gets overridden for Prod in settings.acquia.php
$settings['mass_caching.hosts'] = [
  'stage.mass.gov',
  'edit.stage.mass.gov',
];

// Entity Usage settings and config
$config['entity_usage_queue_tracking.settings']['queue_tracking'] = TRUE;
$settings['queue_service_entity_usage_tracker'] = 'queue_unique.database';

// If in an Acquia Cloud environment
if(isset($_ENV['AH_SITE_ENVIRONMENT'])) {
  // if in acquia...
  require $app_root . '/' . $site_path . '/settings.acquia.php';

  // If there is a need in the future to have a production configuration
  // if also prod...
  // if(isset($_ENV['AH_PRODUCTION']) {
  //   require $app_root . '/' . $site_path . '/settings.acquia-prod.php';
  // }
} else {
  // if not in Acquia
  require $app_root . '/' . $site_path . '/settings.vm.php';

  // Override as needed via a settings.local.php. Use docroot/sites/example.settings.local.php as a model.
  if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
    include $app_root . '/' . $site_path . '/settings.local.php';
  }

  if (getenv('DOCKER_ENV') !== 'ci' && file_exists($app_root . '/' . $site_path . '/services.local.yml')) {
    $settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.local.yml';
  }
}

// Now that secrets file was included, perform config overrides.
$config['mass_analytics.settings']['looker_studio_url'] = getenv('LOOKER_STUDIO_URL');
$config['mailchimp_transactional.settings']['mailchimp_transactional_api_key'] = getenv('MANDRILL_API_KEY');
$config['key.key.real_aes']['key_provider_settings']['key_value'] = getenv('REAL_AES_KEY_VALUE');
$config['geocoder.geocoder_provider.opencage']['configuration']['apiKey'] = getenv('GEOCODER_OPENCAGE_API_KEY');

$databases['default']['default']['init_commands'] = [
  'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
];

// Add database connection for Service Details migration.
// @todo Remove once thats complete.
$databases['migrate'] = $databases['default'];

// Environment indicator. See https://architecture.lullabot.com/adr/20210609-environment-indicator/
if (isset($_ENV['AH_SITE_ENVIRONMENT'])) {
  $config['environment_indicator.indicator']['name'] = $_ENV['AH_SITE_ENVIRONMENT'];
  switch ($_ENV['AH_SITE_ENVIRONMENT']) {
    case 'prod':
      // Green background.
      $config['environment_indicator.indicator']['bg_color'] = '#9CC2AB';
      break;

    default:
      // Gray background.
      $config['environment_indicator.indicator']['bg_color'] = '#BABABA';
      break;
  }
}
else {
  // We are in local or CI or Tugboat.
  $config['environment_indicator.indicator']['name'] = getenv('TUGBOAT_ROOT') ? 'Tugboat' : 'Local';
  // Gray background.
  $config['environment_indicator.indicator']['bg_color'] = '#BABABA';
}

// phpunit.xml.dist sets -1 for memory_limit so just change for other cli requests.
if (PHP_SAPI === 'cli' && ini_get('memory_limit')) {
  ini_set('memory_limit', '4096M');
}

// https://github.com/drush-ops/drush/issues/676#issuecomment-1136068584
if (PHP_SAPI === 'cli') {
  $databases['default']['default']['init_commands']['wait_timeout'] = 'SET SESSION wait_timeout = 3600';
}

if (extension_loaded('newrelic')) { // Ensure PHP agent is available
  newrelic_disable_autorum();
}
