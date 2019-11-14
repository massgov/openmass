# Modules

### Enabling development-only modules (e.g. Devel)

You are welcome to enable development-only modules (e.g. Devel, Kint, Views UI, Migrate UI, ...) in your VM. In your Pull Requests,
remember not to commit a core.extension.yml which enables these projects, and remember not to add the config files that
belong to these modules. @todo We can help developers avoid this error by adding common development config files to
our `.gitignore`. We could also add `core.extension.yml` and ask developers to use `git add --force` when they really want to change that
file.

### Adding a new module to composer

1. Add the module as a project dependency with composer _(without downloading it, or updating any other package)_: ex. `composer require --no-update drupal/bad_judgement:^2.0` _(If you checked `git status` you should see that only `composer.json` was updated)_
2. Download module files with composer: ex. `composer update --with-dependencies drupal/bad_judgement` _(If you checked `git status` you should see that `composer.lock` was now also changed, but only to reflect the module that you just installed or updated, and all of the module files have been added, likely to `docroot/modules/contrib/your_module`.)_
3. Enable the module: `drush en bad_judgement`
4. Export the config with the module enabled: `drush config-export`
5. Sometimes composer will pull in a git directory instead of distribution files for a dependency. Please double check that there is no `.git` directory within the module code, and if so, please remove it. You can test with this command: `find . | grep ".git$"` and should ONLY see the output of: `./.git` (which is this projects git directory - don't delete that!).
6. Commit the changes to `composer.json`, `composer.lock`, `conf/drupal/config/core.extension.yml`, but not the module code itself.

### Patching a module

Sometimes we need to apply patches from the Drupal.org issue queues. These patches should be applied using composer using the [Composer Patches](https://github.com/cweagans/composer-patches) composer plugin. Note that both composer operations: `update` and `install` will halt in the event that a patch failed to install due the setting "composer-exit-on-patch-failure" in `composer.json`Commit the changes to `composer.json`, `composer.lock`, `conf/drupal/config/core.extension.yml`, but not the module code itself.

### Updating a dependency

Follow these steps in order to keep Mass.gov up to date with desired dependency updates.

1. When there is a new dependency update available, it's a good idea to read the release notes for that update to get an idea of what has changed. Also, make sure you are familiar with the implications of each kind of release (i.e. major, minor, patch) for that package.
1. The exact composer workflow will depend on the whether or not the new version falls within the [version constraints](https://getcomposer.org/doc/01-basic-usage.md#package-version-constraints) used when requiring the package. These constraints can be found in the `composer.json` entry for the given package.
   1. If the new version of your package is included in the version constraint in `composer.json`, then you can update your package by running `composer update <package owner>/<package> -- with dependencies`. For example, `composer update drupal/bad_judgement --with-dependencies`. _(If the update was successful, then `git status` should show changes to `composer.lock` to reflect the new package version.)_
   1. If the new version of your package is _not_ included in the version constraint in `composer.json`, then you need to first update the version constraint and then update the package by running `composer require <package owner>/<package name>:<version constraint> --update-with-dependencies`. For example, `composer require drupal/bad_judgement:^3.0 --update-with-dependencies`. _(If the update was successful, then `git status` should show changes to both `composer.json` and `composer.lock` to reflect the new package version constraint and installed version, respectively.)_
1. Test Mass.gov with the new release (remember to `drush cr`!):
   1. Smoke test the [Mass.gov featureset](releases/smoke_testing.md) to ensure existing functionality has not been broken - especially the functionality that the dependency adds.
   1. Functional test: Does this release introduce new functionality? Verify that this functionality works in Mass.gov. If any issues are identified during testing steps, make the necessary code fixes in Mass.gov to accommodate.
