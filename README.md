# Mass.gov Drupal Website

_The official website of the Commonwealth of Massachusetts_

This is the codebase for the Drupal 10 web site `www.mass.gov`. The site's theme, [mass_theme](https://github.com/massgov/openmass/blob/develop/docroot/themes/custom/mass_theme/README.md), is powered by Mayflower, a companion repo available at https://github.com/massgov/mayflower.

See the [Table of Contents](/docs/README.md) for additional documentation related to this repository.

## Getting Started

1. Clone the repo: `git clone git@github.com:massgov/openmass.git`

1. Move into the project directory: `cd openmass`

1. Create a `.env` file in the ~/.ddev dir of the project by copying the example file shipped with the `openmass` repo. This file contains more options; we suggest that you review it and adjust accordingly. Note that the `.env` file is ignored in `.gitignore`; and will not be tracked or pushed to Github.
    ```
    $ cp .ddev/.env.example .ddev/.env
    ```

### DDEV and Docker

1. [Install DDEV](https://ddev.readthedocs.io/en/stable/). On Windows, use the WSL2 method. Most of us use Colima as the Docker provider.
1. Inject your ssh keys into the container via `ddev auth ssh`. [Read more about ddev CLI](https://ddev.readthedocs.io/en/stable/users/cli-usage/).
1. Start DDEV and install packages.
    ```
    ddev start
    ddev composer install
    ddev yarn
    ```
1. To get information about this project.
    ```
    ddev describe
    ```

###### Notes
- The site is browseable at the URL reported by `ddev describe`. Thats usually https://mass.local
- It takes a a minute for the `dbmass` container start up.
- [You may override ddev config locally](https://ddev.readthedocs.io/en/stable/users/extend/config_yaml/). create a `.ddev/config.local.yml` file and add whatever you need.
- Similarly, rename [.ddev/.env.example](https://github.com/massgov/openmass/blob/develop/.ddev/.env.example) to `.env` in order to use ARM containers suitable for the Apple M1 Macs. This is also how you specify the less sanitized variant of our database.
- Since we use a custom `dbmass` service and not DDEV's usual `db`, some DDEV DB commands will not work here. @todo try to improve this.
- Use `ddev service enable backstop` to start the backstop image locally. Same for `selenium-chrome`.
- Mass Digital team members: see additional information at [Mass Digital Developers](https://github.com/massgov/massgov-internal-docs/blob/master/development-massgov-team.md).

## Pull Requests
Anyone is welcome and encouraged to submit a pull request for this project. Members of the public should fork the project and submit a PR. Your PR will automatically build and get limited testing. Once that is green, a mass.gov team member will code review your PR. Once satisfied, the team member will [copy your branch into the openmass repo](scripts/git-push-fork-to-upstream-branch) so the full test suite may run. Once that is green, your PR is is eligible to be merged.


## Workflow

This is a suggestion for how you can transition between branches when working on tickets. From the host, run:

```
git checkout DP-8111-cool-branch-work
ddev pulldb
ddev composer install
ddev yarn install
ddev updatedb
```

### Blackfire

[Blackfire](http://blackfire.io/) is available for performance profiling of CLI or web requests. See https://ddev.readthedocs.io/en/stable/users/blackfire-profiling/ and https://blackfire.io/docs/integrations/paas/ddev for information on how to enable it, and the [Performance](https://github.com/massgov/openmass/blob/develop/docs/performance.md#blackfire) documentation on how to use it.

### Email

To access MailHog using the browser, use the URL shown in `ddev describe`.

### Portainer

To access Portainer using the browser, use the URL shown in `ddev describe`.

### Memcache

Our site uses Memcache for some of the cache buckets. Memcache runs as a separate server in Docker (called `memcached`). See `settings.vm.php` and `settings.acquia.php` for which buckets are being sent to Memcache.

For debugging memcache, we use a set of Drush commands:

```bash
# Show information about hit/miss ratios and memory usage.
drush memcache:health
# Show full statistics.
drush memcache:stats
# List keys stored in memcache
drush memcache:keys
# List key sizes:
drush memcache:sizes
```
## Troubleshooting

### Disk Space

If you run out of disk space, try running `docker system prune` to remove unused Docker images, containers and volumes.

### Debug

If you know where a problem is happening in your code in Twig or PHP, refer to [these steps](./docs/drupal_debug.md).
