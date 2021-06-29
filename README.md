# Mass.gov Drupal Site

_The official website of the Commonwealth of Massachusetts_

This is the codebase for the Drupal 8 web site `www.mass.gov`. The site's theme, [mass_theme](https://github.com/massgov/openmass/blob/develop/docroot/themes/custom/mass_theme/README.md), is powered by Mayflower, a companion repo available at https://github.com/massgov/mayflower.

See the [Table of Contents](/docs/README.md) for additional documentation related to this repository.

## Getting Started - Mac

1. Clone the repo: `git clone git@github.com:massgov/openmass.git`

1. Move into the project directory: `cd openmass`
1. Create a `.env` file at the root level of the project by copying the example file shipped with the `openmass` repo. This file contains more options; we suggest that you review it and adjust accordingly. Note that the `.env` file is ignored in `.gitignore`; and will not be tracked or pushed to Github.
    ```
    $ cp .env.example .env
    ```
1. If using Docker (n.b. see Native alternative below), install [Docker Desktop](https://docs.docker.com/docker-for-mac/install/).
1. Edit your `/etc/hosts` file and add the following line:
    ```
    127.0.0.1 mass.local portainer.mass.local mailhog.mass.local
    ```
1. In order for the Ahoy aliases to work, install [Ahoy](https://github.com/ahoy-cli/ahoy):
   ```bash
    sudo wget -q https://github.com/devinci-code/ahoy/releases/download/2.0.0/ahoy-bin-darwin-amd64 -O /usr/local/bin/ahoy && sudo chown $USER /usr/local/bin/ahoy && chmod +x /usr/local/bin/ahoy
    ```
1. Run `docker login`   
1. Run `ahoy up` to start the Docker containers (n.b. takes about 30 minutes to pull down the latest database).
1. Run `ahoy comi` to fetch all dependencies.   

## Getting Started - Windows Subsystem for Linux (aka WSL2)
The recommended way to run Docker on Windows is via WSL2.

1. In Notepad, edit `c:\windows\system32\drivers\etc\hosts` file and add the following line:
    ```
    127.0.0.1 mass.local portainer.mass.local mailhog.mass.local
    ```
1. Install WSL2. Until WSL2 is released and then supported by IT, you need to follow the Manual install at https://docs.microsoft.com/en-us/windows/wsl/install-win10. Install _Ubuntu_ in the last step. Also install Windows Terminal as suggested.
1. Install [Docker Desktop](https://docs.docker.com/docker-for-windows/install/) if you havent already.
1. Go to Docker Desktop settings > Resources > WSL integration > enable integration for your distro (now Docker commands will be available from within your WSL2 distro).
    1. Double-check in PowerShell: `wsl -l -v` should show three distros, and your Ubuntu should be the default )has asterisk). All three should be WSL version 2.
    1. Double check in Ubuntu CLI (i.e. open Windows Terminal with Ubuntu shell instead of Powershell) that Docker is working inside Ubuntu: `docker-compose ps`.
1. In Ubuntu CLI: 
   ```bash
   git clone https://github.com/massgov/openmass.git
   cd openmass
   docker login
   # Install Ahoy
   sudo wget -q https://github.com/devinci-code/ahoy/releases/download/2.0.0/ahoy-bin-darwin-amd64 -O /usr/local/bin/ahoy && sudo chown $USER /usr/local/bin/ahoy && chmod +x /usr/local/bin/ahoy
   # Install PHP dependencies
   ahoy comi
   # Start the openmass containers
   ahoy up
   ```
1. Verify that your development site responds by opening any Windows browser and navigating to http://mass.local. It is expected that the CSS is broken. We'll fix that soon.
1. Install [Visual Studio Code from](https://code.visualstudio.com/) Microsoft. This will be your code editor. Also install the [Remote - Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension.
1. In VS Code, run the command _Attach to Running Container_ and pick the `drupal` container.   
1. You should see a green `Connected massgoc/drupalcontainer` in lower left. This means that Remote Container extension is connected and working. You may now browse the codebase, and make changes. The codebase you are editing canonically lives in the Ubuntu filesystem (e.g. /home/), not in the Windows filesystem (/mnt/c), because you'll get vastly superior performance on the Ubuntu filesystem.
1. Create a `.env` file at the root level of the project by copying the example file shipped with the `openmass` repo. Review and edit your .env file. Restart Docker for changes to take effect. Note that the `.env` file is ignored in `.gitignore` and will not be tracked or pushed to Github.
    ```
    $ cp .env.example .env
    ```
1. In VS Code *Terminal*, run each of the lines below. This fixes the broken CSS we noted earlier.
   ```
   mkdir -p docroot/sites/default/files docroot/sites/simpletest/browser_output
   chown -R www-data:www-data docroot/sites/default/files docroot/sites/simpletest/browser_output
   ```
1. Misc Tips
   1. Try to use Linux programs. For example, use [gh](https://cli.github.com/), [lazygit](https://github.com/jesseduffield/lazygit) or VS Code for Git operations instead of Tower or GitKraken. WSL GUI applications are [in Preview now, and will be in the Windows 11](https://docs.microsoft.com/en-us/windows/wsl/tutorials/gui-apps).
   1. For CLI work, you can use the Terminal inside VS Code. That drops you right into the `drupal` container. Or you can use a shell on Ubuntu. Ahoy commands (see below) will only work in the Ubuntu shell.
   1. Install these VS Code extensions into the Remote as needed: PHP Intelephense, PHP Debug, SQLTools (and MySQL plugin), `Open in Github, BitBucket, Gitlab`.   

### Native (optional)
If the Docker section above is unappealing, its easy to run mass.gov natively on any OS. 

1. You need to provide your own PHP, web server and DB server (and optional memcache). On OSX, [these install instructions](https://getgrav.org/blog/macos-bigsur-apache-multiple-php-versions) are good (stop at the section called _PHP Switcher Script_), along with this [mysql section](https://getgrav.org/blog/macos-bigsur-apache-mysql-vhost-apc). Point your web server at the /docroot directory.
1. The Ahoy aliases also work for native development environments. Set an environment variable: `MASS_DEV_ENV=native`

###### Notes
- It takes a few minutes for the `mysql` container start up.
- Mass Digital team members: see additional information at [Mass Digital Developers](https://github.com/massgov/massgov-internal-docs/blob/master/development-massgov-team.md).

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

### Xdebug

If you know where a problem is happening in your code, Xdebug is a useful tool that allows you set breakpoints to trace the problem back. See [.env.example](../.env.example) for setup instructions.
