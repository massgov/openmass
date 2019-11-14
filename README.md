# Mass.gov Drupal Site

_The official website of the Commonwealth of Massachusetts_

This is the codebase for the Drupal 8 www.mass.gov. The site's theme (mass_theme) is powered by Mayflower, a companion repo available at https://github.com/massgov/mayflower.

[Table of Contents](https://github.com/massgov/DS-Infrastructure/blob/develop/docs/massgov/README.md) for documentation related to this repository such as [Releases](https://github.com/massgov/DS-Infrastructure/blob/develop/docs/massgov/release.md). NOTE: documentation specific to the production site is not publicly available. 

## Getting Started

1. Clone the repo: `git clone git@github.com:massgov/openmass.git`

1. Move into the project directory: `cd openmass`

1. Create a `.env` file at the root level of the project by copying the example file shipped with the `mass` repo. This file contains more options; we suggest that you review it and adjust accordingly. Note that the `.env` file is ignored in `.gitignore`; and will not be tracked or pushed to Github.
    ```
    $ cp .env.example .env
    ```

### Docker

1. Install Docker for [Mac](https://docs.docker.com/docker-for-mac/install/) or [Windows](https://docs.docker.com/docker-for-windows/install/). If using Linux, skip this step.

1. Edit your `hosts` file and add the following line:
    ```
    127.0.0.1 mass.local portainer.mass.local mailhog.mass.local
    ```
    1. **Mac/Linux:** `/etc/hosts`
    1. **Windows:** `c:\windows\system32\drivers\etc\hosts`
    
### Ahoy

1. In order for the Ahoy aliases to work, install [Ahoy](https://github.com/ahoy-cli/ahoy):
    ```bash
    sudo wget -q https://github.com/devinci-code/ahoy/releases/download/2.0.0/ahoy-bin-darwin-amd64 -O /usr/local/bin/ahoy && sudo chown $USER /usr/local/bin/ahoy && chmod +x /usr/local/bin/ahoy
    ```
1. Run `ahoy start` to setup a fresh new site (n.b. takes about 1 hour to complete). This does the following: 
    - Starts the Docker containers, which includes a database.
    - Runs `composer install` to fetch all dependencies.
    - Installs a fresh site based on latest config contained in this repository.

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

[Blackfire](http://blackfire.io/) is available for performance profiling of CLI or web requests. See `.env.example` for information on how to enable it, and the [Performance Documentation](https://github.com/massgov/DS-Infrastructure/blob/develop/docs/massgov/Performance.md#running-blackfire) on how to use it.

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
