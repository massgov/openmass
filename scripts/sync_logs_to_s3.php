<?php

define('AWSCLI', '/home/massgov/.local/bin/aws');
define('S3_PATH', 's3://logs.acquia/');
// See ~/.aws on Prod or feature1.
define('S3_PROFILE', 'acquia-files-sync');
$site = getenv('AH_SITE_GROUP');
$env = getenv('AH_SITE_ENVIRONMENT');
$parts = explode('.', gethostname());
$servername = $parts[0];
define('SOURCE_DIR', "/var/log/sites/$site.$env/logs/$servername/");

sync();

function sync() {
  // Several log types commented out because they are not needed.
  $includes = [
    'access.log', // Apache
    // 'error.log', // Apache
    // 'drupal-requests.log',
    'drupal-watchdog.log',
    // 'php-errors.log',
  ];
  $suffix = implode(' --include ', $includes);
  $cmd = [AWSCLI, '--profile', S3_PROFILE, 's3', 'sync', SOURCE_DIR, S3_PATH, ' --only-show-errors', '--no-progress', '--exclude', '"*"', '--include '];
  $str = implode(' ', $cmd) . ' ' . $suffix;
  fwrite(STDERR , $str . "\n");
  exec($str, $output, $result_code);
  fwrite(STDERR, implode("\n", $output));
  exit($result_code);
}
