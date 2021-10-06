# Drupal Twig Debugging

[Drupal.org: Debugging Twig templates](https://www.drupal.org/docs/theming-drupal/twig-in-drupal/debugging-twig-templates)

## Locating Template files

[Drupal.org: Locating Template Files with Debugging](https://www.drupal.org/node/2358785)

### For Mass.gov

1. Copy the `docroot/sites/default/example.services.local.yml` file to `docroot/sites/default/services.local.yml`.
2. Uncomment the Twig debugging lines in the file `docroot/sites/default/services.local.yml`.
3. Clear the cache by running `ddev drush cr`.

## Viewing variables

[Drupal.org: Viewing variables](https://www.drupal.org/docs/theming-drupal/twig-in-drupal/debugging-twig-templates#s-viewing-variables)

### For Mass.gov using Xdebug and PhpStorm

Initial setup:
1. Enable xdebug in ddev by running `ddev xdebug on`.  See [PHP Step Debugging](https://ddev.readthedocs.io/en/stable/users/step-debugging/) for ddev specific setup instructions and how to configure your IDE
2. Under PHP > Debug > Templates > Twig Debug, set the Cache path (e.g. /Users/username/Projects/openmass/docroot/sites/default/files/php/twig).
3. Download and enable the [Xdebug helper](https://www.jetbrains.com/help/phpstorm/browser-debugging-extensions.html) browser extension.

Starting a debugging session:
1. Enable the Devel module by running `ddev drush en devel`.
2. Clear the cache by running `ddev drush cr`.
3. Enable Xdebug listening.
4. Enable `Debug` in the Xdebug helper browser extension for the page you are debugging.
5. Set a breakpoint.
6. Load the page to hit the breakpoint.
   1. You may need to clear the cache as you make changes to the template file.

**Note: Don't commit changes to the `core.extension` file enabling the `devel` module, or `devel` related config files.**
