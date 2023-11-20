<?php

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

$is_prod = isset($_ENV['AH_PRODUCTION']) && $_ENV['AH_PRODUCTION'];
$cli = php_sapi_name() == 'cli';
$is_mass_gov = preg_match("/\.mass\.gov$/", $_SERVER["HTTP_HOST"]);

/**
 * Loads environment-specific secrets, if available.
 *
 * Required for Mass Feedback Loop (mass_feedback_loop).
 * Expected array structure for mass_feedback_loop:
 *   $settings['mass_feedback_loop']
 *   `-- ['external_api_config']
 *       |-- ['api_base_url']
 *       `-- ['authenticate_header']
 */
$secrets_file = sprintf(
  '/mnt/files/%s.%s/secrets.settings.php',
  $_ENV['AH_SITE_GROUP'],
  $_ENV['AH_SITE_ENVIRONMENT']
);
if (file_exists($secrets_file)) {
  require $secrets_file;
}

/**
 * Loads global secrets.
 *
 * - Used by TFA's encryption profile'.
 * - Used by Akamai module's Key module integration.
 */
$secrets_file_global = '/home/massgov/.app/secrets.settings.php';
if (file_exists($secrets_file_global)) {
  require $secrets_file_global;
}

/**
 * Protect our origin against direct access. We control this access using a shared
 * token, which must be present in the `mass-cdn-fwd` header.
 * mass-cdn-fwd value can be found in the $secrets_file_global file.
 */
if (!$cli && ($is_prod || $is_mass_gov)) {
  if ($_SERVER['HTTP_MASS_CDN_FWD'] !== getenv('MASS_CDN_TOKEN')) {
    throw new AccessDeniedHttpException('Only requests sent through the CDN are allowed.');
  }
}

/**
 * Set the HTTP header name which stores real client IP. Harmless if not provided.
 * We use https://www.drupal.org/project/reverse_proxy_header because of https://www.drupal.org/project/drupal/issues/3223280
 */
$settings['reverse_proxy_header'] = 'HTTP_TRUE_CLIENT_IP';

/**
 * Load Acquia-specific services.
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.acquia.yml';

/**
 * Configure file directory paths.
 */
$settings['file_public_path'] = 'files';
$settings['file_private_path'] = "/mnt/files/{$_ENV['AH_SITE_GROUP']}.{$_ENV['AH_SITE_ENVIRONMENT']}/files-private";
$config['system.file']['path']['temporary'] = "/mnt/gfs/{$_ENV['AH_SITE_GROUP']}.{$_ENV['AH_SITE_ENVIRONMENT']}/tmp";

/**
 *
 * Use memcache.
 *
 * Comment out this line to disable memcache.
 */
$settings = $configureMemcache($settings);

/**
 * Password protect non-prod environments.
 *
 * If the environment is not production, and we're not on CLI, block access
 * unless the authentication requirements have been met.
 *
 * @see https://docs.acquia.com/articles/password-protect-your-non-production-environments-acquia-hosting#phpfpm
 */
if (!$cli && !$is_prod && !in_array($_SERVER['SERVER_NAME'], ['wwwcf.digital.mass.gov', 'editcf.digital.mass.gov', 'stage.mass.gov', 'edit.stage.mass.gov'])) {
  $username = getenv('LOWER_ENVIR_AUTH_USER');
  $password = getenv('LOWER_ENVIR_AUTH_PASS');
  $is_testing_page = strpos($_SERVER['REQUEST_URI'], '/topics/hunting-fishing') !== FALSE;
  $is_oauth = strpos($_SERVER['REQUEST_URI'], '/oauth/token') !== FALSE;
  $is_endpoint = strpos($_SERVER['REQUEST_URI'], '/api/v1/') !== FALSE;
  if (!$is_testing_page && !$is_oauth && !$is_endpoint && !(isset($_SERVER['PHP_AUTH_USER']) && ($_SERVER['PHP_AUTH_USER']==$username && $_SERVER['PHP_AUTH_PW']==$password))) {
    header('WWW-Authenticate: Basic realm="This site is protected"');
    header('HTTP/1.0 401 Unauthorized');
    // Fallback message when the user presses cancel / escape
    echo 'Access denied';
    exit;
  }
}

/**
 * Environment specific overrides.
 */
if(isset($_ENV['AH_SITE_ENVIRONMENT'])) {
  $settings['mass_caching.schemes'] = ['https'];
  // Allow media entity download to work with files from production.
  $config['media_entity_download.settings']['external_file_storage'] = 1;
  // Set domains to clear when issuing relative path purge requests.
  // @see \Drupal\mass_caching\ManualPurger
  switch($_ENV['AH_SITE_ENVIRONMENT']) {
    case 'prod':
      // Disable once Stage File Proxy in off in Prod.
      $config['stage_file_proxy.settings']['origin'] = FALSE;
      $config['media_entity_download.settings']['external_file_storage'] = 0;
      // Override for Prod.
      $settings['mass_caching.hosts'] = ['edit.mass.gov', 'www.mass.gov'];
      $config['akamai.settings']['disabled'] = FALSE;
      $config['akamai.settings']['basepath'] = 'https://www.mass.gov';
      $config['akamai.settings']['domain']['staging'] = FALSE;
      $config['akamai.settings']['domain']['production'] = TRUE;
      break;
    case 'test':
      $config['akamai.settings']['disabled'] = FALSE;
      break;
    case 'dev':
      $settings['mass_caching.hosts'] = [
        'wwwcf.digital.mass.gov',
        'editcf.digital.mass.gov',
      ];
      break;
  }
}

/**
 * Improve traceability of New Relic transactions by adding URL and
 * unique request ID. Request ID also shows up in all of Acquia's logs.
 */
if(function_exists('newrelic_add_custom_parameter') && !$cli) {
  newrelic_add_custom_parameter('backend_url', $_SERVER['REQUEST_URI']);
  newrelic_add_custom_parameter('request_id', $_SERVER['HTTP_X_REQUEST_ID']);
}


