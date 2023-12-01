# Composer

## Overview

[Composer](https://getcomposer.org/) is a PHP dependency management tool. It allows us to specify the third party code (packages) we need in a `composer.json` file, and pull them all in with a single command.

Composer uses a "lock file", named `composer.lock`. Whereas `composer.json` can specify "loose" versions of the packages you need, `composer.lock` will always specify exact versions. This allows `composer install` to be very fast, and ensures that all developers on the project are using the same versions. As a rule of thumb, any time you change `composer.json`, you should run `composer update --lock` to regenerate the lock file so the two stay in sync.

\*\*Important: You should never just delete the lock file. If you hit a conflict, see the [conflict resolution](#Conflicts) section below.

## Installing a new package/module:

Follow these steps to update a package:

1. `composer require drupal/mymodule --update-with-dependencies`
2. `git add composer.*`
3. `git commit -m "Adding drupal/mymodule"`

## Updating an existing package/module:

1. `composer update drupal/mymodule --with-dependencies`
2. `git add composer.*`
3. `git commit -m "Updating drupal/mymodule to version X"`

Note: You may also need to update the version constraint for the package in `composer.json`

## Removing an existing package/module:

1. `composer remove drupal/mymodule`
2. `git add composer.*`
3. `git commit -m "Removing drupal/mymodule"`

Do not directly remove the package from `composer.json` - this will leave any dependencies of your package in `composer.lock`, and they will continue to be installed in the future, even though they are not needed.

## Conflicts

It's common to run into conflicts in the `composer.lock` file. This file cannot be merged by hand. You have to resolve any conflicts using composer. Let's imagine you have a feature branch `myfeature` that was taken off of `develop` a few weeks ago. You've added a new module on `myfeature`, and there's been a new module added to `develop`. When you merge `develop` back into `myfeature`, you will hit a conflict in the lock file. Here's how to resolve that conflict:

1. `git checkout --theirs composer.lock` <- Reset composer.lock to the develop version, throwing out your changes.
2. `composer require drupal/mymodule` <- Re-run the exact changes you made in adding your module on the clean lockfile.
3. `git add composer.*`
4. `git commit -m "Resolve merge conflict in composer.lock"`

You need to remember the Composer commands that you used to make your changes initially. Assuming you have those, it's just a matter of resetting the lock file and running them again to resolve the conflict.

## Development dependencies

Any packages required in the `require-dev` section of `composer.json` will not be included in the final build that is sent to Acquia. Be very careful not to reference development packages from code that will be run in production.

## Version Constraints

You should try to use loose version constraints, based on semantic versioning wherever possible. See [this article](https://blog.madewithlove.be/post/tilde-and-caret-constraints/) for information on what the tilde and carat do, and [this article](https://getcomposer.org/doc/articles/versions.md) for a full reference.

Most packages that are used will follow [Semantic Versioning](https://semver.org/). In general, you want to allow either MINOR or PATCH level changes to be pulled in without needing to change the version constraint in `composer.json`.

## Tips/Troubleshooting

- Symfony components have been added to `composer.json` as root requirements. This is only to [save memory during the update process](https://github.com/massgov/mass/pull/1784). It is against best practices to add dependencies of dependencies to your root `composer.json`, but the memory and time savings made it worth it for this project. To update these in the future, open Drupal core's `composer.json` and copy out the `symfony/` dependencies into the root `composer.json`
- Use loose version constraints wherever possible. This will allow the version to be updated in the future without changing `composer.json`.

- On occasion, `composer.json` and `composer.lock` files can get out of sync. In order to prevent this from happening, we enabled CircleCI to run `composer validate`. This validation process ensures that both `.json` and `.lock` are in sync and no `json` syntax errors exist. If you get an error that these two files are out of sync, run `composer update --lock` on your local branch and commit the output of this operation.
  
- If you have trouble running `ahoy comi` you can try deleting the vendor directory and rerunning `composer install` to get a fresh copy of the vendor directory:
      - `cd <path/to/mass>`
      - `rm -r vendor`
      - `composer install`
