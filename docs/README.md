# Table of Contents
See also: [Documentation for Mass Digital team](https://github.com/massgov/DS-Infrastructure/blob/develop/docs/massgov/README.md) (not public)
  
## Developer basics
- [Getting Started](#getting-started)
  - Clone the repo; set up Docker and ahoy; workflow
- [Changelog Instructions](changelog_instructions.md)
- [Composer](composer.md)
- [Peer Review checklist](peer_review_checklist.md)
- [Performance](performance.md)
- [Testing](testing.md)

## API
- [Web Services(JSON API)](webservices.md)

## Content model
- [Content Type configuration checklist](content-type-checklist.md)
- [Descendant Manager](descendant-manager.md)
  - [Configuring Content types for Descendant Manager](https://github.com/massgov/openmass/blob/develop/docs/descendant-manager.md#adding-and-updating-content-types)
- [Map content type to schema.org](schema.org_mapping.md)


## Modules
-  [Modules](modules.md)
   - [List of custom modules](#custom-modules)
   - [Adding a new module to composer](modules.md#adding-a-new-module-to-composer)
   - [Patching a module](modules.md#patching-a-module)
   - [Updating a dependency](modules.md#updating-a-dependency)
   - [Enabling development-only modules (e.g. Devel)](modules.md#enabling-development-only-modules-eg-devel)


## Testing
- [Testing](testing.md)
  - [Behat & PHPUnit](testing.md#run-tests-locally)
  - [Backstop](https://github.com/massgov/openmass/blob/develop/backstop/README.md)
  - [Performance Optimization and Profiling (Blackfire.io)](performance.md)
  - [Nightcrawler](https://github.com/massgov/openmass/blob/develop/.circleci/nightcrawler/README.md)

## Theme & Mayflower
- [mass_theme](https://github.com/massgov/openmass/blob/develop/docroot/themes/custom/mass_theme/README.md)
- [Mayflower Module](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mayflower/README.md)
- [Change the Mayflower version in Drupal](mayflower.md)
- [Mayflower assets integration](mayflower_assets.md)

### Custom Modules

Contributors should familiarize themselves with existing custom modules so they can find and copy established patterns, keep related code together, and generally keep the codebase organized. Because this list will undoubtedly become outdated at times, always consider it a starting point, not an exhaustive list.

- [file_entity_delete](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/file_entity_delete/file_entity_delete.info.yml)
- [file_upload_submit](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/file_upload_submit.info.yml)
- [mass_admin_pages](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_admin_pages.info.yml)
- [mass_admin_toolbar](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_admin_toolbar.info.yml)
- [mass_alerts](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_alerts.info.yml)
- [mass_caching](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_caching.info.yml)
- [mass_content_api](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_content_api.info.yml)
- [mass_content_moderation](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_content_moderation.info.yml)
- [mass_controller_override](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_controller_override.info.yml)
- [mass_dashboard](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_dashboard.info.yml)
- [mass_decision_tree](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_decision_tree.info.yml)
- [mass_docs](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_docs.info.yml)
- [mass_entityaccess_userreference](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_entityaccess_userreference.info.yml)
- [mass_entity_reference](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_entity_reference.info.yml)
- [mass_feedback_form](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_feedback_form.info.yml)
- [mass_feedback_loop](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_feedback_loop.info.yml)
- [mass_fields](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_fields.info.yml)
- [mass_flagging](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_flagging.info.yml)
- [mass_formatters](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_formatters.info.yml)
- [mass_hardening](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_hardening.info.yml)
- [mass_jsonapi](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_jsonapi.info.yml)
- [mass_map](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_map.info.yml)
- [mass_media](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_media.info.yml)
- [mass_metatag](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_metatag.info.yml)
- [mass_migration](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_migration.info.yml)
- [mass_moderation_migration](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_moderation_migration.info.yml)
- [mass_more_lists](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_more_lists.info.yml)
- [mass_nav](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_nav.info.yml)
- [mass_schema_metatag](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_schema_metatag.info.yml)
- [mass_search](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_search.info.yml)
- [mass_search_suppression](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_search_suppression.info.yml)
- [mass_serializer](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_serializer.info.yml)
- [mass_site_map](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_site_map.info.yml)
- [mass_styles](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_styles.info.yml)
- [mass_superset](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_sutperse.info.yml)
- [mass_tours](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_tours.info.yml)
- [mass_utility](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_utility.info.yml)
- [mass_validation](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_validation.info.yml)
- [mass_views](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_views.info.yml)
- [mass_workbench_ui](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_workbench_ui.info.yml)
- [mass_xss](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_xss.info.yml)
- [mayflower](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mayflower.info.yml)
- [scheduler_media](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/scheduler_media.info.yml)
- [tfa_unblock](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/tfa_unblock.info.yml)
- [trashbin](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_trashbin.info.yml)
