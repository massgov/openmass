# Mayflower

[Mayflower](https://github.com/massgov/mayflower) is an opensource design system for the state government of Massachussets. It contains a [PatternLab (Twig)](http://patternlab.io/) component library that's used as a frontend dependency for Mass.gov (Openmass).

## Mayflower and Mass.gov's Drupal theme

Openmass has a custom Drupal theme called [mass_theme](../docroot/themes/custom/mass_theme), which uses Mayflower build artifacts as a project dependency for static assets (css, js, and image) as well as twig templates.

This relationship is managed alongside other project dependencies through composer. Learn more about this relationship in the [Mayflower Artifacts](#mayflower-artifacts) section below.

Mass.gov also uses a custom Drupal module, called [mayflower](../docroot/modules/custom/mayflower), which acts as "glue code" to get `mass_theme` working with Mayflower. Learn more in the [mass_theme](../docroot/themes/custom/mass_theme) and [mayflower module](../docroot/modules/custom/mayflower).

### A visual flow from Mayflower to Drupal

[![Mayflower + Drupal theme](assets/mayflower_drupal.png)](https://docs.google.com/presentation/d/1qWY-QoXu8JgazqnwNUoPyumu_XH-DgFj_iNoFiKu1YA/edit#slide=id.p)

## Mayflower Artifacts

[Mayflower Artifacts](https://github.com/massgov/mayflower-artifacts) is a repository containing versioned build artifacts of Mayflower Patternlab. All branches and tags from Mayflower are deployed automatically via CircleCI to Mayflower Artifacts.

Openmass can point to a specific version of Mayflower Artifacts built from a Mayflower branch (e.g. `patternlab/DP-1234`) by pointing to a branch alias prefixed by `dev-` (e.g. `dev-patternlab/DP-1234`) using composer. 

By default, The Openmass `develop` branch and `master` branch always point to the `develop` branch of Mayflower. 
```
// In composer.json
"massgov/mayflower-artifacts": "dev-develop"
```
If a new change has been merged into the `develop` branch of Mayflower, running `composer require massgov/mayflower-artifacts:dev-develop --update-with-dependencies` in openmass will bring in the latest change from Mayflower and update the composer.lock in openmass to "lock" to the specifc version.


### Feature testing Mayflower changes in Drupal

If you're working on a ticket that requires updates in Mayflower that have not yet been released, you can _temporarily_ pull in a development branch of mayflower-artifacts to Drupal for testing.

#### Using a branch of mayflower-artifacts in your Drupal branch:

1. From your terminal, within Docker, update and download your new mayflower version by running `composer require massgov/mayflower-artifacts:dev-<your-branch-name> --update-with-dependencies` -- so in this example: `composer require massgov/mayflower-artifacts:dev-DP-8411-test-branch --update-with-dependencies`
1. Commit only the files and file hunks which correspond to updating mayflower-artifacts
1. You should now have Mayflower updated in your feature branch. Remember to rebuild your cache!

#### Use a branch of mayflower-artifacts to...

- Facilitate local development on a feature or fix that integrates Mayflower and Drupal code updates
- Enable internal and/or external reviews of a branch _before_ it is merged into develop

#### Do not use a branch of mayflower-artifacts to...

- Update the version of Mayflower used in Mass.gov production!

#### Local Development Workflow

If you're working on a ticket that requires updates in Mayflower that you want to preview locally before committing, you can also build Mayflower artifacts locally.

1. Clone [massgov/mayflower](https://github.com/massgov/mayflower) and follow the [setup instructions](https://github.com/massgov/mayflower#getting-started-on-development).
2. Inside your local Mayflower installation, copy `packages/patternlab/styleguide/.env.example` to `packages/patternlab/styleguide/.env`, and set the `MAYFLOWER_DIST` environment variable so it points at `libraries/mayflower-dev` in your Drupal root (eg: `MAYFLOWER_DIST=~/Sites/openmass/docroot/libraries/mayflower-dev`).
3. Build the artifacts from Mayflower Patternlab by running `rush build:patternlab`.
4. Check to see if the mayflower artifacts generated from the previous step exists in the your local openmass repo at openmass/docroot/libraries/mayflower-dev
5. Run `ahoy drush cr` on the Drupal site to have the development artifacts picked up.

Note that you will want to remove the `mayflower-dev` folder when you are finished development, since it will override whatever version of Mayflower is otherwise specified.
