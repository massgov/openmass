# Cloudflare Configuration

This project manages its Cloudflare configuration here. Cloudflare is set up as the CDN, and is the first line of defense for traffic going to Mass.gov. In Cloudflare, we depend on Page Rules and Workers to route traffic to the right place with the right settings.

## Page Rules

Page rules are sets of configuration that will be applied to certain requests. If a page rule's path matches the request's path, the configuration for that page rule will be applied. Only one page rule can be active for a given request (they do not stack).

## Worker

The Worker, or Service Worker, is javascript that lives at the edge and is invoked for every request. In our configuration, we use workers to handle:

* Routing legacy requests to the legacy backend.
* Adding a "CDN-Token" header to incoming requests, which will be verified at the origin to prevent direct origin access.
* Stripping of cookies on www.mass.gov
* Overriding of browser and edge cache TTLs based on the request.


## Terraform

We use [Terraform](https://www.terraform.io/) to deploy changes to Cloudflare. Terraform is an Infrastructure as Code tool that allows us to keep infrastructure configuration alongside the application code. In this repository, the Terraform code is split into two parts:

* [Environment configuration](terraform/environment), which dictates the page rules and workers that are used for a particular environment.
* [Global configuration](terraform/global), which dictates the global settings for the mass.gov zone.

For the environment configuration, we also leverage a Terraform feature known as "Workspaces."  Workspaces allow us to use the exact same Terraform code to deploy to multiple environments. Each workspace has its own state and variables, and can be deployed to independently. We have the following workspaces:

* `cf` - Temporary, for testing on the wwwcf.digital.mass.gov and editcf.digital.mass.gov domains.
* `stage` - Staging workspace.
* `prod` - Prod workspace.

## Workflow
When making changes to Cloudflare configuration in Terraform, we follow a standard dev →  stage →  prod workflow. Work gets reviewed on dev and only gets pushed to stage when it’s been reviewed and merged.

Cloudflare changes affect 2 different domains -- edit.mass.gov, and www.mass.gov, so we want to be able to test both during development/review.

Stage testing happens on our existing Stage environment (accessible at the preexisting `edit.stage.mass.gov` for changes to edit, and the new `stage.mass.gov` for changes to www).

Dev testing happens at `editcf.digital.mass.gov` and `wwwcf.digital.mass.gov`, which both map to the development environment at Acquia. Depending on which URL you use, you'll get different Cloudflare configuration, so you can test both the "edit" and the "www" experience. The development environment is still accessible at its Acquia URL, too, if you want to skip Cloudflare (https://massgovdev.prod.acquia-sites.com/).

Cloudflare changes don’t touch the Drupal code or database, so whatever happens to be on those environments is fine for CF testing purposes. And Cloudflare changes are unlikely to affect other work happening on those environments. In other words, the environments can generally be used for Drupal and CF testing purposes independently of each other.

## Development

The service worker code lives in the [`src/`](src) directory. When you change it, you should run `npm run-script build` to update the compiled code. There is also a test suite that can be run using `npm test`. This test suite is run automatically by CircleCI on every code push.

## Deploying changes

Before you can deploy any changes, you will need:

* An AWS account that is allowed to access the Terraform state and stored Cloudflare credentials (contact the cloud team for this access).
* [Terraform](https://www.terraform.io/downloads.html) installed locally. See [scripts/cloudflare-deploy](../scripts/cloudflare-deploy) for the correct version to use. [tfenv](https://github.com/tfutils/tfenv) helps with installing older versions.
* [AWS Vault](https://github.com/99designs/aws-vault) installed and [configured with MFA](https://github.com/massgov/DS-Infrastructure/blob/develop/docs/access.md#programmatic-cli-access).
* [Chamber](https://github.com/segmentio/chamber) installed locally.
* Python 2 must be the first `python` on your PATH. If needed, prefix your deployment with `PATH=/path/to/python2:$PATH`

Once you have these things, you will use the [`cloudflare-deploy` shell script](../scripts/cloudflare-deploy):

```bash
aws-vault exec massgov -- scripts/cloudflare-deploy TARGET
```

Replace `TARGET` with the name of the target you want to deploy to, which will be one of:
* `cf` - The wwwcf.digital.mass.gov and editcf.digital.mass.gov domains
* `stage` - The stage.mass.gov and edit.stage.mass.gov domains.
* `prod` - The www.mass.gov and edit.mass.gov domains.
* `global` - Global configuration that affects all domains.

## Smoke testing
* There is good testing in place for the workers; manual testing isn't needed there typically.
* Test that legacy redirects are working: check https://wwwcf.digital.mass.gov/anf and make sure it sends you to the correct Org page (https://www.mass.gov/orgs/executive-office-for-administration-and-finance).
* Open any page on the test URL. Reload it. Then open the dev tools Network tab and confirm the CF Cache Status is "HIT"
* Confirm that for files, we show only the /download alias in the browser (not the direct file system URL): `curl -I https://wwwcf.digital.mass.gov/media/1/download` and confirm you see 1) a 200, not a 301, response, and 2) `content-disposition: inline; filename="test.rtf"` in the response.

