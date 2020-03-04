# Mass.gov Drupal Site

_The official website of the Commonwealth of Massachusetts_

This is the codebase for the Drupal 8 web site `www.mass.gov`. The site's theme, [mass_theme](https://github.com/massgov/openmass/blob/develop/docroot/themes/custom/mass_theme/README.md), is powered by Mayflower, a companion repo available at https://github.com/massgov/mayflower.

See the [Table of Contents](/docs/README.md) for additional documentation related to this repository.

## Getting Started

1. Clone the repo: `git clone git@github.com:massgov/openmass.git`

1. Move into the project directory: `cd openmass`

1. Create a `.env` file at the root level of the project by copying the example file shipped with the `mass` repo. This file contains more options; we suggest that you review it and adjust accordingly. Note that the `.env` file is ignored in `.gitignore`; and will not be tracked or pushed to Github.
   ```
   $ cp .env.example .env
   ```

### Docker (optional)

1. Install Docker for [Mac](https://docs.docker.com/docker-for-mac/install/) or [Windows](https://docs.docker.com/docker-for-windows/install/). If using Linux, skip this step.

1. Edit your `hosts` file and add the following line:

   ```
   127.0.0.1 mass.local portainer.mass.local mailhog.mass.local
   ```

   1. **Mac/Linux:** `/etc/hosts`
   1. **Windows:** `c:\windows\system32\drivers\etc\hosts`

### Ahoy (optional)

1. In order for the Ahoy aliases to work, install [Ahoy](https://github.com/ahoy-cli/ahoy):
   ```bash
   sudo wget -q https://github.com/devinci-code/ahoy/releases/download/2.0.0/ahoy-bin-darwin-amd64 -O /usr/local/bin/ahoy && sudo chown $USER /usr/local/bin/ahoy && chmod +x /usr/local/bin/ahoy
   ```
1. Run `ahoy up` to start the Docker containers (n.b. takes about 30 minutes to pull down the latest database).
1. Run `ahoy comi` to fetch all dependencies.

###### Notes

- It takes a few minutes for the `mysql` container start up.
- Mass Digital team members have additional information at [Mass Digital development](https://github.com/massgov/DS-Infrastructure/blob/develop/docs/massgov/development-massgov-team.md) instead.

## Pull Requests

Anyone is welcome and encouraged to submit a pull request for this project. Members of the public should fork the project and submit a PR. Your PR will automatically build and get limited testing. Once that is green, a mass.gov team member will code review your PR. Once satisfied, the team member will [copy your branch into the openmass repo](scripts/git-push-fork-to-upstream-branch) so the full test suite may run. Once that is green, your PR is is eligible to be merged.

## Workflow

This is a suggestion for how you can transition between branches when working on tickets. From outside the Docker environment, start by checking out the branch you will be working on:

| docker compose                                                     | ahoy          |
| ------------------------------------------------------------------ | ------------- |
| git checkout DP-8111-cool-branch-work                              | n/a           |
| docker-compose down && docker-compose pull && docker-compose up -d | ahoy pull     |
| docker-compose exec drupal composer install                        | ahoy comi     |
| docker-compose exec drupal yarn                                    | ahoy yarn     |
| docker-compose exec drupal scripts/ma-refresh-local --skip-db-prep | ahoy updatedb |

### Blackfire

[Blackfire](http://blackfire.io/) is available for performance profiling of CLI or web requests. See `.env.example` for information on how to enable it, and the [Performance](https://github.com/massgov/openmass/blob/develop/docs/performance.md#blackfire) documentation on how to use it.

### Email

To access MailHog using the browser, go to http://mailhog.mass.local.

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

### SSH Keys

If you are using a dedicated SSH Key pair and you have named it other than `~/.ssh/id_rsa`, specify the private key name and path in the `.env` file located in the root of the project.

### hosts file

View `/etc/hosts` for Mac/Linux or `c:\windows\system32\drivers\etc\hosts` in Windows; and verify that there is only one entry for `mass.local` and it displays as follows:

```
127.0.0.1 mass.local portainer.mass.local mailhog.mass.local
```

### `SQLSTATE[HY000][2002]` Connection refused

This usually happens if you go visit mass.local right after the containers are brought up. MySQL has not started yet. Open `portainer.mass.local`; and go to _Containers > mass_mysql_1 > Logs_ and check for the message: _mysqld: ready for connections._ If you don't see this message, _mysqld_ has not started yet.

### Windows troubleshooting

- All host machine command line steps should be done from an elevated (admin) prompt.
- Make sure that you have run `git config --local core.symlinks true` to enable symlinks when
  you check out the repository.
- If the symlinks from the theme to the Pattern Lab assets are not working after running composer,
  delete the non-working symlinks and `git checkout` again.
- You will find it helpful to copy `docroot/.gitattributes` to the root of the project. [@todo - add this to the automation]
