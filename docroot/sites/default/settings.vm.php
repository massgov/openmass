<?php

use Drupal\KernelTests\Core\Entity\EntityQueryTest;
use Drupal\node\Entity\Node;
use Drupal\mass_utility\DebugCachability;

$databases = array();

$databases['default']['default'] = array(
  'driver' => 'mysql',
  'database' => getenv('MYSQL_DATABASE') ?: 'drupal',
  'username' => getenv('MYSQL_USER') ?: 'root',
  // Allow for setting an empty password via environment.
  'password' => getenv('MYSQL_PASSWORD') === FALSE ? 'root' : getenv('MYSQL_PASSWORD'),
  'port' => getenv('MYSQL_PORT') ?: 3306,
  'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  // Extended timeout allows for slow Docker DB start times.
  'pdo' => [PDO::ATTR_TIMEOUT => 60],
);

$settings['hash_salt'] = 'temporary';
$settings['file_public_path'] = 'sites/default/files';
$settings['file_private_path'] = 'sites/default/files/private';

// Allow media entity download to work with files from production.
$config['media_entity_download.settings']['external_file_storage'] = 1;

// Disable autologout
$config['autologout.settings']['timeout'] = 9999999;
$config['autologout.settings']['max_timeout'] = 9999999;
// Routes mail to PHP's sendmail_path which then routes to Mailhog.
if (!getenv('MASS_MAILCHIMP')) {
  $config['mailsystem.settings']['defaults']['sender'] = 'php_mail';
  $config['mailsystem.settings']['defaults']['formatter'] = 'php_mail';
}
// Development geocoder overrides:
// Use the dummy "random" geocoder plugin in in development environments.
// This avoids overwhelming our production credentials for things like tests.
// Our production account is wired to [REDACTED]@gmail.com.
$config['field.field.paragraph.address.field_geofield']['third_party_settings']['geocoder_field']['plugins'] = ['random'];


if (getenv('DOCKER_ENV')) {
  // Docker service configuration.
  $settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.docker.yml';
}
if (getenv('DOCKER_ENV') === 'devel') {
  $settings['container_yamls'][] = $app_root . '/sites/development.services.yml';
}

// Manually require this file so we can share the constant. At this point,
// Drupal is not bootstrapped enough to know what modules are enabled, so
// technically we can only autoload classes in /vendor/.
require_once __DIR__ . '../../../../docroot/modules/custom/mass_utility/src/DebugCachability.php';
if (isset($GLOBALS['request']) && $GLOBALS['request']->headers->get(DebugCachability::HEADER, FALSE)) {
  $settings['container_yamls'][] = $app_root . '/sites/debug_cacheability_headers.services.yml';
}

/**
 * Show all error messages with backtrace information, except during Behat/Browserkit runs.
 * Those would fail dynamic page cache tests (at minimum).
 */
if (getenv('HTTP_USER_AGENT') !== 'Symfony BrowserKit') {
  $config['system.logging']['error_level'] = 'verbose';
}

// Make the Static Google Map API key available to CircleCI.
$settings['static_google_map'] = getenv('STATIC_GOOGLE_MAP');

/**
 * Skip file system permissions hardening.
 *
 * The system module will periodically check the permissions of your site's
 * site directory to ensure that it is not writable by the website user. For
 * sites that are managed with a version control system, this can cause problems
 * when files in that directory such as settings.php are updated, because the
 * user pulling in the changes won't have permissions to modify files in the
 * directory.
 */
$settings['skip_permissions_hardening'] = TRUE;

/**
 * Assertions.
 *
 * The Drupal project primarily uses runtime assertions to enforce the
 * expectations of the API by failing when incorrect calls are made by code
 * under development.
 *
 * @see http://php.net/assert
 * @see https://www.drupal.org/node/2492225
 *
 * If you are using PHP 7.0 it is strongly recommended that you set
 * zend.assertions=1 in the PHP.ini file (It cannot be changed from .htaccess
 * or runtime) on development machines and to 0 in production.
 *
 * @see https://wiki.php.net/rfc/expectations
 */
// See https://www.drupal.org/node/3391611
ini_set('zend.assertions', 1);

// If Memcache needs to be disabled, comment out this block.
if($memcached_host = getenv('MEMCACHED_HOST')) {
  $memcached_port = getenv('MEMCACHED_PORT') ?: 11211;
  $settings['memcache']['servers'] = ["{$memcached_host}:{$memcached_port}" => 'default'];
}

/**
 * Loads secrets, if available.
 *
 * Required for Mass Feedback Loop (mass_feedback_loop).
 * Expected array structure for mass_feedback_loop:
 *   $settings['mass_feedback_loop']
 *   `-- ['external_api_config']
 *       |-- ['api_base_url']
 *       `-- ['authenticate_header']
 */
$secrets_file = $app_root . '/' . $site_path . '/secrets.settings.php';
if (file_exists($secrets_file)) {
  require $secrets_file;
}

if (getenv('TUGBOAT_ROOT')) {
  require __DIR__ . '/settings.tugboat.php';
}
