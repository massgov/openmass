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

### For Mass.gov using Xdebug

Initial setup:
1. [Setup Xdebug](https://github.com/massgov/openmass#xdebug) for your IDE.
2. Download and enable the [Xdebug helper](https://www.jetbrains.com/help/phpstorm/browser-debugging-extensions.html) browser extension.

Starting a debugging session:
1. Enable the Devel module by running `ddev drush en devel`.
2. Clear the cache by running `ddev drush cr`.
3. Enable Xdebug listening in your IDE.
4. Enable `Debug` in the Xdebug helper browser extension for the page you are debugging.
5. Add `{{ devel_breakpoint() }}` to the Twig template you are debugging where you want to see variable values.
   1. This snippet can be added in multiple places and stepped through.
6. Load the page to hit the breakpoint.
   1. You may need to clear the cache as you make changes to the template file.

**Note: Don't commit changes to the `core.extension` file enabling the `devel` module, or `devel` related config files.**
