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
  global $conf, $databases;
  $conf['acquia_hosting_settings_autoconnect'] = FALSE;
  require "/var/www/site-php/massgov/massgov-settings.inc";
  $databases['default']['default']['init_commands'] = [
    'isolation_level' => "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED",
  ];
  if (function_exists("acquia_hosting_db_choose_active")) {
    acquia_hosting_db_choose_active();
  }
}

// Include deployment identifier to invalidate internal twig cache.
if (file_exists($app_root . '/sites/deployment_id.php')) {
  require $app_root . '/sites/deployment_id.php';
}

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
