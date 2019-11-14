@api @security

Feature: Security
As an administrator, I want to be sure that the platform does not
expose compromising data.
Scenario: Check for access to compromising files
  # Handled by FilesMatch section at the top of .htaccess.
  When I am on "/core/includes/database.inc"
  Then I should get a 403 HTTP response
  When I am on "/core/scripts/cron-curl.sh"
  Then I should get a 403 HTTP response
  When I am on "/core/modules/system/system.module"
  Then I should get a 403 HTTP response
  When I am on "/.git/config"
  Then I should get a 403 HTTP response
  When I am on "/core/core.services.yml"
  Then I should get a 403 HTTP response
  When I am on "/core/yarn.lock"
  Then I should get a 403 HTTP response
  When I am on "/core/composer.json"
  Then I should get a 403 HTTP response
  # Access files that may give more info than we want.
  # Including all files in core.
  When I am on "/core/INSTALL.mysql.txt"
  Then I should get a 404 HTTP response
  When I am on "/core/phpcs.xml.dist"
  Then I should get a 404 HTTP response
  When I am on "/CHANGELOG.txt"
  Then I should get a 404 HTTP response
  When I am on "/modules/custom/mayflower/README.md"
  Then I should get a 404 HTTP response
  When I am on "web.config"
  Then I should get a 404 HTTP response
  When I am on "/core/install.php"
  Then I should get a 404 HTTP response
  When I am on "/core/rebuild.php"
  Then I should get a 404 HTTP response
  When I am on "/core/modules/statistics/statistics.php"
  # Access PHP files directly, handled by php deny block.
  Then I should get a 403 HTTP response
  When I am on "/autoload.php"
  Then I should get a 403 HTTP response
  # Check that access to hq2 is ok.
  When I am on "/hq2/index.php"
  Then I should get a 200 HTTP response

  # Check that /filter/tips _is_ accessible.
  # Note: Blocking this URL causes Acquia uptime checks to fail.
  When I am on "/filter/tips"
  Then I should get a 200 HTTP response
