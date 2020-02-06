## [0.229.0] - February 5, 2020

### Changed  

-DP-17289: Authors can now enter hours for contacts in 5-minute increments.
-DP-14197: Checking to see if there is a holiday or any changelogs are there before cutting the branch.  
-DP-17014: Adjust Superset config to include promo page (aka campaign landing).
-DP-16864: Exclude the static Google map images, iframes, campaign page header banners from the backstop test by covering them.
-DP-17049: Add description metatag to curated lists and info details.
-DP-17039: Allow multiple types for document media entity and display choices as checkboxes.
-DP-16934: Allow forks to pass limited testing.
-DP-16628: Render temp. unpublished links to "What you need to know" on the service page. 
-DP-17287: For service details and info details, we changed conditional logic to show the data resource type field only if the data type field = "data resource" We add help text to the data type and data resource type fields on service details, info details, and curated list to define the options.
-DP-17047: Modify the mg_type metatag to support multiple values.

### Fixed  

-DP-14197: Fixed to use the changelog markdown to the PR body and GitHub release tag instead of the placeholder.
-DP-17313: Restore triggered workflows at CircleCI.
-DP-17189: Fix sitewide alert notification function to use updated moderation states from mass_content_moderation.
-DP-17005: Resolves an issue where the nightly database population fails due to missing Drush aliases and invalid API calls.
-DP-17313: Resolves an issue where Nightcrawler would not crawl in CircleCI after release automation changes.
-DP-17286: Resolves an issue where ma-refresh-local was unable to download the database over Acquia's V2 API.
-DP-17333: Add missing "Entity\Media" class to fix `mass_serializer` cron job error.

### Added

-DP-16688: Add severity name to Syslog.
-DP-17242: Python2 for CloudFlare deployment.
-DP-17135: Add the ability to embed Tableau visualizations on info-details pages.
    
## [0.228.0] -January 22, 2020

### Fixed

- DP-15833: Added `field_links` to Location content type linking pages config.

### Changed

- DP-16915: Hide the "Temporary Unpublished Access" form on the edit page for media items.
- DP-17004: Limit characters for the button's text to 35 characters (in key message on promo pages).
- DP-17036: Remove hyphens from the `mg_organization` metadata for media entities.
- DP-16772: Add comments to `docker-compose` regarding the database container.
- DP-11540: Remove the option to create an "unlimited" temporary unpublished access token from the node form.

### Added

- DP-17050: New fields are added to Service Details, Info Details and Curated Lists that allow authors to tag the pages so that they will appear in the data tab on search when it is released. This ticket is just the authoring user interface, it doesn't include full configuration of the meta tags.

## [0.227.0] - January 15, 2020

### Added

- DP-16657: Added config for route iframe for promo page analytics dashboards

### Fixed

- DP-15854: Alerts JSON should return 250 items
- DP-16945: Media download links not resolving properly if they result in multiple redirects to get to the file.

## [0.226.0] - January 8, 2020

### Changed

- DP-16624: Update contents of emails sent to users for account changes and Watching.
- DP-16283: Upgrade to Acquia Cloud API 2
- DP-16767: Update mserc Drush command, and Drush core.
- DP-16815: Update .eslintignore.
- DP-16844: Update Drupal core to 8.7.11.
- DP-16853: Update links to knowledge base articles for restricted access, scheduling.
- DP-16876: Adjust Nightcrawler failure thresholds to reduce false positives.
- DP-16877: Remove old admin theme no longer in use.
- DP-16881: Restart automation after holiday break.
- DP-16927: Add intended audience to Drupal API
- DP-17023: Update Mayflower version to 9.36.0.

### Fixed

- DP-16815: Fix errors in in-house custom js files flagged by eslint.

### Security

- DP-16805: Security update for the PHP library `symfony/cache` (CVE-2019-18889 - https://nvd.nist.gov/vuln/detail/CVE-2019-18889).
- DP-16808: Update serialize-javascript for cloudflare.
- DP-16815: Update eslint from 2.13.1 to 4.18.2.

## [0.225.0] - December 18, 2019

### Changed

- DP-16764: Stop purge from false exception at end of a deployment.
- DP-16689: Exclude purges sent to New Relic.

### Added

- DP-16429: KPI information added to api/v1/content-metadata REST endpoint

## [0.224.1] - December 11, 2019

### Changed

- DP-16759: Downgrade Drush to fix sitemap and massdocs cron tasks.
- DP-16759: `ma:deploy` erroring during cache clearing.

## [0.224.0] - December 11, 2019

### Changed

- DP-16625: Add a KPI choice field to promotional page, conditional logic to display KPIs, modifies percent/CTR KPIs to use 0 to 100 scale, modifies related help text.
- DP-16655: Selective Varnish purge at end of deployment.
- DP-16598: Update dependencies - cache_metrics, devel, drush
- DP-16638: Update to use Mayflower 9.33.0.

### Added

- DP-16524: Added validation to the KPI choice to make sure each checkbox has value if checked off. If unchecked checkbox the field value will be reset.
- DP-16585: Added the "-y" option for prod's deployment to proceed once approved via CircleCI.

### Fixed

- DP-16524: Resolved a test failure due to PHP notices in entity comparisons that was uncovered by an upstream content change.
- DP-16695: Resolve the error with curated list pages by correcting the field names in /modules/custom/mass_content/src/EntitySorter.php.

### Security

- DP-16674: Update symfony/http-foundation from 3.4.27 to 3.4.35 (https://github.com/advisories/GHSA-xhh6-956q-4q69).

## [0.223.0] - December 4, 2019

### Changed

- DP-16585: Config fixes for Circle deployments, skip-maint and refresh-db
- DP-16322: Uninstalled a few modules content export yaml, manager content export yaml, mass yaml content, and vbo content export yaml.
- DP-16585: Refactored deploy jobs in config.yml
- DP-16563: Numerous field label and help text changes for the promotional page
- DP-16596: Changed the default help text for fields using both external/internal with core patch for link module. If will not override any code changes to help text.
- DP-16470: Makes promotional pages available for authors and editors to create, clone, edit own, edit any, but not publish, revert or recycle.
- DP-16625: Add a KPI choice field to promotional page, conditional logic to display KPIs, modifies percent/CTR KPIs to use 0 to 100 scale, modifies related help text.

### Removed

- DP-16322: Removed the yaml_content module and the custom mass_yaml_content module.

### Fixed

- DP-16564: Minor updates to documentation after move to openmass repo
- DP-16524: Resolved a test failure due to PHP notices in entity comparisons that was uncovered by an upstream content change.

### Added

- DP-16469: Build a super samitized database image nightly
- DP-16585: Run test suite with super sanitized image nightly
- DP-16620: Added a new job to deploy code to production from CircleCI. Also added the job to our current workflow for build_tag with hold button.
- DP-16524: Added validation to the KPI choice to make sure each checkbox has value if checked off. If unchecked checkbox the field value will be reset.

## [0.222.0] - November 20, 2019

### Changed

- DP-16567: Removed "/agr" path from Cloudflare config.
- DP-15695: Update a few release bits from mass to openmass

### Added

- DP-16555: Create a way to produce a sanitized database that has no username or roles in it.

## [0.220.1] - November 13, 2019

### Removed

- DP-16557: Remove the legacy `/resources` redirect path from Cloudflare configuration.

## [0.220.0] - November 13, 2019

### Changed

- DP-16426: Updated the paragraph type module from 1.6 to 1.10.
- DP-16426: Update the entity_reference_revisions module from 1.6 to 1.7
- DP-16525: Limit promotional page sections to 9, allow links in mosaic to promotional page.
- DP-16499: Enabled character countdown in key message in promotional page.
- DP-11415: Updated Mayflower version to 9.31.0
- DP-16457: Correct semantics of sub title and apply title heading level + 1 styles to it. (MF)
- DP-16457: Fix padding bug introduced if only title and button. (MF)
- DP-16483: Fixed collapse animation if max dimension passed on IE11. (MF)

### Added

- DP-15843: Log cache tag invalidations especially for use during incident response
- DP-16435: Added the scheduler to all campaign landing pages and made the unpublished date required.
- DP-16435: Added validation to the unpublished date to only allow a date less than 14 months.
- DP-16491: Add a KPI tab and 5 KPI fields to the promotional page. Renames campaign page to promotional page
- DP-16447: A new role has been created for campaign landing page publishers, associated workflow and view changes were made.
- DP-16478: Add missing link label context info for video transcript link.
- DP-16466: Create a way to produce a sanitized database that has no previous revisions in it.
- DP-16466: Create a way to produce a sanitized database that has no unpublished content in it.
- DP-16467: Create a way to produce a sanitized database that has no secrets in the key_value table.
- DP-16468: Create a way to produce a sanitized database that has no log messages in it.
- DP-15695: Be more explicit about our License

### Fixed

- DP-16527: Fixed the config export for the campaign landing page.
- DP-16527: Fixed the cron time to release_branch and mysql_rebuild workflows. The UTC does not adjust to daylight saving time.

## [0.219.0] - November 6, 2019

### Changed

- DP-16285: modify help text for image selection on non-header key message on campaign page
- DP-16251: Limit link fields on the campaign page to allow only certain types
- DP-16345: Remove key message alt text field on campaign page
- DP-16362: Change key message button layout in campaign page
- DP-16364: Change "text overlay color" labal on campaign page
- DP-16365: change description field of video component on campaign landing page to use correct text format
- DP-16370: make image fields required for features row on campaign page
- DP-16363: make color choices more clear and consistent for campaign page
- DP-16460: Updated Mayflower version to 9.30.0.
- DP-16287: Add a logic to assign proper green with or without background image to key message in content area. (MF)
- DP-16299: Change the width of the content area key message block from 840px to 1240px in desktop display. (MF)
- DP-16299: Adjust spacing button and message content container. (MF)
- DP-16299: Set a condition to print the keymesage heading only when its value is available. (MF)
- DP-16299: Change the opacity of the ovelay color in the content area to 0.8. (MF)
- DP-16299: Adjust the bottom spacing for feature card in desktop display with or without more link. (MF)
- DP-16299: Adjust margin and padding for feature container. (MF)
- DP-16299: Adjust margin and padding for video. (MF)
- DP-16336: Adjust margin and padding for campaign page components. (MF)
- DP-16314: Correct closing heading level for feature card. Removed id from secondary cards. Correct the wrong class name for the card and the condition to add the class to the container. (MF)
- DP-16314: Update landing page jsons to match the change for ID. (MF)
- DP-16314: Add `focusable='false'` to svg icons. (MF)
- DP-16312: Reposition style and script for the background image above the keymessage container. (MF)
- DP-16312: Add a condition to add ID only when its value is available to key message section. (MF)
- DP-16312: Remove the extra curly brace causing a parse error. (MF)
- DP-16312: Add a condition to button link to add title only when its value is available. (MF)
- DP-16312: Modify the condition to add the second set of style. (MF)
- DP-16248: Changed the maxlength for text editors within the Campaign landing pages. The maxlenght has changed from 250 to 300.

### Fixed

- DP-16463: Remove background data specifically for heading key message from ones for content area ones.
- DP-16450: Fix the file system from moving documents from private to public storage and not creating a directory.
- DP-16452: Add a missing ID to Campaign landing page feature primary card for its background image setup.
- DP-16452: Correct the heading level of Campaign landing page feature secondary cards.
- DP-16452: Adjusted /modules/custom/mayflower/src/Render/SvgProcessor.php to add focusable="false" to svg icons.
- DP-15827: Updated help text link to request access to image library in Org page banner field.
- DP-15482: Fixed the rewrite rule for PDF documents with the Cloudflare workers.

### Added

- DP-16286: Added a text overlay color to the key message sections.
- DP-16286: Added some of the conditonal logic to the key message sections to work with the background type.

### Removed

- DP-16286: Clean up some of the conditional fields that were using field_content_layout.

## [0.218.0] - October 30, 2019

### Changed

- DP-16374: Update config for a field on the video media type to allow only 1 text format; also commit changes to help text string formatting that appear when changing config.
- DP-16245: 2 up featured item and 2 up featured item 2 are now collapsed instead of expanded.
- DP-16096: Changed the Campaign Landing page restricted text format to authenticated users role having access.
- DP-16291: Updated Mayflower version to 9.29.0.
  - DP-16164: Set key message component height to be adjustable based on available content in the container maintaining even padding on top and bottom. (MF)
  - DP-16271 Added breakpoints and Increased font size for H2. (MF)
- DP-13780: Revise main readme.md in preparation for open sourcing; also remove /docs folder (moved to DS-Infrastructure)

### Fixed

- DP-16334: Fix the Mayflower develop automation to push_acquia and deploy to CD on Sunday nights.

### Added

- DP-16376: Releaseing decision tree types for authors and editors

## [0.217.0] - October 23, 2019

### Changed

- DP-16276: Changed the github_tag job to not wait for test_without_danger before allowing a release manager tagging the master branch.

### Fixed

- DP-16276: Fixed the release automation by reverting back to the old way.
- DP-15661: Fix the pathologic module from breaking image styles routing to allow us to use for the legacy redirects replacement.
- DP-16108: Fixed the overlay box positioning for key message when it has a solid color background.
- DP-16108: Set a condition to define variable for background color based on background type option.
- DP-16249: Fixed the conditional logic and twig template for the key message subtitle and description fields.

### Added

- DP-12419: Adds legacy redirect replacement drush commands.
- DP-16252: Add campaign landing page QAG pages to backstop.
- DP-16025: Added log messages for moving documents between public and private folders.
- DP-16167: Added the Basic tags and Advanced to the campaign landing page edit form. Setup the default for following description, og_image, og_description, twitter_cards_image, twitter_cards_description, and twitter_cards_type.

### Removed

- DP-16249: Remove the layout choice from the key message header and sections paragraph type.

## [0.216.0] - October 16, 2019

### Changed

- DP-6357: Updated feedback form template to match a11y changes in Mayflower.
- DP-15832: Updated help text on Org page banner image field to link to image library on smugmug.
- DP-15422: Relocate secrets from the git repo.
- DP-16092: Update the help text for the 2up and single feature images.
- DP-16231: Updated Mayflower version to 9.28.0.
  - DP-16115: Update Feedback Form to allow toggling required "legend" items to improve accessibility. (MF)
  - DP-16115: Add required attribute to radio button. (MF)
  - DP-16115: Add role="radiogroup" and a condition to add aria-required="true" to fieldset. (MF)
  - DP-15349: Added text fade and collapse/expand functionality to overview text area. (MF)
  - DP-16109: Set a condition to check bgImage and set class for overlay box position to section based on its result. (MF)
  - DP-16028: Fix video transcription display to lined up as one line and no overwraping to video. (MF)

### Added

- DP-16056: Added the Custom HTML back to the header and sections in the campaign landing pages
- DP-16012: Added a new link field and passed it through to the Mayflower template.
- DP-15515: A new command `ahoy start` spins up a fresh massgov site.
- DP-16133: Added the campaign landing pages to the filter for content types on the following views All content, My content, and Needs review.

### Fixed

- DP-16056: Fixed the paragraph type to only allow an Administrator role access to add the custom HTML field to the campaign landing page.
- DP-16132: Fixed the horizontal list to display on the key message within the section field for campaign landing page.
- DP-16046: Update links to Knowledge Base articles.

### Removed

- DP-16092: Removed the alt text for all images in the 2up and single feature components.
- DP-15816: Uninstall Crazy Egg module.

## [0.215.0] - October 9, 2019

### Changed

- DP-16102: Updates to the Campaign Landing Page content type Campaign Video background color field.
- DP-16120: Updated Mayflower version to 9.27.0.
  - DP-16102: Updated background color variables to match color variable names in MF. Added top/bottom padding to the component. (MF)
- DP-16110: Config backport for permissions for campaign landing page for content admins to create, edit, revert, clone. For anonymous users to see unpublished pages.
- DP-15809: Changed styling on alert messages in the authoring interface so links are underlined.

## [0.214.0] - October 3, 2019

### Fixed

- DP-16041: Fix the heading level based on the component position.
- DP-16041: Fix banner image rendering issue.
- DP-16041: Fix one of text overlay options was not available.
- DP-16041: Add 'div.main-content.main-content--full'.
- DP-16088: Fix link URLs in campaign featured cards to fill proper value to each href.
- DP-13660: Update popular search links to use "link list" instead of deprecated "helpful links" templates.

### Changed

- DP-16081: Updated Mayflower version to 9.26.0.
  - DP-15986: Adds primary button theme variant of c-white. (MF)
  - DP-4562: Add "more" link to Campaign Features component. (MF)
  - DP-15986: Updated template so bottom key message lines up with footer. (MF)
  - DP-15986: Update campaign feature to use ma-container mixin gutters. (MF)
  - DP-15986: Update key message component to pass color themes. Update to render with boxed callout and solid overlay callout. (MF)
  - DP-14562: Set received heading level value to 'headingLevel', keyMessage.compHeading.level to headingLevel. Add a filter for banner image URL not to encode "&". (MF)
  - DP-16073: Stack image on primary card on tablet size screen. (MF)
  - DP-13660: Fixed spacing on mobile devices to prevent popular search links from overlapping image credit. (MF)
  - DP-15262: Updated sidebar handling so the first item is showing full-width when no sidebar content is present. (MF)
  - DP-15979: Fixed flexbox bug related to layout on small screens. (MF)
  - DP-15986: Updated iframe video allow params to prevent console error. (MF)
  - DP-15986: Fixes bug related to setting the theme of the component. (MF)
  - DP-16073: Fix spacing between marketing campaign page sections. (MF)
  - DP-16073: Remove heading style override and wrapper top/bottom padding in preference of inheriting margins from the page. (MF)
- DP-15978: Updates the campaign landing page content type fields.
- DP-15806: Updates to the Campaign Landing Page content type.
- DP-15696: Updated templates to standardize heading level setting.

## [0.213.0] - September 26, 2019

### Added

- DP-12549: Added an apple touch icon
- DP-15929: Custom style to CKEditor campaign text format and updated key message template so it renders on the page via Mayflower.

### Fixed

- DP-15972: Fixed the notice error we were seeing locally when adding a video to a content type.

### Changed

- DP-16007: Updated Mayflower version to 9.25.0.
  - DP-15872: Adds card molecule with primary (default) and secondary usage variant. (MF)
  - DP-15813: Added color options and made other styling tweaks. (MF)
  - DP-15731: Added ability to add classes to lists; implemented ma\_\_horizontal-list class. (MF)
  - DP-15872: Replaces campaign feature full width molecule with card molecule. (MF)
  - DP-15872: Updates campaign feature 2up to consume new card molecule in secondary variant and updates molecule scss. (MF)
  - DP-15872: Updates campaign feature organism to consume primary card molecule and adds organism scss. (MF)
  - DP-15856: Uses google map's viewport biasing when converting user's input into a geocode so that places from and around Massachusetts are returned, thereby fixing previously faulty location filtering. (MF)
  - DP-12883: Bumps browser-sync from 2.26.3 to 2.26.7 and handlebars from 4.05 to 4.1.2 to address security vulnerabilities. (MF)
- DP-15262: Updated the sidebar logic for info detail pages so it is hidden when empty.
- DP-16002: Updated campaign video paragraph so that the widget used to add a video matches the widgets from elsewhere on the site.

## [0.212.0] - September 24, 2019

### Added

- DP-15479: Module for importing and exporting of entities

### Changed

- DP-15716: Updated codebase to allow for site installation from configuration alone.

### Fixed

- DP-15391: Updated admin theme styles to prevent inline entity forms from loading as blank forms.
- DP-15943: Update campaign feature implementation so pre-rendering occurs in .theme file.

## [0.211.0] - September 19, 2019

### Added

- DP-14105: Added field_info_detail_overview and modified node--info-details.html.twig to use it
- DP-15935: Backport config for the iframe URLs needed to add https://www.eia.gov/beta/states/iframe to allowed iframes URLs.
- DP-15842: There were behat test steps to check for dynamic cachability for only "how_to_page" content type. Similar test steps have now been added to 16 other node content types.

### Security

- DP-15779: Updates codebase dependencies.

### Fixed

- DP-15805: Fixed the duplicated news items from the Organization page content type appearing.
- DP-15938: Rollback traefik container image version to v1.7.4 to mitigate issues with the latest version of traefik tag.

### Changed

- DP-15937: Updated Mayflower version to 9.24.0.
  - DP-15605: Add context info to sticky TOC buttons to reflect the state of the TOC display. Switch focus as the flyout toc shows/hides. (MF)
  - DP-15896: Makes decorative link in a page alert show inline without unexpected wrapping up. (MF)
  - DP-14105: Added an overview rich text field to the information-details component. (MF)

## [0.210.1] - September 13, 2019

### Fixed

- DP-15863: Fixes a bug that caused revision's date and author data to not render on the node/NID/revisions page even though the data was present.
- DP-15855: Fixes the curated list links to display correctly without document icons and quasi-URL strings.

## [0.210.0] - September 12, 2019

### Changed

- DP-15803: Patch key module for too frequent cache invalidation
- DP-11226: Updated field help text to remove links to deprecated velir URLs.
- DP-15860: Updated Mayflower version to 9.23.0
  - DP-15521: Adds key message component and Campaign Marketing Page. (MF)
  - DP-15512: Version 1 of the Full Width Campaign Full Width Header. (MF)
  - DP-15564: Add campaign video component. (MF)
  - DP-15583: Adds campaign feature molecules and organism. (MF)

## [0.209.0] - September 10, 2019

### Added

- DP-15584: Support fields and settings for the Features components on Campaign Landing pages.
- DP-15580: Support fields and settings for the Video component on Campaign Landing pages.
- DP-14111: Added a new Restricted moderation state for documents
- DP-15579: Support fields and settings for the Key Message component.

### Changed

- DP-14242: Updated the threshold for Nightcrawler from 5 seconds overall to 1 second overall; 20 seconds per content type basis to 2 seconds per content type basis.
- DP-15714: Updated Drupal core to version 8.7.6.

### Fixed

- DP-15828: Locked the version of Google Maps Javascript API that Mass Gov uses to version 3.36 so that bad 404 URLs are not created on IE11.

## [0.208.0] - September 5, 2019

### Changed

- DP-9194: Updated link rendering to parse file extensions from directly linked file URLs to make linked files more accessible.

## [0.207.0] - September 3, 2019

### Changed

- DP-15457: Update alert content type to require end date.
- DP-14932: Added author filter to the All content and Needs review views.
- DP-15081: Switch to Real AES in TFA encryption profile.

## [0.206.0] - August 29, 2019

### Added

- DP-14486: Added 'pages linking here' to media within the info details content type.

### Fixed

- DP-14870: Update description text for url field
- DP-15462: Publication Status filter on the Content views to show all content that has a published revision

## [0.205.0] - August 22, 2019

### Changed

- DP-15587: Updated Mayflower version to 9.21.0.
  - DP-9085: Added line break to address. (MF)
  - DP-14912: reposition stickyTOC on mobile screens. (MF)
- DP-14969: Avoid no-op Cloudflare URL purges.

### Added

- DP-15423: Added the YAML content module to use for converting our QAG pages to YAML files to start the process of open-source the repository.

## [0.204.0] - August 20, 2019

### Fixed

- DP-15593: Updated clone module to ensure clone permissions are respected.
- DP-14942: Fixed the sort for person and contact list type to use correct field for display title when using a contact information content type in the automated list. When an author uses a List - Automatic with a person content type it will alphabetize by the last name then first name if the last name is the same.

### Added

- DP-15193: Added de-duplicate logic to computed log in field so links displayed to the contextual navigation target unique destinations.
- DP-15371: Added new Campaign Landing content type with support fields and styles.
- DP-15353: Updated relationship manager to update Related To links for certain content types referenced from service pages.
- DP-15519: Added logic to provide a default value for the Event Quantity field when editing existing Org and Service pages that do not have the value set.

### Changed

- DP-15519: Updated Service Page event quantity field description.

## [0.203.0] - August 15, 2019

### Changed

- DP-15524: Updated Mayflower version to 9.20.0.
  - DP-9274: Added `labelContext` variable for an optional visually-hidden suffix (MF)
  - DP-8937: Made contact groups inside accordions always stack vertically. (MF)
  - DP-9274: Changed JSON to use the `link` component’s new `labelContext` variable in the style guide. (MF)
  - DP-15523: Removes sticky TOC from print. (MF)
  - DP-6174: Removed search field clear button in IE. (MF)

### Added

- DP-12419: Adds legacy redirect replacement drush commands.
- DP-11179: Added the view bulk operations module to allow admin/content admin roles the ability to change authored by.
- DP-11179: Added a view "Bulk change node author" under Manage > Content dropdown to allow admin/content admin role ability to change authored by in bulk.

### Fixed

- DP-11179: Fixed the view "Bulk change node author" to not add the current user as a "Watcher" when bulk updating all pages. It will add the new Authored by as the watcher instead.

## [0.202.0] - August 13, 2019

### Changed

- DP-14969: Use a purge queue that deduplicates queue items to reduce the potential of duplicate items being queued.
- DP-9870: Updated all of the legacy URLs within the alerts page with current CMS pages to elimate the legacy redirects being used.
- DP-14684: Removed help text and updated label for file download single paragraphs.
- DP-15352: Updated allowed content types in Key Information field on service pages to allow Decision Tree and News content.
- DP-12246: Update content view title to "All Content"
- DP-15284: Changed the default moderation state from "draft" to "prepublished draft". Updated the following Prepublished Draft and Unpublished workflows.
- DP-15453: Backport config for iframe URLs needed to add https version for one of the URLs.

### Added

- DP-15083: Added new Event Quantity field and logic to allow displaying more than two upcoming events on Service and Org pages.

### Fixed

- DP-11588: Fixed the help text for the TFA recovery code login screen by updating the syntax for the correct format.
- DP-15417: Updated implementation of contextual links to render at the node level and update placement of the links with JS.

## [0.201.0] - August 8, 2019

### Added

- DP-15198: Limited the number of links allowed to be entered in the Log In Links field on Service pages.
- DP-15348: Added description onto Overview field on service pages.

### Changed

- DP-13038: Update the email address from MassITDigitalServices@mass.gov to DigitalSupport@mass.gov for site setting and flag contact form.
- DP-15302: Services conditional field for social now always visible. Changed "Top tasks" to "Featured" on service page with custom link groups.
- DP-15300: Update the alert page to display MEMA twitter feed under the Key Resources in the sidebar.

### Removed

- DP-15403: Modified More services field of organization content type to disallow how-to which was added in error.

### Fixed

- DP-15392: Updated service page template conditional to display Action finder if any featured tasks, all tasks, and/or link groups are set.

## [0.200.1] - August 7, 2019

### Fixed

- DP-15397: Temporarily commented out cache tag code from DP-15204 and a small code snippet from the larger contextual link work from DP-13944 so that dynamic cache usage does not fall on PROD site and performance remains high.

## [0.200.0] - August 6, 2019

### Added

- DP-15080: Added logic to include the All Tasks field on Service Pages to the relationship indicators on forms.
- DP-13958: Added new drush command to assist in revision cleanup and maintenance.
- DP-15236: Added help text and its support styles for beta Decision Tree content type edit pages.

### Fixed

- DP-15204: Updated cache context setting for contextual navigation.
- DP-12345: Update the guide page to remove overrides preventing the display of section divider horizontal line.
- DP-15314: Avoid sporadic drush cr error at end of deployments.
- DP-15088: Fixed Content View's CSV download to not have multiple rows for a node, one for every revision. Now it only has one row per node with combined data of the published revision and the latest revision, and data in the CSV matches data in the All Content View UX.
- DP-12345: Update the guide page to remove overrides preventing the display of section divider horizontal line.
- DP-15194: Hid and set default value for published states of fees in inline forms.

### Changed

- DP-15246: Update file download links to use media entity link rather than direct links.
- DP-15005: Use API keys rather than Client IDs for authenticating with Google Maps APIs
- DP-13958: Updated monolog module to resolve errors with call to missing class.
- DP-15070: Updated label and allowed content types for the Organization 'More actions' field.
- DP-15236: Moved help text block into header on admin theme directly beneath the title.

## [0.199.0] - August 1, 2019

### Changed

- DP-15200: Update my recent content block so title has correct url.
- DP-14449: Allow editors to move directly from Published to Needs Review.

### Fixed

- DP-14449: Needs Review view was not consistently showing content that was in Needs Review status.
- DP-14893: Fixed the scrolling for adding a paragraph so it scrolls top of paragraph into view.

## [0.198.0] - July 30, 2019

### Fixed

- DP-15112: Fixed a missing space between the branch name and origin when pushing up to GitHub.

## [0.197.0] - July 25, 2019

### Fixed

- DP-14926: Fixed content views such that the Publication Status field shows currently published status of the node, not the status of the latest revision which could be an unpulished draft.

### Changed

- DP-14637: Add location page dashboard to CMS. Removed location content type from basic pages dashboard.
- DP-11107: Updated the contact us template for contact information to use the contact-row template in Mayflower.
- DP-15191: Update Mayflower version to 9.18.0.
  - DP-15056: Added blocks around contact groups to allow overriding on a field-by-field basis. #684 (MF)
  - DP-13005: Remove unused/broken template from Mayflower #653 (MF)
  - DP-9262: Add underline to `:hover` and `:focus` states of image links #678 (MF)
- DP-14682: Updated the content type category label for Decision Tree to indicate its 'Beta' status.

### Added

- DP-14447: Add authenticated alerts block to admin pages.

### Security

- DP-15196: Update the Metatag module from 1.8.0 to 1.9.0 this will prevent access while the site is in maintenance mode.

## [0.196.1] - July 24, 2019

### Fixed

- DP-15133: Fixed the contact information edit form from a 500 error for "Unknown or bad timezone ()"

## [0.196.0] - July 23, 2019

### Fixed

- DP-14834: Set nodes/node revisions that still contained the "archived" moderation state to "unpublished"
- DP-11179: Fixed the CircleCI testing section we had to comment out the `git diff -w --exit-code` for the following PR DP-14570 flag module.
- DP-14855: Resolves issues where caches aren't selectively cleared for updates to contextual navigation links.

## [0.195.1] - July 23, 2019

### Fixed

- DP-15135: Fixes display of "More Information" section for Service Pages with default templates when both Featured Tasks and All Tasks fields are empty.

## [0.195.0] - July 18, 2019

### Added

- DP-14259: Implement a new variation of the service page template.

### Changed

- DP-14191: Updated logic for news overflow pages to nest subtitle in h1.
- DP-9195: Update Offered By block in Service Page sidebar to directly use Mayflower markup.
- DP-15027: Updated Mayflower version to 9.17.0.
  - DP-14741: Allow EmergencyAlerts to render everywhere not just right below header, specify mobile top positioning with .ma\_\_ajax-pattern wrapper and fix z-index to not overlay main nav dropdown. (MF)
  - DP-14173: Add a block for overriding columns. (MF)
  - DP-14173: Add a block for overriding link list loop. (MF)
  - DP-11891: Fixed How-to pages printer style being incorrectly indented. (MF)
  - DP-9211: Remove the text alternative for the banner image in Guide and Binder pages. (MF)
  - DP-13965: MF Change title and H1 on news overflow pages. (MF)
- DP-14995: Set the timing to create a release branch to the original setting.

### Removed

- DP-9211: Remove 'bgInfo' for alt text data from mass_theme/templates/content/node--guide-page.html.twig and /mass_theme/templates/content/node--binder.html.twig, disable 'alt field' for field_guide_page_bg_wide.

### Fixed

- DP-14948: Resolved regression that prevented the display of the utility nav body text when no links were present in the Utility Drawer links field.

## [0.194.0] - July 16, 2019

### Changed

- DP-12885: Updated various help text throughout edit.mass.gov.
- DP-14721: Update relationship manager to support new link group fields.
- DP-11181: Updated validation logic for contact components to require at least a single contact method.
- DP-10778: Create a script to pull in the Mayflower develop branch early to test with Drupal on Mondays. The automation will take place every Sunday night at 11 p.m. EST on the CD environment. The new branch will not be deleted automatically in GitHub when completed yet.
- DP-14570: Updated the flag module to allow the view bulk operations to work in views.
- DP-11992: Modifies the help text for nonstandard hours on contact content type.

### Fixed

- DP-10137: Fix action links so they use title instead of url.
- DP-14815: Fixed the URLs for the All content and My content views when a published page has a draft after it in the revisions. The URL will not display the revision link anymore in those views.

## [0.193.0] - July 11, 2019

### Added

- DP-13944: Added new field to allow setting log in links on a per service page basis and logic to show contextual log in links based on the page being viewed.
- DP-14174: Add fields and validation to support a new service page template or variation.

### Changed

- DP-14116: Upgrade Drupal core to 8.7
- DP-14305: Exposed the author field on Document media edit forms.
- DP-14679: Updated Mayflower version to 9.15.1.
  - DP-13939: Add second panel to utility nav for contextual login links and vanilla js functionality. (MF)
  - DP-14175: Update styles and templates for the Stacked Row Section to resolve inconsistent applications of padding and spacing in parent templates. (MF)
  - DP-12216: Update banner image scss to avoid background repeating. (MF)
- DP-13168: Updated styles to resolve indentation and spacing issues.

## [0.192.0] - July 9, 2019

### Changed

- DP-12834: Updated feedback manager with "watched pages only" filter.
- DP-14594: Update help text on title fields for certain content types.
- DP-14627: Re-starting automated release steps after 4th of July holiday week.

### Added

- DP-10397: Trash workflow for Documents.

### Fixed

- DP-14163: Last revision status now reflects most recent revision status in content views.

## [0.191.1] - July 3, 2019

### Fixed

- DP-14680: Content types that do not require field_organizations aren't forced to enter a value.

## [0.191.0] - June 27, 2019

### Fixed

- DP-10645: Screen unpublished and deleted items in the fields, field_service_ref_actions_2, field_service_ref_actions, field_service_key_info_links_6 from rendering. Correct the item contes for What would you like to do? and What you need to know. Author entered link text to be respected over original node title.
- DP-10495: Fixed event agenda template so minutes link appears only when an event has minutes.
- DP-13971: Fixes incorrect required condition on input of organizations during editing of a node.

### Changed

- DP-13808: Updated help text on Event complex date field.
- DP-14600: Updated Mayflower version to 9.13.0.
  - DP-13864: Moved arrow icon into the a tag to make clickable within the stickyTOC.js file, styled accordingly in the `_sticky-toc.scss` file (MF)
  - DP-13951: Add option for link list instead of more link to suggested pages. (MF)
  - DP-14173: Service page redesign with grouped links (MF)
  - DP-14341: Updated the emergency alerts organism scss and the emergency alert molecule scss to use tint instead of lighten for background colour. (React, PatternLab) (MF)
- DP-14390: Fix issues with incorrect filtering and published status column in the admin/content CSV export.
- DP-13332: Fix 500 error to show 404 instead for missing files.

### Added

- DP-13072: Added a Nearby/related locations section to location nodes using orgs and services that reference the location.

## [0.190.0] - June 25, 2019

### Fixed

- DP-14435: Fixed the reverted autocomplete results to show all content types that contain that title match. Adjust the query to show all results that are contain those words.
- DP-14375: Patched 8.6 so canonical links use full url.

### Changed

- DP-13964: Set metatag robots=noindex, follow on overflow pages
- DP-10800: Update documents view to allow for csv export.
- DP-14496: Updated performance.md to include how to test for query changes that could affect overall performance of the project.
- DP-12597: Added .rte and .rfa to list of allowed file types to upload.
- DP-10994: Alters news content type metadata for description and twitter description tags.

### Added

- DP-14568: Added the permission for the Author role to allow clone nodes in the edit form.

## [0.189.0] - June 20, 2019

### Changed

- DP-13873: Added date filtering for feedback.
- DP-12965: Updated author contact link on revisions page so it generates a subject line.
- DP-13566: Ensure title has been changed and retain original author when cloning a node

### Removed

- DP-10773: Removed "Public Access Level" field from document media.

### Added

- DP-12419: Added a Nightcrawler automation to crawl feature3 with 1000 samples size to test the legacy URLs PR.
- DP-14370: Purge Cloudflare urls for newly existing file and node URLs.

## [0.188.0] - June 18, 2019

### Changed

- DP-13966: Updating help descriptions for decision tree content types.

### Added

- DP-13009: Now topic pages also show "Offered By" organizations and "Related To" cross links like other content type pages

## [0.187.1] - June 14, 2019

### Fixed

- Revert DP-14199: Fixed the autocomplete results to show all content types that contain that title match. Adjust the query to show all results that are contain those words.

## [0.187.0] - June 13, 2019

### Fixed

- DP-14374: Fix the curl access to the GitHub API and the release branch time changed back to noontime.
- DP-14199: Fixed the autocomplete results to show all content types that contain that title match. Adjust the query to show all results that are contain those words.

### Changed

- DP-14285: Override edge TTL for legacy
- DP-14347: Run pending entity updates

## [0.186.0] - June 11, 2019

### Fixed

- DP-12313: Improve cache invalidation for events listing page on organization and service pages.
- DP-14203: Fixed a missing bracket in the email sumbit form which throws an error in Safari browser.
- DP-14345: Fixed the 500 error displaying for event listing pages to max_age_limit default is one week.
- DP-14087: Fix admin/content CSV export not working under new CDN.

### Added

- DP-10836: Added new Manage your account section to How To pages.
- DP-13290: Added a new viewport to Backstopjs for testing the desktop view.

### Changed

- DP-14302: Simplify Cloudflare deployments by adding a shell script to trigger deployment.

## [0.185.0] - June 6, 2019

### Fixed

- DP-14256: Allow editors role to be able to view any unpublished content.
- DP-14256: Allow editors and authors roles to delete unpublished access tokens.
- DP-14258: Fixed the content type filter to be exposed to content authors in the following views - My content, All content, and Need review.

### Changed

- DP-13079: Create new billing organization field for organizations and populate it
- DP-10166: Automating the release branch to cut at noontime every Tuesday and Thursday. This will create a PR in GitHub against master branch each time the workflow has completed.
- DP-10166: Update and reviewed the release documentation with this new release process

### Added

- DP-10166: Added Dangerfile to check each PR for a changelog file and make sure the schema correct for each ymal file.
- DP-10166: Added a new job to create a tag directly off the master branch in CircleCI (includes an hold trigger) after merging the release branch into it.

## [0.184.0] - Jun 5, 2019

### Changed

- DP-14108: Prevent invalid 0.0 values from displaying in the admin content score columns

### Fixed

- DP-14195: Fixes performance issues for the My Content view and restores it to visibility for authors/editors. Also fixes scores to be linked to the analytics dashboard for that node.

## [0.183.0] - May 30, 2019

### Changed

- DP-13992: Update modules - crazyegg devel csv_serialization subpathauto.
- DP-10720: Restricted "Featured tasks" field on Service pages to exclude. Contact Info, Fee, Legacy redirect, Organization, Topic, and Service pages.
- DP-13996: Added ability for info details pages to have "pages linking here"
- DP-13887: Updated Intended Audience field placement on News and widget on Organization edit pages.
- DP-14110: Update Mayflower version to 9.8.0.
- DP-1379: Set focus state for elements on Topic pages. #524 (MF)
- DP-14006: Add new "Descriptive links wrapper" organism and associated "Descriptive link" molecule to handle when a brief description and an associated link are needed. #561 (MF)
- DP-11737: Downgrades font size from 800 to 700 for event-teaser and publish-state. #545 (MF)
- DP-13088: Change sticky TOC to appear only if there are 3 or more sections not including related and contacts. #553 (MF)
- DP-13967: Fix the heading size for the Related Services and Additional Resources heading from h3 to h2. #593 (MF)
- DP-12558: Update binder content type so that they show up in linking pages.

### Added

- DP-7810: Added new social media image style to handling metatag images for social sharing.
- DP-11619: “Adds a dashboard to Guide pages”.

### Fixed

- DP-7810: Corrected image used for sharing Service pages by updating image styles and sources used when Service page content metatags are generated.
- DP-14099: Remove an unnecessary relationship from content views to increase performance.
- DP-14098: Add default sort back to My Content view.
- DP-14180: Fixed the relationship and sort in the trash and need content views.

## [0.182.1] - May 30, 2019

### Fixed

- DP-14125: Fixing the blocking of pages that starts /user* and /admin* by removing this rule.

## [0.182.0] - May 28, 2019

### Changed

- DP-13875: Changed the widget for the date and time on the event page.

### Added

- DP-11371: Added a title "More Information" above the Related Services/Additional Resources on the Service pages.
- DP-12433: Scripting of Cloudflare configuration via Terraform
- DP-13394: Added the matatag section "Advanced" back in to allow Robots to be changed on all content types.

### Removed

- DP-13394: Removing unused sub-section within the "Advanced" Metatags section. Robots section should be the only item to appear in Advanced section.

## [0.181.0] - May 24, 2019

### Changed

- DP-10117: Migrate content to core Content Moderation.

## [0.180.1] - May 23, 2019

### Fixed

- DP-13912: Fixed issue that happened to certain nodes when adding the intended audience field.

## [0.180.0] - May 21, 2019

### Changed

- DP-11622: Updated Curated List page dashboard configuration
- DP-13384: Updated configuration to allow additional link types to be set.
- DP-13656: Updated metatag for description for service detail pages.
- DP-13801: Adds another allowed origin for mass_search related to backstopJS' new docker image.
- DP-13994: Updates superset analytics query

### Added

- DP-13903: Add link to the new Platform Point Person dashboard in Kibana and information on how to handle a slow query issue.

### Fixed

- DP-10592: Schema.org data of Event content now always has a location value populated irrespective of whether the event has an existing contact information address or a new unique address.

## [0.179.0] - May 14, 2019

### Added

- DP-13166: Added a csv export to node feedback form.

### Removed

- DP-13841: Removed some modules that were not being used. - XMLSitemap module was removed. - Password Reset Landing Page module was removed. - Restrict By IP module was removed.

### Changed

- DP-12808: Updated Alerts page language and removed the no results language.
- DP-13841: Updated some modules to latest available stable versions. - Clam AV (anti virus) module upgraded from version 1.0.0 to version 1.1.0 - Admin Toolbar module upgraded from version 1.23.0 to version 1.26.0 - Address module upgraded from version 1.0.0 to version 1.6.0 - Search API module upgraded from version 1.11.0 to version 1.13.0

### Fixed

- DP-10576: Added validation for the associated pages field

## [0.178.0] - May 9, 2019

### Security:

- DP-13869: SA-CORE-2019-007 - Update Drupal core from 8.6.15 to 8.6.16 to fix third-party dependencies.

### Fixed

- DP-12515: Fixed config files to include computed_related_to fields.

### Changed

- DP-12515: Added new field to content types and added post update hook to update fields.

## [0.177.0] - May 2, 2019

### Added

- DP-12475: Create a text filter to replace the function to add indentations by heading levels in richtext content in richText.js.

### Fixed

- DP-13189: Fixes listing of all tasks on the node/%/tasks page when a service page has no featured task but does have other tasks.

### Changed

- DP-13723: Updated Mayflower version to 9.6.0.
  - DP-13100: Changed topic card heading styles, and footer CTA styles (MF)
  - DP-12475: Remove richText.js. (MF)
  - DP-12475: Removed js flag classes for richText.js. (MF)

## [0.176.0] - April 30, 2019

### Fixed

- DP-11862: Fix mosaic templates so alt text appears for images/links.

### Changed

- DP-12208: Parts of Service Page twig template are template mapped so that less node data is prepared in preprocess hooks.
- DP-13023: Updated the fetch_urls.js to display an exit code 1 if error out.

## [0.175.0] - April 25, 2019

### Fixed

- DP-13579: Fix missing heading values for 3 column sections in guide page.
- DP-13559: Fix display issue on Service Details page by removing Person content type from list of available cts in contact field dropdown.

## [0.174.0] - April 23, 2019

### Changed

- DP-13335: Updated views to add Author filtering.

## [0.173.0] - April 18, 2019

### Added

- DP-11386: The Related To is now displaying on Advisory, Decisions, News, and Regulations pages.

### Changed

- DP-12496: Template mapping with guide page.
- DP-11386: Made some changes to the Related To by adding Guide page fields to certain pages.

## [0.172.1] - April 17, 2019

### Security

- DP-13509: Drupal security core update 8.6.15
- DP-13509: Update Mayflower version 9.5.1 for the change to jQuery 3.4.0.

### Changed

- DP-13495: Updated Mayflower version to 9.5.0.
  - DP-1323: remove `<b>` tag from footer. (MF)
  - DP-6198: MainNav, Header a11y change keyboard behavior for navigation to be tab based. (MF)
  - DP-6354: Pagination ally fix pagination to use links rather than buttons and access accessibility features. (MF)
  - DP-6358: Change tab order in footer. (MF)

## [0.172.0] - April 9, 2019

### Changed

- DP-12383: Removed preprocessing for Service Detail Pages in favor of template mapping.

### Fixed

- DP-13314: Fixes scrolling on edit pages in Safari.

## [0.171.0] - April 4, 2019

### Changed

- DP-11501: description: Adds the field_organizations field to the dataLayer for use in lieu of entityTaxonomy.

## [0.170.0] - April 2, 2019

### Security

- DP-13080: Drupal core for Mass Gov updated to 8.6.13

### Changed

- DP-13008: Add stale-if-error and stale-while-revalidate to Cache-Control header in setResponseCacheable method.
- DP-13178: Added a patch for the field_group module to fix long field groups display.

## [0.169.0] - March 28, 2019

### Added

- DP-13018: Adds page performance dashboard for Information Details content type

## [0.168.0/0.168.1] - March 26, 2019 --- Non production release

### Added

- DP-12218: Created new Top Priorities View and added it to admin/home page.
- DP-12433: Scripting of Cloudflare configuration via Terraform
- DP-10025: A new enhancement that allows adding Contacts and Related Items to a curated list page on the side.

### Changed

- DP-12406: Add snooze functionality to the dashboard table.

## [0.167.0] - March 21, 2019

### Added

- DP-10485: Added Curated List as a type of content type to the Binder page. The Curated List with show binder navigation at the top and bottom of the page.

### Changed

- DP-12651: Changes to the How to twig template to remove some items from the preprocess and move to the node--how-to-page.html.twig.
- DP-13065: Updated Mayflower version to 9.3.0.
  - DP-8334: Use NPM instead of Bower to pull in front end dependencies (MF)
  - DP-12843: Changes to location page to show link to all locations (MF)
  - DP-12682: Changes to the details.twig to use the class `sidebar sidebar--colored` in sidebar. (MF)
  - DP-12682: Added a block to the tabular-data.twig for template mapping. (MF)

## [0.166.0] - March 19, 2019

### Changed

- DP-12910: Simple sitemap's default baseurl config has been set to "https://www.mass.gov" and a bug has been fixed in our custom module mass_site_map such that now this setting applies to all links in our sitemap output.
- DP-13006: Changed the `ma:sql-cli` to `sql-cli` in the .circleci/nightcrawler/fetch_urls.js. Removed the whole file drush/Commands/ConnectCommands.php as well.

### Added

- DP-12928: Added the following new classes find a service, learn about, and login to the subnav menu within the org page.

## [0.165.0] - March 14, 2019

### Changed

- DP-12911: Updated Mayflower version to 9.2.1.
  - DP-12928: Add link list specific classes `ma__org-nav-i-want-to__findService`, `ma__org-nav-i-want-to__learnAbout`,`ma__org-nav-i-want-to__login` to sections for GTM. (MF)
  - DP-4562: Set focus state for search on mobile menu in mobileNav module (MF)
  - DP-9249: Topic card more links. (MF)
  - DP-9494: Add related orgs/topics to topic page. (MF)

## [0.164.0] - March 12, 2019

### Added

- DP-12949: Added two URLs to the allowed iframe urls domain

### Changed

- DP-12863: Update Drush to fix sitemap generation.

### Fixed

- DP-12924: Allow access to /user on Acquia domains in all non-prod environments.

## [0.163.1] - March 7, 2019

### Changed

- DP-12863: Update Drush 9.6.0-rc4 to fix sitemap generation.

## [0.163.0] - March 5, 2019

### Changed:

- DP-12878: Disabled the default drupal "front page" view; we don't use it.
- DP-12256: Updates mg_title metatag to pull from field_title on media entities.
- DP-10532: Refactors .htaccess for use under Cloudflare.

## [0.162.0] - February 28, 2019

### Fixed:

- DP-12857: Fix the watch-report emails from adding extra characters to the end of each line.

### Changed:

- DP-12860: Only basic meta-tags are shown on node/edit form. This fixes the problem of slow edit pages that authors were experiencing.

## [0.161.0] - February 26, 2019

### Changed:

- DP-10723, DP-10507: binder info details bugs
- DP-12431: Use consistent versions of drupal-container at Circle.
- DP-12794: Change the cURL to wget to download the db-backup for populate and --refresh-db.
- DP-12823: Use `/mnt/tmp` during `wget`.

### Fixed:

- DP-11506: Fixed the diff module with a patch to prevent a possible infinite loop that causes 500 errors for content authors.
- DP-12731: Fixed a 500 error if the node does not have organization assoicated with the node. The feedback module is using field_organization for the feedback form.

### Removed:

- DP-12832: Remove the Home link from the main navigation.

## [0.160.1] - February 20, 2019

### Changed:

- DP-12235: Upgraded various packages due to SA-CORE-2019-003.
  - Drupal core to 8.6.10.
  - Paragraphs module to 8.x-1.6
  - JsonAPI module to 8.x-2.3
  - Metatag module to 8.x-1.8
  - Acquia Connector module to 8.x-1.16
  - Schema Metatag module to 8.x-1.3

### Fixed:

- DP-12235: Serialization bugs affecting schema.org metadata after upgrade

## [0.160.0] - February 19, 2019

### Changed:

- DP-12235: Upgraded Drupal core version to 8.6.9.

## [0.159.0] - February 14, 2019

### Changed:

- DP-11507: Create a text filter for setting up responsive table.
- DP-12700: Updated Mayflower version to 9.0.0.
  - DP-11666 (MF): Replace `<section>` with `<div>` for `.ma__header-search`.
  - DP-11507 (MF): Remove ll.8-29 where add hooks to table richtext.js.

## [0.158.0] - February 12, 2019

### Added:

- DP-6498: A new LoginTrait used for automated testing.
- DP-9732: Adding in a doc that describes how to deploy to a testing environment.
- DP-10921: Feedback manager can now filter by one or more organizations and authors. Filtered feedback manager data can be downloaded as a CSV file.
- DP-12691: Adding a `--http1.0` to the refresh-db and mysql populate command. To prevent failures in downloading the database.

### Changed:

- DP-9146: Style adjustments to boards
- DP-9204: Rearrange the video transcript page to place the transcript at the top of the content area.
- DP-9464: Changed the display of document URLs on the How-To page for the Next steps and Downloads sections.
- DP-11137: Updated stacked layout CT with new Organizations Field. Updated twig to add feedback form to homepage.
- DP-12405: Changed the display of document URLs on the following pages Advisory, Decision, Event, Executive Order, Regulation, and Rules of Court. Removed unused code from the change in the display of doucment URLs.
- DP-12585: Updated config for Acquia Connector module to mitigate security issue as described in https://www.drupal.org/sa-contrib-2019-014.
- DP-12584: Updated Mayflower version to 8.25.0.
  - DP-11135: Create Mayflower for new feedback form - Option 2a (contact link)
  - DP-11301: Feedback integration updates and merging to develop
  - DP-12404: Added a formDownloads block to the following twig templates - court-rules.twig, policy-advisory.twig, & executive-order.twig.
  - DP-12464: Added a block to the action-steps.twig for decorativeLink.
  - DP-9204: Add a label to the video container. Change the reading order to 1. label, 2. transcript link, 3. video for screenreader users.
  - DP-9200: Add labelContext to decorative link.

## [0.157.0] - February 5, 2019

### Changed:

- DP-9200: Set up context info for action finder see all link in service page.
- DP-12452: Updated Mayflower version to 8.22.0.
  |
  DP-12387: Added a block to the steps-ordered.twig and action-step.twig to use a view mode on the Drupal twig. #428

### Fixed:

- DP-12465: description: Content metadata API output is now sorted by `NID` instead of `node CHANGED time` ensuring that no nodes slip through the cracks during offset based paginated fetch.

### Security

- DP-11527: Update to latest Drush 9.6 (9.5.2->9.6.0-rc2) to fix the pm:security.

## [0.156.0] - January 29, 2019

### Changed:

- DP-9162: Create association between the question and its answer options in decision tree page for screenreader. Correct the heading level and adjust its style to maintain the original presentation.

## [0.155.0] - January 24, 2019

### Changed:

- DP-12321: Updated Mayflower version to 8.20.0 (includes MF 8.19.1).
  - DP-11662(MF): Replace markup validation flagged elements `<section>` with `<div>` for `.ma__utility-nav` and `.ma__utility-panel`.
  - DP-11668(MF): Change to a valid container for `.ma__main-nav`.
  - DP-12120(MF): Hide feedback wrapper in print.
  - DP-12234(MF): Fix spacing issue after conditional content for phone within the contact item.

## [0.154.0] - January 22, 2019

### Changed:

- DP-10265: Changed the Google Map display on the location pages only. To use the Google Static Map with a map image and link text.
- DP-11453: Modified meta data for mg_organization that removes the hyphens from the organization name.

## [0.153.0] - January 17, 2019

### Changed:

- DP-12223: Updated Mayflower version to 8.19.0.
  - DP-11437(MF): MF Location search by city/zip fix for autocomplete issue.

## [0.152.0] - January 15, 2019

### Fixed

- DP-12175: Fixed a bug that causes behat test to fail vulnerabilities test.

### Changed

- DP-10479: Update the org_page config to add additional descendant children.
- DP-12055: Update Drush TFA integration

### Security

- DP-11721: Security update for the jsonapi contrib module.

## [0.151.0] - January 10, 2019

### Fixed:

- DP-11731: Fixes BackStop from displaying blank pages by changing the wait time from 300 to 2000.

### Changed:

- DP-12009: Updates Mayflower version to 8.18.0.
  - DP-9183(MF): Expand button on alert.
  - DP-9775(MF): Mayflower adjust print styles for topic and org pages to have less space at top.
  - DP-9186(MF): Change section tags to div tags on rich text pattern for better semantics.
  - DP-11400(MF): Add more spacing on org page above "More about [name]".
  - DP-5230(MF): Fix print styles - how-to left alignment.
  - DP-5232(MF): Fix print styles - remove feedback button.
  - DP-9185(MF): Mayflower accessibility: Use proper html element for semantics.

## [0.150.0] - December 20, 2018

### Fixed:

- DP-11452
  - Bugs and discrepancies fixed in descendant manager.
  - Descendant UI page by default shows a nested tree view of parent and children.
  - Descendant UI page shows a flat list of all descendants if parameter `flat=yes` is passed in the URL.
  - Content metadata rest API output by default outputs a flat list of descendants for each node.
  - Content metadata rest API can output nested tree of descendant per node if parameter `flat=no` is passed in the URL.
  - Traversal depth (to fetch children) for both descendant UI page output and content metadata rest API output, can be controlled by a `depth=N` parameter, where N is any integer value between 1 and MAX_DEPTH.
  - In case of circular references duplicate items are not fetched repeatedly.

### Changed:

- DP-11708: Updated Mayflower version to 8.16.0.
  - DP-10231(MF): A bug fix for the location pagination.
  - DP-5859(MF): Checkboxes missing from "What You Need" section on print style for How-To pages.

## [0.149.0] - December 18, 2018

### Added:

- DP-11578: Add Google Optimize snippet.

## [0.148.0] - December 13, 2018

### Changed:

- DP-10487: Implement header and spacing reductions for Drupal specific styles.
- DP-11579: Updated Mayflower version to 8.15.2:
  - DP-9727: Header and spacing size reductions (MF).
  - DP-10447: Pages show excess scroll space to right and bottom in IE11 (MF).

## [0.147.1] - December 7, 2018

### Fixed

- Contact dropdown on organization pages did not display correctly after Drupal upgraded from mayflower 8.10.0 to 8.14.0, because drupal had a template override that was still referring to the old contact subnav twig. This has now been fixed.

## [0.147.0] - December 6, 2018

### Added

- DP-9265: Added documentation explaining how descendant manager works.

### Changed

- DP-11415: Updated Mayflower version to 8.14.0 that brings the below changes over to Mass.Gov
  - DP-10265: Added a block for the map to the location-banner.twig. (MF)
  - DP-10264: Support Google static maps for locations. (MF)
  - DP-11102 and DP-11026: responsive tables obscure stickTOC and final table row. (MF)
  - DP-11030: Create a clearer distinction between contact and related in service. (MF)
  - DP-9883: Updated the styles of the contact groups in the contact row to display the same number of groups in each column under the "Contact Us" section of the org page. (MF)

## [0.146.0] - December 4, 2018

### Added

- DP-10921: Along with the node data that it used to expose Mass Content API now also includes "offered_by_organizations" values.

## [0.145.1] - November 15, 2018

### Security

- DP-11116: Update the Paragraphs module to 8.x-1.5 for security vulnerabilities within the contrib module. Updated the entity_reference_revisions module to 1.6.0 this was recommend by the change to the Paragraphs module in 8.x-1.3.

### Added

DP-10469/DP-10470: Create queue worker to fetch node page views and grade data, and update admin content views to include 'grade' and 'page views' sortable columns.

### Changed

- DP-10380: Page specific alert validation.

## [0.144.0] - November 8, 2018

### Changed

- DP-10490: Update route iframes to use a simplified configuration for dashboards.

### Fixed

- DP-10615: Fixed formatting of fee values such that we no more show two dollar symbols or redundant decimal places.
- DP-11051: Fixed issue where upgrade from workbench_moderation 1.2 to 1.4 prevented successful scheduled publishing of content.

### Removed

- DP-11162: Removed the Redirect creators and Executive Orders permissions from the Tester Role.

## [0.143.0] - November 1, 2018

### Added

- DP-10462: Implement sticky TOC for multiple content types.

### Changed

- DP-10911: To allow content authors the ability to publish and unpublish documents in the all documents view.
- DP-11058: Update video position on the service pages
- DP-11067: Updated Mayflower version to 8.10.0.
  - DP-10793: Update video layout on service pages (MF)
  - DP-10774: Implements sticky table of contents on Advisory, Decision, Executive Order, Regulation, and Rules of Court content types.
    It also updated the integration of the sticky table of contents for Information Details, Curated Lists, and Guide pages. (MF)

## [0.142.0] - October 30, 2018

### Changed

- DP-5899: Adds a migration "slot" for Gov Community Compact documents. Upon deployment, migration is then initiated to import data from "document-list-dp-5899.csv".

## [0.141.0] - October 23, 2018

### Changed

- GH-2834: Fix 404 error in ahoy uli

### Fixed

- DP-9391: Remove conditional field rule for location type and icons to show icon on location listings.
- DP-10914: Fixes the curated list directory to stop showing TOC displaying additional sections.
- DP-10899: Fixes xss behat test failure by checking for the existence of "children" key in \$video_render_array before calling array_key_exists()
- DP-10876: Update the workbench moderation module to 8.x-1.4

### Security

- DP-10875: Updated Drupal core to 8.6.2.

## [0.140.0] - October 18, 2018

### Added

- DP-8019: Added sticky TOC to guide pages.

### Changed

- DP-10257: Updated Drupal core to 8.6.1.
- DP-10838: Updated Mayflower version to 8.7.0.
  - DP-10025: Update sticky TOC selector to target new curated list structure. (MF)
  - DP-10013: Offered by relationship indicator row changes Medium priority. (MF)
  - DP-10274: New feedback button obscures navigation elements on mobile. (MF)
  - DP-10025: Update sticky TOC selector to target new curated list structure. (MF)
  - DP-10013: Offered by relationship indicator row changes Medium priority. (MF)
  - DP-10274: New feedback button obscures navigation elements on mobile. (MF)
  - DP-8725: Applies responsive functionality to tables in rich text. (MF)
  - DP-7916: Updated header alert CSS to fix underline issues with multi-line sentences. (MF)
  - DP-8019: Update sticky TOC to only target h2 elements. (MF)
  - DP-10756: Modify display of relationship indicators on mobile. (MF)

### Fixed

- DP-10843/DP-8136: Fixed bug that caused scheduled content to get published ahead of time.
- GH-2834: Fix 404 error in ahoy uli.
- DP-10883: Add a numeric requirement to the argument for curated_list_news view to stop the node/add/news page from breaking.

## [0.139.0] - October 16, 2018

### Added

- DP-10611: Added a corresponding Media entity title to each file entry on the sitemap.

## [0.138.0] - October 11, 2018

### Added

- DP-8136: Add support for Media publish/unpublish scheduling to scheduler module.
- DP-10459: Fixed the icon display for the How To methods section. The icons for online, by fax, and in person where not displaying.

### Changed

- DP-10630: Adds line to ignore the new media entity url for documents in robots.txt.

## [0.137.0] - October 9, 2018

### Added

- DP-10112: New mass_admin_metatag feature for behat testing.

### Changed

- DP-8403: Changed handling of current and past events to work more like the same on Org page.
- DP-10305: Moves the vertical tabs into a sidebar region of the add/edit document forms.
- DP-10305: Changes the color of the save button on the add/edit document forms to darker blue.
  Note that the original PR for this had a commit that ended up getting reverted in a hot fix. The fix for what was reverted
  DP-10458

### Fixed

- DP-10450: Fixes an issue where the new work was preventing file uploads from working correctly on document forms.
- DP-10359: Adds patch to inline_entity_form to fix disappearing field labels when creating a new inline entity.

## [0.136.0] - October 4, 2018

### Added

- DP-10263: Adds views_data_export module

### Changed

- DP-10263: Updates admin/content view to attach data export view.
- DP-10591: For location content type, title of related locations page has been changed to the format "Other locations related to FOO_BAR". It was previously of the format "FOO_BAR Locations".

## [0.135.0] - October 2, 2018

### Fixed

- DP-10448: Fix issue with "Log in to..." links not displaying on org nav.
- DP-10498: Fixes an issue for pages with have content in the main content region that require a rule to display at full page width.

### Added

- DP-10385: Adds logic to remove non-numeric symbols from field_fee_fee.

### Changed

- DP-4855: Change view button text from "apply" to "filter".
- DP-8824: In the How-to content type, set the fee field to use a new form display mode; in the fee content type, configure the new form display to limit the fields that appear in the fee inline entity form widget.
- DP-9301: Add node title and id to the "contact the author" form.
- DP-10411: Show document title field value instead of filename at top of document edit form.
- DP-10534: Updated Mayflower version to 8.3.0.
  - DP-10238: MF Modify map pins to include link to location and directions. (MF)
  - DP-10514: Add primary `.ma__button-search` and secondary `.ma__button-search--secondar` usage themes to button search. (MF)
  - DP-10514: Fix and unify button search hover styles in organization-navigation search. (MF)
    issue: 10534

### Updated

- DP-10320: Modifies map pins to include link to location and directions.

### Notes

The following items are excluded from this release because of the rendering issues caused by them:

- DP-10025: Fixes issue with narrow widths on curated list pages.
- DP-10526: Update all tasks view to properly check for empty fields and handle them.

## [0.134.1] - September 28, 2018

### Fixed

- DP-10513: See all links on service page does not show all the pages it should.

## [0.134.0] - September 25, 2018

### Fixed

- DP-10227: Fix featured services are not showing on service "see all" pages.
- DP-10381: Gzip JSONAPI responses.
- DP-10382: Fix featured services are not showing on service "see all" pages.
- DP-10409: Fixes page layout issues caused by flex being too flexible. cf DP-10369.
- DP-10467: Re-adds permission to edit Updates block for Content Admins.

### Added

- DP-6652: Update OG description meta tag to map news description.
- DP-10410: Updated Mayflower version to 8.0.1.

### Changed

- DP-10409: The breakpoint from wide to tablet for the admin page template is now 1120px.
- DP-10409: The request support button on the author homepage is now 45% width on mobile/tablet and 100% on wide.

## [0.133.1] - September 21, 2018

### Fixed

- Revert for DP-10305: To allow users to upload files in the add document. Moves the vertical tabs into a sidebar region of the add/edit document forms.

## [0.133.0] - September 20, 2018

### Fixed

- DP-5419: Fix spacing on service-details below section headers and between sections.
- DP-9337: Remove hiding of scroll overflow on IE/old versions of Edge that was preventing users from seeing scrollbars.

### Added

- DP-10389: Adds a new custom updates block to the right sidebar of the author welcome page for the Customer Success team.

### Changed

- DP-10305: Moves the vertical tabs into a sidebar region of the add/edit document forms.
- DP-10305: Changes the color of the save button on the add/edit document forms to darker blue.

### Removed

- DP-6971: Remove core patch added to unblock deletion of files.

## [0.132.0] - September 18, 2018

Release 0.132.0 includes the Release 0.131.0 items. (Release 0.131.0 was canceled and not deployed on September 13.)

### Added

- DP-9513: Adds responsive image template file for field_event_image.

### Changed

- DP-9513: Changed field_event_image to display responsive images.

### Fixed

- DP-9791: Add jquery to restore missing paragraph button text in alert edit pages.
- DP-10369: Fixes an issue where the width of the main content area of the author homepage was being affected by the release notes content.

### Removed

- DP-9497: Remove Better Field Descriptions module

## [0.131.0] - September 13, 2018

### Added

- DP-9537: Added integration to MF organization navigation component in org_page template.
- DP-9790: Added integration to MF organization navigation component in org_page template.
- DP-10216: Duplicated related links to How To at bottom of page.

### Changed

- DP-8132: Changes config for alerts so rabbit hole settings can't be overridden for individual entities and promotion options are hidden.
- DP-9270: update help and button text in the Insert Media Entity Download Link browser to make it clear that users can only choose one file.
- DP-9980: Change default moderation state for fee to published.
- DP-10163: Configure autocomplete field_links_downloads_link in "Links and Downloads" paragraph to not display contact_information, fee, legacy_redirects.
- DP-10297: Updated Mayflower version to 7.1.0.
  - DP-5917: Updates to truncate long pagination displays with ellipsis. (MF)
  - DP-9912: Replicated related links to the bottom of the page along with changes style to the heading. (MF)
  - DP-10287: Adds block to page-overview twig file for responsive images. (MF)
  - DP-9337: Implement Organization Page navigation menu (MF)
  - DP-5955: Fixes top alignment of the "Related Services" and "Additional Resources" split columns block on tablet view mode. (MF)

## [0.130.1] - September 12, 2018

### Fixed

- DP-10312: Fix the subtype title to display on News content types.

## [0.130.0] - September 11, 2018

### Added

- DP-9885: Fail on notices during Behat tests
- DP-9921: Every content page now has a feedback tab visible to authors where they can see all the feedback that constituents shared for that particular content.

### Changed

- DP-10302: Change Views edit form save button to Bay blue.
- DP-10039: replace old medium.com links with new massgovdigital.gitbook.io links in author-facing pages.

### Fixed

- DP-10283: The getTimestamp() was null causing a 500 error on News, Curate List, and Regulation pages. DP-7763 introduced a bug when adding getTimestamp() to News pages.

## [0.129.0] - September 6, 2018

### Added

- DP-9135: Adds API endpoint for nav links eg. /api/v1/nav/main.
- DP-9182: adds a new custom homepage for authors that includes
  - a latest release notes block,
  - a view block called my recent content that is a truncated version of the full My content view page,
  - a configurable help and support block containing a large text field and a link text/URL field combo,
  - and an uneditable block containing an absolute link that takes you to an external site to report an issue.
- DP-9182: adds a new (optional) right sidebar region has been added to the mass_admin_theme to accommodate the page layout.
- DP-10179: Adds a post-update hook to save all unpublished documents that have public files so the files become private.

### Changed

- DP-9136: Exposes the three footer menus to be viewed by any role. This allows their corresponding jsonapi request to also be viewed by any role.
- DP-9182: changes to the Content (dashboard) menu that removes two views links and puts the links in the order requested by Customer Success.
- DP-9182: changes to the admin menu that adds a set of 'edit block' links in the admin menu from which you can access the Help and Support and Node feedback block forms.
- DP-9792: Hide the preview button within the edit form for a node which is unsaved.

### Fixed

- DP-10239: Add jquery to restore paragraph button text in curated_list pages.
- DP-10268: Fixed the display of social media links on the organization page when using a "General Organization" subtype.
- DP-10276: Fixed the org page custom validation to remove the background image constraint from services offered section.

### Removed

-DP-9510: Disables old My work view.

## [0.128.0] - September 4, 2018

### Fixed

- DP-7763: Fixed wrong dates for news release teasers on org pages.

### Changed

- DP-9535: Configure autocomplete internal link fields to include Person pages.

## [0.127.1] - August 31, 2018

### Fixed

- DP-10252: Fix generic file icon being used instead of correct icon in many places.

## [0.127.0] - August 30, 2018

### Changed

- DP-10019: Updated footer template to use new feedback button.
- DP-10101: swapped displayed organization field for Documents media landing page and "All Documents" admin listing
- DP-10101: swapped required Organization field for Documents media, hides the old field in the add/edit form.
- DP-10111: To prevent the information detail iframe paragraph type from allowing any URLs to be inserted. Also made changes to the overall validation from each content type to the iframe paragraph type to use the allowed domain URLs only.
- DP-10114: Run cache rebuild _after_ full deployment, but remove it after database operations.
- DP-10150: Add type for downloadable .mpp files to .htaccess.
- DP-10183: Change 'User' to 'Account' on the user admin tab (again).
- DP-10207: Update Mayflower to 7.0.0.
- DP-10207: Update Mayflower to 7.0.1. For a hotfix to the feedback button.
- DP-10211: Bump Composer declared PHP version to match what we actually use to fix IDE auto completion.
- DP-6871: Removed paragraph title from single listed curated lists.
- DP-7322: adds link type field to Alerts, and conditional logic to hide and show the "link" field and "detail page content" fields to aid in the publishing experience.
- DP-7954: Updates mass_alerts module to use core email validation, instead of a custom drush command.

### Added

- DP-10101: adds parent-org logic into the xml site map generation
- DP-9745: Custom metatags that show only authenticated users and help analytic tools capture product's users and their organizations.
- DP-9651: Updates pages to display relationship indicators in the form of an "Offered by" and "Related to" section at the top of most pages.

## [0.126.0] - August 28, 2018

### Changed

- DP-8356: Added data check to make sure Category was set before sending the heading to the pattern.
- DP-9132: changes the date field used to sort Binders from "Last updated" to "Date published", so Binders appear in the correct order in Curated Lists. Sets the default for "Date published" to be the current date.
- DP-10029: Updated location listings integration to use new MF designs.
- DP-10078: Removes deprecated sidebar quick actions.
- DP-10107: Update class for button in theming templates to match Mayflower
- DP-10113: Updated Mayflower version to 6.3.0.
  - DP-9486: Make location filter persist on location listing page. (MF)
  - DP-9486: Fixed the location listing autocomplete search filter. (MF)
  - DP-9522: Updates the Locaton Listing component to updated designs (MF)
  - DP-10107: Make current button "small" the default button size and add "large" and "small" variations. (MF)

### Added

- DP-9705: Adds `ma-files` to backup the `files` folder to an S3 bucket.
- DP-10170: Adds category metatag to location content type
- DP-10171: Adds category metatag of value "events" to the event content type.

## [0.125.0] - August 23, 2018

### Fixed

- DP-10003: Fix code warnings in Rules of Court pages.
- DP-10007: Fix code errors in Curated List.
- DP-10034: Deployment id now shows on the backend admin theme too
- DP-10135: Restores the patch to scheduler that fixes the feature for scheduling nodes to be unpublished.
- DP-10001: Fix Section Landing and Topic Page Notices.

### Removed

- DP-9352: Removed mg_associated_organization metatag.

### Changed

- DP-9352
  - Updates Event content type metatag to include start date, end date and directions url.
  - Updated output of MassMetatagDirectionsUrl to account for event address with logic on address type.
  - Moved mg_location to mg_address and added logic for address type.
- DP-9354: Updates location content type metatags adding mg_contact_details and mg_date.

### Added:

- DP-9343: Adds computed fields to descendant manager config.
- DP-9352
  - Adds Start Date metatag class.
  - Adds End Date metatag class.

## [0.124.0] - August 21, 2018

### Changed

- DP-8037: Add cacheable metadata to moderation state block.
- DP-8299: adds a new view, Legacy redirects, to the author content menu; removes Legacy redirect pages from the All Content, My Content, Needs Review, and My Work views.
- DP-9844: Change config of paragraph form display on Curated Lists to use "open".
- DP-9984: Updated template to use stacked layout when show_image variable is true.
- DP-9988: Updated organizations metatag token to include all organization ancestors.
- DP-10079: Change the "Drupal Page" field to allow Person content type in Legacy Redirects.

### Added

- DP-9401: Adds mg_type meta tag to Document media XML site map links

## [0.123.1] - August 20, 2018

### Changed

- DP-10102: Update Mayflower version to 6.2.3.

### Fixed

- DP-10102: Fix the info details TOC and sticky nav which was broken.

## [0.123.0] - August 16, 2018

### Fixed

- DP-10004: Fix code warnings in News type.
- DP-10005: Fix code warnings in Location Details.

### Changed

- DP-9184: Transfers data on Document bundles from taxonomy term to new organization field.
- DP-9400: Adds document category to sitemap.
- DP-9824: Add type for downloadable .prx files to .htaccess.
- DP-9842: Updated data being passed into the mapped-locations pattern to use new field values.
- DP-10075: Update Mayflower to v6.2.0.

### Added

- DP-9403: Adds organization metatag to sitemap for files.
- DP-9403: Adds service to mass_metatag for sluggifying organization titles.
- DP-9842: Added 2 new fields for the location details button label and short description.

## [0.122.0] - August 14, 2018

### Fixed:

- DP-6955: Updated permissions so “author”, “editor” and “content_team” can access video browser pages to add existing videos.
- DP-9978: Fix curated list caching issue where updating the description source for a list item would not propagate immediately.
- DP-10022: Fixes DescendantManager static cache pollution causing Org Page metadata test failure introduced by Decision Tree depth change.

### Changed:

- DP-9553: Change “rabbit_hole” settings for all taxonomy pages to display page not found.
- DP-9730: Enable new file cleanup cron.
- DP-9862: Add Seal Image url in theme to support removal of URL variables in Mayflower.
- DP-9979: Enable Vimeo support on the Video media bundle.
- DP-10022: Removes static cache from DescendantManager after the current implementation was proven ineffective in profiling.

### Added

- DP-9044: Add maximize button to “ckeditor” toolbar for basic and full html.
- DP-9333: Adds category term field to document media type.
- DP-9335: Adds new "Announcement Type" and "Document Other Type" vocabularies installs terms in them, and adds the "Type" field to Document media.
- DP-9899: Added Organization parent field to allow Sub Organizations to have parent organizations.

## [0.121.1] - August 10, 2018

### Fixed

- DP-9734: Set a max depth for the decision tree manager when it fetches children from descendant manager.

## [0.121.0] - August 9, 2018

### Added

- DP-6274: Image credits was added to News content type.
- DP-9334: Adds document_category vocabulary to configuration and uses a hook_update to insert requested terms into vocabulary

### Changed

- DP-6881: Add conditional_fields settings for News nodes so the "Sections" field is not visible on the node add/edit screen except when the field_news_type is "news".
- DP-9869: Updated service finder section design and language for all org page variations.
- DP-9871: Update the version of Mayflower to 6.0.1.
- DP-9871: A fix on pagination, replace state instead of push state to avoid requiring additional back button clicks to get to the previous page. (MF)
- DP-9983: Update to Mayflower 6.1.0.
- DP-9839: Updated styling and language of service finder section for general and boards organizations to match that of elected officials (MF).
- DP-9993: Update the action finder text for headings and links

### Fixed

- DP-9769: Fixed a 500 error displaying on the Location detail page from the \$section_title
- DP-9879: Fixes fatal errors on nightly serialization run.

## [0.120.1] - August 3, 2018

### Fixed

- DP-9874: Fix the issue with saving person nodes by setting organizations field on presave and not requiring it in Drupal.
- DP-9873: Fix duplicate/erroneous content appearing on needs review page.

## [0.120.0] - August 2, 2018

### Changed

- DP-9711: Synchronize the organizations field to service offered by and person organization field.
- DP-9859: Update the version of Mayflower to 5.34.0.
- DP-9867: Update the version of Mayflower to 5.34.1.
  - DP-9867: Fix the 404 display on the "See all" page when go back to the page. (MF)

## [0.119.1] - August 1, 2018

### Security

- DP-9843 - Drupal Security update DRUPAL-PSA-2018-07-30

## [0.119.0] - July 31, 2018

### Fixed

- DP-5567: Fixes AJAX-related Views errors via Drupal core patch
- DP-8568: Hide upcoming events link from past events page if there are no upcoming events.
- DP-9793: Patch drush to fix return code for update hooks that fail without throwing an exception.

### Added

- DP-8991: Adds a drush script that reports the likely cause of each 404 page that gets shown to a customer.

### Changed

- DP-9641: Change tool tip text from "Download Link" to "Insert Link to Document" in ckeditor toolbar.
- DP-9768: Purge aliases from Varnish as they are created/updated.

### Removed

- DP-9738: Uninstall the XML Sitemap module.
- DP-9793: Remove unused workbench_tabs module from composer dependencies.

## [0.118.0] - July 26, 2018

### Added

- DP-9766: Adds views mini-pager template to mayflower-ize mini pagers where they were appearing visually broken previously.
- DP-8177: Adds a new curated list type that supports people and contact information.
- DP-9368: Adds the Document Bundle (from the Media Entity) to the sitemap.
- DP-9332: Adds support for adding the PageMap attribute to Document (Media Entity) entities in the sitemap. Added mg_date attribute to documents in the sitemap.

### Changed

- DP-8381: Updates "complex" date/time field to override both date and time when displaying Event nodes
  DP-9518:
- Allow uploading or browsing/inserting only images, not documents. This button should only be used to insert images.
- Change title/tool tip to say “Insert Image”
- Change the button's icon from the "E" to use a standard "image" icon.
- Change the location where uploaded images go to an /images/YEAR/MONTH directory.
- Adjust entity listing view to only show files with image extensions.
- Enable auto select to bypass extra steps in the embed process that were just adding confusion.
- Add help text to each entity browser widget pointing out that only one image at a time can be embedded.
- Other minor config changes, e.g. reduce the number of query results the entity browser file view displays to 12.
- DP-9738: Patch XML Sitemap so it doesn't delete the files directory.
- DP-9515: Add required asterisk to Related tab in Events node edit form.
- DP-9746: Mayflower updated to version 5.33.0

### Fixed

- DP-9742: Fix scroll hijacking when adding a paragraph as the second ajax action on a page.

## [0.117.0] - July 24, 2018

### Fixed:

- DP-9090: Allows Our Organizations section to be added to Org page for any Subtype selection.
- DP-9479: Updated the Private files download permission (pfdp) module to version 8.x-2.0.
- DP-9560: Fixes revision-related data in Mass Dashboard views (My Content, Content, Watched Content, Trash)
- DP-9741: Fixed "Current Moderation State" appearing on pages that do not have a moderation state (eg: media pages)
- DP-9741: Resolve php notice when viewing media entity: "Trying to get property of non-object ..."

### Added:

- DP-9226: Add feedback form to 'more' and location listing pages.
- DP-9654: Update executive order issuer field to only allow organizations.

## [0.116.0] - July 19, 2018

### Fixed:

- DP-9694: Fix organization filter breaking editor views when navigating to the next page.

### Added

- DP-8655:
  - Adds tools to clean Drupal's `file_managed` table so that files can be deleted safely and with confidence.
  - Adds `drush` command to check real file usage.
  - Provides reports of extra and missing files on the Mass.gov file system.

### Changed

- DP-9585: Descendant Manager changes:
  - Remove second level indexing for faster and simpler descendant calculation.
  - Rename methods for easier understandability.
  - Separate DescendantManager storage and relation extraction to allow for unit testing.
  - Refactor DescendantManager storage.
  - Index unpublished nodes, but exclude them from results.
  - Remove unused DescendantNodes block.
  - Replace custom cron system with standard cron queue.
  - Add type and return hints to DescendantManager classes to enforce data standards and guarantee interoperability.
  - Remove unused DescendantNodes block.
  - Reduce the max levels for the Descendant manager(from 20 to 6).
- DP-9650: Updated Mayflower version to 5.32.0.
  - DP-5329: Adds browser history enabled pagination for listing pages. (MF)

## [0.115.0] - July 17, 2018

### Added

- DP-8462: Adds the mg_organization meta tag to the content types:
  Curated List, Decision Tree, Event, Form Page, Guide Page, Information Details,
  Location, Location Details Page, Organization Landing Page, and Topic page.
- DP-9437: Add test cases for org API with complete item data
- DP-9561: Adds drush command "ma:ping-google-sitemap" to ping Google with the current sitemap.

### Fixed

- DP-9557: On service details page analytics, user satisfaction tab, link to correct dashboard in iframe.

### Changed

- DP-8462: Updates the mg_organization meta tag for content types: Advisory,
  Binder, Decision, Executive Order Page, How-to Page, News, Regulation Page,
  Rules of court, Service Detail Page, and Service Page.
- DP-9331: Switched the site over to using the Simple Sitemap module from the XMLSitemap module.
- DP-9554: Updates robots.txt to disallow default taxonomy term and node paths

### POST DEPLOY STEPS

- DP-9331: Run "drush ssg". This will generate the new sitemap using Simple Sitemap.

## [0.114.1] - July 13, 2018

### Fixed

- DP-9565: Fixed duplicate content showing up in admin views with the new organization filter.
- DP-9565: Fix organization nodes not showing up in admin views when filtering by organization.

## [0.114.0] - July 12, 2018

### Added

- DP-4613: Adds focal point to the image on the guide page
- DP-9112: Add field organizations to all page types except organization and topic and populate the field.

### Fixed

- DP-9475: Update board org page template to fix issue with missing social links.
- DP-9530: Content API descendants needs static variable level decremented on return.

### Changed

- DP-9237: Moves the functionality from mass_dashboard into mass_admin_toolbar.
- DP-9484: Updated Mayflower version to 5.31.0:
  - DP-9435: Adds text-wrapping on header alert messages to fix bug on mobile devices (MF)
  - DP-8177: Adds a selector to the sticky TOC JavaScript to target curated lists (MF)
- DP-9496: Add type for downloadable AutoCAD files to .htaccess.

### POST DEPLOY STEPS

1. A drush command needs to execute to populate the new organizations field.
2. Run 'drush ma:populate-organizations 100'
3. This could run for over an hour.

## [0.113.0] - July 10, 2018

### Added

- DP-8999: /api/v1/orgs/detail returns the org listing with contentInfo added.
- DP-9269: Add test case for news API with complete news item data.
- DP-9408: Add update to allow page-level alerts on more content types.

### Fixed

- DP-9234: Clear caches after deploying route_iframes config changes to fix fatal errors on analytics tabs.
- DP-9422: Fixed a 500 error appearing when download link has been deleted from the system.
- DP-9425: Adds a condition to check if body field is populated before printing out the "About Us" section on board org pages.
- DP-9429: Resolve warning for missing "media_entity_document" module by removing references to its schema.

### Changed

- DP-7155: Configure autocomplete internal link fields to not display contact_information, fee, legacy_redirects, and person pages.
- DP-9517: Change 'User' to 'Account' on the user admin tab.

## [0.112.0] - July 5, 2018 --- Non production release

### Added

- DP-4709: Add the function, mass_utility_update_8023(), to remove the taxonomy terms for Document Agencies.
- DP-9342: Adds a new My content view to be used on the new author homepage.
  Changed:
- DP-9342: Refactors author facing views to have the same field and filter labels, and to have them be in the same order.

### Fixed

- DP-9421: Fixed a 500 error when clicking the preview button on the edit form for the info detail pages.
- DP-9365: When clicking on Analytics in Edit, user experiences denied permissions
- DP-9364: Analytics tabs disappear from Edit page under certain conditions.
- DP-9445: Resolve potential race condition with Memcache for large (>1mb) items.

### Changed

- DP-9315: adds new moderation state message to watch email when a new draft is added to a published page.
- DP-9430: Adds backend_url, request_id, and drupal_uid to New Relic transactions.
- DP-9234: Configures route_iframes to show the analytics dashboards we will have at launch of the feature.
- DP-9234: Grants authors and editors permissions to see the analytics dashboards.

### Removed

- DP-4709: Remove the vocabulary, Document Agencies, and its associated field, field_contributing_agency, from Media Document.

## [0.111.0] - June 28, 2018

### Added

- DP-8510: Added relationship indicators back to referencing service pages for guide page, info details, form page, and curated list nodes.

### Fixed

- DP-9380: Fix event nodes displaying link to related events even when there are no events to view.
- DP-9380: Fix event pages displaying link to past/upcoming events even when there are no events to view.
- DP-9380: Removed events view in favor of a controller based on the EventManager.
- DP-9423: Adds limit to linkit autocomplete queries to prevent them from causing memory limit errors.
- DP-9427: Prevent JS error when blocking form submission due to in-flight ajax.

### Changed

- DP-9173: Adds 'info_details' and 'binder' nodes to allowed entities in featured item mosaic autocomplete link field.

### Removed

- DP-8951: Removed a funnel endpoint field from the stacked layout.

## [0.110.1] - June 28, 2018

### Fixed

- DP-9427: Prevent JS error when blocking form submission due to in-flight ajax.

## [0.110.0] - June 26, 2018

### Added

- DP-7702: Option for events to not have an address.
- DP-8899: Allow the subscript tag to be used in WYSIWYG editors.
- DP-9348: Add media_entity_actions module to support bulk actions on media.

### Changed

- DP-8804: Updates Google Maps API to the latest stable version.
- DP-9341: Adds a new permission 'view pages linking here' and assigns it to the author, editor, content editor, and developer roles; removes the 'view descendant api test pages' permission from the author role.
- DP-9348: Converts from contrib media_entity module to core media module.
- DP-9348: Update video_embed_field, media_entity_download to support Media in core.
- DP-9353: removes the "Set Content as Archived" choice from the Action dropdown on the My Work, Needs Review, and All Content view.

### Fixed

- DP-8615/9227: Refactors add-more-paragraph-scroll.js to correctly scroll to new paragraphs on node edit forms.

### Security

- DP-9329: Disallow uploads of html files to documents due to security concerns. HTML files could be used in XSS or Cross-Site Content hijacking attacks.

### Post Deploy Steps

- DP-7702, DP-8899: Revert configuration (drush cim -y).

## [0.109.1] - June 25, 2018

### Fixed:

- DP-9395: Removes page content placeholder section from person pages.

## [0.109.0] - June 21, 2018

### Added

- DP-8631: Updated the org_page content type with "Boards" subtype and boards fields. Also integrates templates with the Mayflower page component.
- DP-9317: Add a link to the Report abuse or request urgent assistance page to the feedback module.
- DP-9317: Add a character countdown feature to the textbox corresponding to the radio button option "yes" in the feedback module.

### Changed

- DP-8611: Updates patch being used on core Link module to check for allowed content types during URI validation on internal links.
- DP-9317: Change the language of the disclaimer for the feedback module.
- DP-9350: Updated Mayflower version to 5.29.0.

### Removed

- DP-9321: Removed the old emergency alert(node_emergency_alerts) from the mass_theme.theme.
- DP-9322: Removed the storage fields for non used fields.

### Fixed

- DP-9364: Patch route_iframes to fix analytics tab disappearing when route iframes configuration changes.

### Security

- DP-9363: Patch route_iframes to fix access bypass on /node/{node}/analytics.

## [0.108.0] - June 19, 2018

## Changed

- DP-9056: Update editor perms to allow for administer sitemap and rabbit hole.
- DP-9088: Update Field Group module to fix undefined index warnings appearing on entity forms that have field groups.
- DP-9231: Removes insecure hash salt override on Acquia.
- DP-9300: Add ma:release command for running deployments via CircleCI.
- DP-7830: Allow automated curated lists to be sorted in reverse alphanumeric order.

## Fixed

- DP-9264: Fix styling for disabled buttons in mass_admin_theme.

## Removed

- DP-9066: Removes the obsolete fields, field_org_more_news_link, field_ref_actions_3 and field_ref_contact_info, from organization content type and their associated methods, pre-process and validations.
- DP-9068: Remove the obsolete fields, field_action_downloads, field_action_related, field_contact_group, field_funnel_endpoint_term, field_type_term and field_action_parent, in Right Rail content type.
- DP-9071: Remove the obsolete fields, field_topic_parent, field_type_term and field_action_parent, in Stacked Layout content type.

## [0.107.0] - June 14, 2018

## Added

- DP-7206: Added a config backport for permission which allow Authors and Editors to view linking pages.

## Changed

- DP-8135: Updated the heading fields on the agenda and minutes section paragraphs to be optional.
- DP-9311: Updated Mayflower version to 5.28.0.

## Fixed

- DP-9263: Update css and tour configs to fix placement of tour tips in mass_admin_theme.

## Removed

- Removed vagrant from the Mass repository.

## [0.106.0] - June 12, 2018

### Added

- DP-8156: Adds sitewide alerts javascript test.
- DP-8156: Adds plumbing for javascript testing using ChromeDriver.
- DP-8192: Adds new Linkit button to CKEditor to allow for autocompleted inline links to internal content in RTE fields.
- DP-8968: Created config to use Drupal's migrate api to automatically migrate 1200+ DOT Documents, with desired labels and fields.
- DP-8985: Add timestamps to start and end of ma:deploy for easier debugging.

### Removed

- DP-8379: Uninstall unused modules: Block Content, Automated Cron, Restrict by IP, Docson, Schemata.
- DP-8887: Removed deprecated twig templates (blocks, views, includes, forms, and fields).
- DP-8975: Removed views that are no longer used or needed.

### Changed

- DP-9034: Bring back maintenance mode by default around ma:deploy.
- DP-9080: Admin theme changes
  - Admin toolbar now includes an add document and and an content button in the header for wide screens.
  - Two new corresponding links for the Content menu that only display at smaller bp's.
  - Author content tray links are now in sentence case; requires some config and views edits.
  - Fontello font updated to include new user icon.
  - Refactor of some button styles for site wide consistency.
  - Add content buttons from the node/add page and the my content view; Add document button removed from all documents view.%
- DP-9199: Push to Acquia in parallel with running build and test workflow.

### Fixed

- DP-9232: Sets referer for sitewide Formstack feedback form.
- DP-9247: Fixes an issue where the footer wasn't staying sticky on some custom pages.
- DP-9260: Eyebrow styling for node edit page titles only applies to node edit pages and not all page titles.
- DP-9266: Fix fatal error on Search Orgs API.
- DP-9287: Updates diff module again, which had been accidentally reverted. Fixes timeouts on revisions pages.
- DP-9290: Fixes issue with table row widths for inline entity form tables.
- DP-9290: Makes all "Remove" buttons consistent in styling.

## [0.105.0] - June 7, 2018

### Added

- DP-7206 Adds a tab that lists the pages that link to a page or document.

### Fixed

- DP-5989: Switch to OpenCage for geocoding to resolve errors thrown on save of contact nodes.
- DP-9139: Resolves a frequent source of developer confusion by removing mass_docs configuration.

### Changed

- DP-5989: Use random geocoder plugin in development/CI environments to prevent unnecessary API consumption during testing.
- DP-9147: Adjust placement of help text in node and media add/edit pages.
- DP-9225: Fixes bug where table row drag on node manage field forms aren't working.
- DP-9175: Moves Drupal container cache to Memcache to reduce reliance on database.
- DP-9240: Update Mayflower to 5.27.0.

### Removed

- DP-9062: Remove the obsolete fields, field_form_payment_options and field_form_time, and the associated obsolete taxonomy, Payment Types, from Form content type, mass_theme.theme, modules/custom/mayflower/src/Prepare/Molecules.php and /config/core.entity_view_display.node.form_page.teaser.yml.
- DP-9063: Remove obsolete fields, field_audience and field_guide_page_bg_narrow, from Guide content type.
- DP-9063: Remove the audience metatag from Google Custom Search Engine (CSE) in the Metatag config.
- DP-9064: Remove the obsolete fields, field_audience and field_how_to_ref_services from How-to content type.
- DP-9064: Remove the field_audience from Google Custom Search Engine (CSE) in Metatag config.
- DP-9065: Remove the obsolete fields, field_news_presented and field_news_speaker, in News content type.
- DP-9067: Remove the obsolete fields, field_regulation_agency_cmr_num, field_regulation_cmr_chapter_num and field_regulation_ref_org, in Regulation content type.
- DP-9069: Remove the obsolete field, field_audience, in Service Details content type.
- DP-9070: Remove the obsolete field, field_audience, in Service content type.
- DP-9139: Removes legacy mass_docs configuration values from user profile form to prevent confusion when these values are overwritten.

## [0.104.0] - June 5, 2018

### Added

- DP-9155: Our build now has local javascript packages. Eslint is the first one. Developers should run `yarn` after `git pull`, similar to how we run `composer install`.

### Fixed

- DP-9159: Unblocked access to the path `filter/tips` to restore Acquia uptime.
- DP-9142: Fixed truncation of url on additional resources field section on an information details page.

### Changed

- DP-4862: Updated the link field description to add new text for the link title.

### Removed

- DP-9061: Removed the obsolete fields, field_executive_order_ev and field_executive_order_ref_org from Executive Order content type.
- DP-9059: Removed the obsolete fields, field_advisory_ref_events and field_advisory_ref_organization, from Advisory content type.

## [0.103.1] - June 1, 2018

### Fixed:

- DP-9172: Reverted JavaScript changes from DP-8615 which prevented authors from scrolling when editing certain content types.

## [0.103.0] - May 31, 2018

### Fixed:

- DP-8582: _Related Links_ on _Alert Landing Pages_ are showing URL path rather than title.
- DP-8615: Refactors `add-more-paragraph-scroll.js` to correctly scroll to new paragraphs on node edit forms.
- DP-9140: _Information Details_ should start in `prepublished_draft` state so it can take advantage of restricted content controls.
- DP-9153: Overrides Mayflower link ease transitions just for the admin toolbar so the js can correctly calculate the height of the menu bar, and prevents an issue where the main nav overlaps the top tabs on author-facing frontend pages.

### Changed

- DP-5906: Re-enables memcache by specifying bucket routing.
- DP-6601: Improve AX for adding existing document file functionality.
- DP-7801: Add new placeholder thumbnail svg for add content page in admin theme.
- DP-8297: Change media link path to www in _All Documents_ view.
- DP-9058: Disable the obsolete field: `field_bg_wide` in Location content type.
- DP-9108: Checks if the user is active before sending watcher email.
- DP-9134: Update diff module to latest dev version to fix timeouts on revisions page.
- DP-9137: Update Mayflower to v5.26.0.

### Added

- DP-7638: Adds 'mass_flagging.entity_comparison' decorator service to add auto-generated revision logging to Watch emails.

### Removed:

- DP-8972: Removes _Flag_ link (on a trial basis) from block-based render array previously included on every node page.
- DP-9060: Remove the obsolete field: `field_decision_upcoming_ev` from Decision content type.
- DP-9151: Removes unnecessary thumbnails from Inline Entity Form tables as seen on node edit forms.

## [0.102.0] - May 29, 2018

This release contains the items from the Release 0.101.0 initially scheduled on May 24th, which held off due to the Unscheduled Maintenance for Acquia Cloud Enterprise.

### Added

- DP-8854: Add the Google Translate JS to html.html.twig.
- DP-8962: Adds PHPUnit tests for site-critical metatags across many content types.
- DP-9105: Adds new field, field_state_organization to the User Organization taxonomy.

### Changed

- DP-8854: Replace the hard-coded footer part in /theme/mass_theme/templates/layout/page.html.twig, /theme/mass_theme/templates/layout/page--node--widthout-main.html.twig, and /theme/mass_theme/templates/layout/page--search.html.twig with the Mayflower template, @organisms/by-template/footer.twig.
- DP-9072: Updated Mayflower version to 5.25.0.
- DP-9075: Add new legacy redirect endpoints (/redirects-prod.json and /redirects-staged.json) in a custom controller to fix paging issues and performance.

### Removed

- DP-8854: Remove the Google Translate JS from /theme/mass_theme/templates/layout/page.html.twig, /theme/mass_theme/templates/layout/page--node--widthout-main.html.twig and /theme/mass_theme/templates/layout/page--search.html.twig.
- DP-8911: Remove the pilot home page template and images used in it.

### Fixed

- DP-8910: Adds a patch to scheduler and a new unpublish action to fix the feature for scheduling nodes to be unpublished.
- DP-9025: Resolve 500 errors happening on JSONAPI org endpoint by reducing scope of custom JSON normalizer, and fixing computed field cardinality issues.

### Security

- DP-7406: Hides unnecessary system files to reduce disclosure of information about the site.

## [0.101.0] - May 24, 2018

Note: Release didn't happen due to the Unscheduled Maintenance for Acquia Cloud Enterprise.

### Added

- DP-5906: Implement Memcache in Docker and CI environments.
- DP-5906: Route bootstrap, data, default, entity, menu, and render cache bins to memcache in settings.\*.php
- DP-8854: Add the Google Translate JS to html.html.twig.
- DP-8962: Adds PHPUnit tests for site-critical metatags across many content types.

### Changed

- DP-8854: Replace the hard-coded footer part in /theme/mass_theme/templates/layout/page.html.twig, /theme/mass_theme/templates/layout/page--node--widthout-main.html.twig, and /theme/mass_theme/templates/layout/page--search.html.twig with the Mayflower template, @organisms/by-template/footer.twig.
- DP-9072: Updated Mayflower version to 5.25.0.
- DP-9075: Add new legacy redirect endpoints (/redirects-prod.json and /redirects-staged.json) in a custom controller to fix paging issues and performance.

### Removed

- DP-8854: Remove the Google Translate JS from /theme/mass_theme/templates/layout/page.html.twig, /theme/mass_theme/templates/layout/page--node--widthout-main.html.twig and /theme/mass_theme/templates/layout/page--search.html.twig.
- DP-8911: Remove the pilot home page template and images used in it.

### Fixed

- DP-8910: Adds a patch to scheduler and a new unpublish action to fix the feature for scheduling nodes to be unpublished.

### Security

- DP-7406: Hides unnecessary system files to reduce disclosure of information about the site.

## [0.100.1] - May 23, 2018

### Fixed

- DP-9078: Updates the bio page mobile display to remove the placeholder and add the image, contact information, and social links.

## [0.100.0] - May 22, 2018

### Added

- DP-3226: Supplemental information added to headings for a11y improvements.
- DP-8098: A checklist for things to do when creating a new content type.
- DP-7804: A new and better theme for authors, with fully redone content add forms, content add landing page, top toolbar menu, and more.
- DP-8120: Documentation for good testing practices for mass.gov developers.
- DP-8986: Added mg_stakeholder_org metatag to binder and info_details nodes.

### Changed

- DP-8981: Updated hotfix documentation after the improvements made in release document.
- DP-7782: A reset of link field's title text has been done so titles will update dynamically.

### Fixed

- DP-8800: Fixed media documents that had incorrect future date set as updated timestamp.
- DP-9027: Fixed 500 error being thrown on right rail pages that display image promos.
- DP-9027: Fixed 500 error being thrown when trying to save a right rail page (note: the error never made it to prod).
- DP-8997: Fixed javascript error that was caused weird issues with location listing maps when filtered.

### Removed

- DP-5656: Removed the deprecated content types, twig templates, and behat test for the following: section landing, subtopic, topic, and emergency alert.
- DP-6122: Removed funnel_or_endpoint taxonomy vocabulary and behat test.

## [0.99.0] - May 17, 2018

### Added

- DP-8918: Implements new components on the elected official and org pages.
- DP-8918: Implements bio page functionality on the person content type.

### Changed

- DP-8982: Updated Mayflower version to 5.24.0.
- DP-8055: On creating new draft content, or on un-publishing previously published content, url of the content item gets a string `---unpublished` added to its alias. This frees up url aliases for published content and results in less 404s for constituents.

### Post-Deploy

DP-8055:

1. These post deploy steps are only necessary for STAGE and PROD deploy, and are optional for local dev setup.
2. NOTE: These post deploy steps will take 30 mins to execute. Plan your deployment time accordingly.
3. Login as ADMIN role user.
4. Go To `admin/config/search/path/update_bulk` configuration page.
5. Select the checkbox `Content` (DO NOT select the others).
6. Then select the `Regenerate URL aliases for all paths` option.
7. Click on the `Update` button.
8. Wait for the batch process to be 100% complete.

## [0.98.0] - May 15, 2018

### Added

- DP-7562: Adds ID number for Node and Media entities to their edit forms for quick reference by content authors
- DP-8811: Adds PHPUnit tests operating on the existing site.
- DP-8811: Adds test coverage for private/public filesystem switch on media entity publish and unpublish.
- DP-8811: Adds test coverage for deletion of files when they are swapped out of a media entity.

### Fixed

- DP-8238: Fixed issue with simple login pages where a 403 could get cached with a header and footer, then displayed elsewhere on the site.
- DP-8936: Replaced the outdated markup with the Mayflower template for the site logo on error pages.
- DP-8973: Fix cache pollution on media /download route.

### Changed

- DP-6715: Replaced All Services page with a View Mode Page.
- DP-8238: Updated Drupal core to 8.5.3.
- DP-8888: Changed mg_type metadata to use mg_sub_type values for L&R types
- DP-8953: Move vendor-generated.js to footer to avoid blocking render.
- DP-8953: Remove theme custom JS dependency on jquery.once and drupal.announce libraries.
- DP-8955: Raise cache lifetime for generated pages to 1 week.

### Removed

- DP-8238: Removed patch for missing help text in link widget (included in 8.5)
- DP-8238: Removed patch for "always populate raw post data check on CLI" (included in 8.5)
- DP-8238: Removed patch for "User toolbar makes all pages uncacheable" (included in 8.5)

## [0.97.0] - May 10, 2018

### Added

- DP-8808: Adds new class for link_separate field formatter to use computed titles for link fields displayed in Service Page-related Views

### Fixed

- DP-8695: Fixed the showing #-# of # results and pagination on the location listing page by updating the URL paths in the env-drupal.js.

### Changed

- DP-7308: Changed the Service Page content type to display the organization logo in the "Offered by" listing of organizations if the organization has a logo defined.
- DP-8363: Replace the hard-coded site logo and seal components in the page template with the theming in Mayflower, @atoms/09-media/site-logo.twig.
- DP-8363: Replace the hard-coded site logo and seal components in the binder/info details template with the theming in Mayflower, @atoms/09-media/site-logo.twig.
- DP-8452- Moved some of the scripts under the mass_theme/overrides/\* folder.
- DP-8474: Added the "Label(s)" field (`field_reusable_label`) to the `info_details` content type and updated the `list_item_link` paragraph to accept `info_details` nodes in its `field_listitemlink_item` field.
- DP-8781: Change the text in the new user email to remove old email and add ServiceNow information.
- DP-8844: Updates work done in DP-8657 to have Media Entity Download to use redirects instead of direct downloads
- DP-8912: Updated Mayflower version to 5.22.0.

### Removed

- DP-8452: Removed unused scripts from the theme folder.

## [0.96.0] - May 8, 2018

### Added

- DP-8700: Added a new 'Doc deletion' role with permission to the new view to allow users access to document files for deletions.

### Changed

- DP-8852: Move private files directory for local development to prevent unwritable directory errors.
- DP-8789: Disallow "All tasks" field on services from referencing binder or info details.

## [0.95.1] - May 7, 2018

### Fixed:

- DP-8885: Resolve javascript error in IE caused by accessible character count library.

## [0.95.0] - May 3, 2018

### Added

- DP-8657: Check added to ensure files moved to private storage are not still accessible at their "media entity download" alias.
- DP-8705: Adds a \$with_type flag to DescendantManager::getChildren to support L&R Orgs.
- DP-8705: Adds a {type} filter to the orgs api endpoint (defaults to "news").
- DP-8705: Adds ability to query the org api for L&R related Orgs with type=laws-regulations.

### Changed

- DP-8185: Sitewide feedback form converted from a JS embed to static HTML.
- DP-8705: mass_search now depends on mass_content_api for the DescendantManager service.
- DP-8727: Ensure mass_jsonify_links token returns a string for "name".
- DP-8759: Changes the format of mg_date for the binder content type.
- DP-8809: Updated Mayflower version to 5.21.0.

### Fixed

- DP-5191: Help text for social links on Organization Landing pages is now visible.
- DP-5269: Extra space between the header and social media links on service and organization pages is fixed.
- DP-8295: Contact tab on how-to-page is fixed so that it shows a red asterisk indicating it is a required field.
- DP-8687: Resolves PHP fatals on viewing the download URL for a media entity that has no file.
- DP-8829: Fixed imagemagick throwing false errors on the Scale and Crop transition.
- Backout an update to the version of the paragraphs module after identifying compatibility problem.

## [0.94.0] - May 1, 2018

### Added

- DP-7127: All content listings can now be filtered by state organizations.
- DP-8542: Feedback Manager located at `/admin/ma-dash/feedback`.

### Changed

- DP-7031: Adding help text and links to the following areas: document edit form, media entity download link view, trash view, content types (field types, description & titles), paragraph types, and node edit form.
- DP-8641: Make drush ma:deploy default to --skip-maint.
- DP-8641: Add cache rebuild after deploying code in ma:deploy.
- DP-8641: Add --no-cache-rebuild flag for ma:deploy to skip rebuilding.
- DP-8792: Enable imagick image optimization.

### Fixed

- DP-8191: Add code to mass_schema_metatag to fix the field_state_organization_tax token for guide, topic_page, and event nodes.
- DP-8792: Update imagick module to stop false errors like "Image scale failed using the imagick toolkit".

### Removed

- DP-8721: Removed interstitial redirection for both incoming and outgoing links.

## [0.93.1] - April 27, 2018

### Fixed

- Update Mayflower version to 5.20.1 for revert to DP-8612.

## [0.93.0] - April 26, 2018

### Security

- DP-8747: Update JSON API module to add CSRF protection to authenticated requests.

### Changed

- DP-8587: Add image style for Guide Page Banners to reduce front end impact
- DP-8674: Preload a selection of WOFF2 fonts to improved perceived render time.
- DP-8699: Delete replaced media files immediately.
- DP-8726: Update Mayflower version to 5.20.0.

### Fixed

- DP-8715: Resolve PHP notice appearing on topic pages without related items.

## [0.92.2] - April 25, 2018

### Changed

- DP-8717: Update config for modules that were unintentionally updated in 0.92.1 release.

## [0.92.1] - April 25, 2018

### Security

- DP-8717: Update Drupal core to 8.4.8

### Changed

- DP-8717: Update Auto Entity Label, Draggable Views, Node Title Help Text, and Paragraphs to latest dev versions.

## [0.92.0] - April 24, 2018

### Fixed

- DP-8588: Fixed missing image style for topic page banners.
- DP-8643: Fixed 'Use of undefined constant getType' error in Molecules.
- DP-8677: Downgraded missing icon exception to a warning to fix 500 on service pages with malformed social media icons.

### Added

- DP-8641: Added Traefik to development environment for nicer URLs.

### Changed

- DP-8003: Refactored link titles to use computed fields within Mayflower module.
- DP-8688: QA nodes are now excluded from org and news API endpoints.
- DP-8633: Disabled autologout in non-Prod environments.
- DP-8647: Reduced size of alert data responses by 3/4th.
- DP-8630: Updated documentation in `development.md` on docker based local setup.

### Removed

- DP-8384: Removed the simpler_twig engine to avoid potentially overlapping Drupal and Pattern Lab templates.

## [0.91.1] - April 20, 2018

### Fixed

- DP-8677: Downgrade missing icon exception to a warning to fix 500 on service pages with malformed social media icons.

## [0.91.0] - April 19, 2018

### Fixed

- DP-6224: Fixes issue with relationship indicators not showing up on _Service Details_ pages when referenced under "Featured Tasks" or "All Tasks" on a Service page.
- DP-8357: Fix an issue with the search cleanup that caused javascript errors with Formstack feedback form.

### Added

- DP-8463: Adds [monolog](https://packagist.org/packages/monolog/monolog) for more flexible handling of log messages.
- DP-8576: Adds a _MetatagTag_ for `mg_sub_type` to sluggify the value.
- DP-8646: Adds [Stage File Proxy](https://www.drupal.org/project/stage_file_proxy) module to handle proxying of images in non-prod environments. The module also supports image styles.
- DP-8459: Sets the category meta tag to 'laws-regulations' on the following content types: _Advisory_, _Regulation_, _Decision_, _Rules_ and _Binder_.

### Changed

- DP-8621: Disable antivirus (ClamAV) to no longer check uploads in development environments.
- DP-8656: Change to salutation in watcher email.
- DP-8463: Use Monolog to send error messages to New Relic and Syslog on Acquia. In Docker, Monolog sends messages to `stderr`.
- DP-8362: Replace SVG icon template call with twig function to compile data source for icons per page to reduce page file size.
- DP-8362: Updated Mayflower version to 5.19.0.
- DP-8460: Changed parent relationship for _Advisory_ and _Executive Order_ nodes. Updated `mg_organization` meta tag token configuration for _Rules_ nodes. Requires post deploy steps: 1. `drush updb` 2. `drush cim` 3. `drush queue:run mass_content_api_descendant_queue` 4. `drush queue:run mass_content_api_relationship_queue`
- DP-8357: Clean up legacy search CSS/JS/PHP to reduce tech debt and improve front end performance.
- DP-8357: Make search autocomplete dependencies lazy load rather than being loaded on every pageview.

### Removed

- DP-8387: Removed three previously disabled blocks: `pilot menu`, `subtopic all action` and `emergency alert banner` (the old one).

## [0.90.0] - April 17, 2018

### Changed

- DP-8465: Updates `ma-optimized-backup` to number backups on each execution. The script now handles its own logs cleanup and pruning of older optimized backups.
- DP-8507: Allows Origin header to have a port number in API CORS checks

### Added

- DP-4957: Adds the ability to save unpublished media entities to the private
  filesystem. On publication these media entities are then moved to the public
  filesystem. Similarly when a published media entity is unpublished it is
  moved to the private filesystem.
- DP-8231: Adds text regarding any moderation state change to all Watch email notifications
- DP-8358: Set up a new page template for user login and TFA related pages for better formatted page content.

## [0.89.0] - April 12, 2018

### Changed

- DP-8365: Only load Views Ajax announcements when an Ajax view is used.
- DP-8365: Use Picturefill` from Mayflower rather than Drupal core.
- DP-8365: Use a single custom Modernizr instead of one for Mayflower and one for Drupal.
- DP-8451: Replace `jquery.once` in theme with the version from Drupal core.
- DP-8507: Consolidated CORS headers and cache info for News and Org API.
- DP-8520: Replace Akamai's custom forwarded CDN token header to the `edit.mass.gov`.
- DP-8575: Renames `mg_type` metatag for executive order and regulation content types. Allowed domains tested for exact match. Added stagesearch.digital.mass.gov to allowed domains.

### Added

- DP-7158: Added the mp3 file extension to the allowed file extension under Document.

### Removed

- DP-4610: Removed and deleted the service logo `field_service_sub_brand` from the service page fields.

## [0.88.0] - April 10, 2018

### Security

- DP-7091: Updates TFA module to latest release and prevents disclosure of user ids.
- DP-7405: Reduce logged in user inactivity period before automated logout to 4 hours (Enforce auto-logout on all pages).

### Fixed

- DP-8426: Fixes Workbench Tabs Module bug where messages can become cached, which causes these to show up on repeated views of the same page.
- DP-8457: Fix broken HTML in Main nav.

### Changed

- DP-8457: Clean up script to set random homepage background.
- DP-8500: Release documentation update.
- DP-6351: Change the result count container for the location listing to heading from <div>, and add the listing name for screen readers for clarification.
- DP-8336: Updates `mayflower-artifacts` to `massgov` organization and host on packagist.org.

### Removed

- DP-8383: Uninstalled the Bartik theme.
- DP-8386: Removed an unused `footer_social` region from the `mass_theme.info.yml`.

## [0.87.0] - April 5, 2018

### Added

- DP-7564: Added Drupal structure for the Featured Items Mosaic component on Organization Landing Pages and integrates the component. This change impacts users who will use the Organization Landing Page content type to create pages for elected officials.
- DP-7740: Adds new field Subtype to org_page content type.

### Changed

- DP-8471: Update Mayflower version to 5.18.0.
- DP-8313: Changed how mg_online_contact_url works to allow multiple contact links, including email addresses.

### Fixed

- DP-8436: TOC empty items on info details view within binder. Binder inner page bottom navigation page flipper while viewing an info details page extends under sidebar.
- DP-8464: Fixed the noise and false positives that were being sent to New Relic logs from Acquia logs.

## [0.86.0] - April 3, 2018

### Fixed

- DP-6967: Fixes potential XSS vulnerabilities for the display of decision nodes.
- DP-6968: Fixes potential XSS vulnerabilities in the decision_tree content type.
- DP-6969: Fixes potential XSS vulnerabilities for the display of decision tree branches.
- DP-6975: Fixes bug on paragraphs icon links that caused 500 error due to non-existent icon path.
- DP-6977: Fixes XSS bug on executive order pages related to the display of field_executive_order_overview.
- DP-6985: Fixes a potential XSS vulnerability related to the display of field_news_body.
- DP-6986: Fixes a bug related to the display of news items on organization pages. Also fixes a potential XSS bug related to field_news_lede and displaying press teasers.
- DP-6988: Fixes potential XSS vulnerabilities related to paragraph fields field_sprite_name and field_content.
- DP-6989: Fixes potential XSS vulnerability on service pages when rendering the field_service_body field.
- DP-6997: Fixes potential XSS vulnerabilities with the display of the page header on media documents.
- DP-7096: Fixes XSS vulnerability in content displayed on mass_map.map_page routes at paths like `/node/{node}/locations`.
- DP-8258: Fix the reporting of a 500 error to New Relic.
- DP-8428: Prevent a News content type from giving a 500 error when the signee organization page is deleted from the system.

### Added

- DP-8449: Added url to the allowed iframe domain list.

### Changed

- DP-6978: Changes method prepareParagraph to use fieldFullView to fix potential XSS vulnerability.
- DP-8317: Remove Google map api and overrides/js/initGoogleMaps.js from global insert. Add them as the 'google-map-api' library to Location, Location detail, Location list, Organization and Service pages only when those pages have Google map(s) in them.

## [0.85.0] - March 29, 2018

### Fixed

- DP-8321: Update _Decision Tree_ configuration to add two states: _Trash_ and _Unpublished_.
- DP-8372: Optimize slow query for validating _Legacy Redirects_.
- DP-4313: Remove width and height for `.ma__decision-tree-post .ma__callout-alert__icon svg` to fix the icon display issue.

### Added

- DP-7943: Add _Binder_ as a new content type.
- DP-7989: Add _Information Details_ content type along with its header fields, form display and twig entity mapping.

### Removed

- DP-8378: Remove `index-generated.css`, previously rolled as `hotfix-0.83.1`, and implement permanent fix in Mayflower.

### Changed

- DP-8213: Refactor and streamline `ma-refresh-local` utility code.
- DP-8422: Update Mayflower to 5.17.0.

### Deprecated

- DP-8213: `ma-refresh-local` to no longer offer the option of syncing files from prod or sync the db from Acauia's lower environments.

## [0.84.1] - March 28, 2018

### Security

- DP-8405: Drupal core security update highly critical release 2018 PSA-2018-001

## [0.84.0] - March 27, 2018

### Added

- DP-8311: Docker based development environment via Docker Compose.

### Changed

- DP-8348: Upgraded JSON API module after new access bypass security vulnerability was fixed in the module.
- DP-8355: Only push code to Acquia repo during the build_test workflow.

### Fixed

- DP-8374: Clean up error logs resulting from search redirection by returning a response.

## [0.83.1] - March 23, 2018

### Fixed

- DP-8373: Fix for the page banner display in mobile.

## [0.83.0] - March 22, 2018

### Added

- DP-6883: Adds a Twig `icon` function to embed SVGs once per page, even when used multiple times.

### Changed

- DP-6883: Use the `icon` function on download links to avoid embedding the same SVG multiple times.
- DP-8217: Update Mayflower to 5.15.0 release

### Fixed

- DP-8306: Reduce Nightcrawler's execution concurrency to address recently reported intermittent failures.
- DP-8325: Fixes dynamic lists not reflecting documents or content right away.
- DP-8354: Fixes fatal error being thrown on org pages when an e-mail address is entered as a contact link.

## [0.82.1] - March 22, 2018

### Changed

- DP-8011: Removes the call to mass_search's theme function to instead redirect users to the new search site.

## [0.82.0] - March 20, 2018

### Fixed

- DP-8171: Fixed timezone handling in Recent News API endpoint.
- DP-8259: Adds defensive error checking to service details template to resolve fatal error on /service-details/employment-placement-and-staffing-agency-definitions-and-the-law.
- DP-8061: Additional links that refer internal mass.gov content now automatically shows content's title as link text, just like topic cards already do.

### Changed:

- DP-8312: Changes how `mg_online_contact_url` works for internal links, using node title if no link text is provided.

## [0.81.0] - March 15, 2018

### Changed:

- DP-8214: Sets response headers necessary for the news and orgs API to handle CORS requests.
- DP-8072: Making a change to the mass_content_api to improve the usage on mass_metatag module. As well as mass_metatag_tokens change load() to loadMultiple() for nodes.
- DP-8212: Build a pre-imported and pre-sanitized MySQL docker image and use it in CI for faster test runs.
- DP-8252: Changes were made to the service page and service detail page tour steps.

### Fixed:

- DP-8261: Fixed typo in mass_metatags module that was causing fatal errors on service detail page during the release.
- DP-8263: Fix fatal error saving new organization pages that have a location specified.

## [0.80.0] - March 13, 2018

### Added:

- DP-8182: Add a nightly check for pending Drupal's security updates (runs at CircleCI).

### Fixed:

- DP-8202: Sort events in descending order in upcoming events listings.
- DP-8143: Improve performance on the How-to pages for Related Links by optimize the query from 1 large to 2 smaller ones.
- DP-8187: Fixes nightcrawler response time calculation to avoid false positives on response time.

## [0.79.1] - March 9, 2018

### Fixed:

- DP-8202: Sort events in descending order in upcoming events listings.

## [0.79.0] - March 8, 2018

### Added:

- DP-4027: Editors can now move content to trash state, which is just like deleting content, but if needed they can restore it later from trashbin. Adminstrators can permanently delete content from trashbin after which it cannot be restored.
- DP-7749: Populate new "Organization" field on nodes with value of the node's author's "user org"
- DP-7755: Adds the new metatag mg_stakeholder_org which holds the value of the stakeholder organization field field_state_organization_tax.
- DP-7945: Added an API endpoint to get news items dated from the last 48 hours.
- DP-8094: Added an API endpoint to get orgs with at least one related News item.
- DP-8180: Adds `--strict` argument to `behat` executions to force a build failure when testing issues are encountered.

### Changed:

- DP-8059: Replace handy_cache_tags:node:event with explicit node:PARENT_ID tag on events view
- DP-8059: Replace programatic uses of events view during theming with lightweight events service
- DP-8180: Upgrades `behat` and its dependencies from `v3.3.1` to `v3.4.3`.s

### Fixed:

- DP-3222, DP-3914: Heading levels have been fixed so h1 headings are followed by h2, and not h5, as was in some cases.

### Removed:

- DP-7960: Removed field_link from Organization pages. This field could fail validation, making it impossible to save certain content.

## [0.78.1] - March 2, 2018

### Fixed:

- DP-8125: Fix JSONAPI for alerts not clearing on new post of alert by attaching handy_cache_tags:node:alert tag to JSONAPI alerts endpoint.

## [0.78.0] - March 1, 2018

### Changed

- DP-7669: Changed date format on mg_date metatag to YYYYMMDD for easier sorting.
- DP-7998: Change node feedback form to use the state api to store settings.
- DP-8057: Optimize Molecules::prepareGoogleMapFromContacts() to speed up service pages with a lot of locations.

### Fixed

- DP-8039: Fixes public meeting event to correctly render Public testimony and Posted date fields.
- DP-7983: Fix pathauto not generating aliases on the first save of certain nodes
- DP-8075: Limits the scope of mass_schema_web_page_tokens to decision nodes and cleans up PHP warnings thrown by this function.
- DP-8087: Resolve slow query performance on location pages when generating "Related To" links.
- DP-7143: Optimize the function mass_schema_government_service_tokens by removing the unused 'entity_reference_revisions'.

## [0.77.0] - February 27, 2018

### Added

- DP-7666: Creates the mg_location_listing_url metatag and configures it for Organization pages.
- DP-7930: A drush script that safely deletes files associated with media entities that are supplied as input.
- DP-7950: Crawl the extent of the site for errors in a CI environment on a regular basis.

### Removed

- DP-8007: Uninstalled JSONAPI Extras module, which was no longer used.

### Changed

- DP-7142: Optimize the performance of mass_entityaccess_userreference.
- DP-7665: Changed mg_online_contact_url metatag to contain a JSON array of link objects.
- DP-7900: Reduces Akamai's cache ('max-age') from 1 hour to 30 minute.
- DP-8007: Update JSONAPI Extras module to 8.x-rc5 to support JSONAPI 8.x-1.10.0.
- DP-8009: Send additional data to New Relic. Adds cacheability and redirect ID attributes, names redirect.redirect and redirect.canonical transactions

### Fixed

- DP-8028: Fixes 500 error(s) on org pages where media/download was removed from the file system.

### Security

- DP-8007: Security update for JSONAPI module to 8.x-1.10.0.

## [0.76.0] - February 22, 2018

### Added

- DP-4715: Implement Schema.org structured data for "Decision" content type.
- DP-6679: To allow all users the ability to contact one another through email.

### Changed

- DP-1902: Change the date format for the alert page and the alert banner from "01.20.17, 10:01am" to "Jan. 20th, 2017, 10:01am".
- DP-2403: Change the social media link text in the footer for screen reader.
- DP-6350: Change the label text for the search query field in Location and Event listing pages from 'Show closest' to 'Search by city or zip code'.
- DP-6359: Change the static `title` content, Geographic Listings, for Location listing pages to match its unique page title, `h1` content, for each page.
- DP-7873: Implement Path/URL purging for files on CRUD ops to allow editors to see file changes faster.
- DP-7985: Limit depth of subpathauto for performance.
- DP-8012: Drupal core - Critical - Multiple Vulnerabilities - SA-CORE-2018-001.

### Fixed

- DP-7952: Fixed a 500 error on the Rules of Court content type when the entity_ref is using a relative url in the link field.
- DP-7964: Fixes PHP notices on location pages.
- DP-7985: Resolve listing pages appearing at unaliased url following redirect canonical change
- DP-8002: Followup to DP-7985 to fix past events page aliasing, which is still broken.

## [0.75.0] - February 20, 2018

### Added

- DP-7764: Users now redirect to /my-work page after login. This works even when users login via the _forgot password_ route.
- DP-7027/DP-6562: Drupal configuration for public meeting notice functionality for event content type. This requires Mayflower and Drupal theming and validation for it to be fully functional.

### Changed

- DP-7956: Changed the description for Organization and Form page to include link to published article page. As well as made a change to the Guide page link text per a request.
- DP-7662: Slugifies mg_organization metatag everywhere it's used (Advisory, Decision, Executive Decision, How to Page, News, Regulation, Rules, Service Details and Service Page content types).
- DP-7663: Slugifies mg_type metatag everywhere it's used (Advisory, Decision, Executive Decision, News, Regulation and Rules content types).
- DP-7664: Creates the mg_contact_details metatag and configures it for Organization pages.

### Fixed

- DP-7937: Update TFA patch to resolve an issue where anonymous could log in using only the second factor.
- DP-7948: Fix 500 error happening on topic pages.

## [0.74.0] - February 15, 2018

### Added

- DP-7086: Adds contact info to service pages.
- DP-7188: Add scheduling to nodes via the Scheduler module. Extend scheduling to draft content.
- DP-7778: Add fix to check if field_alert_display is site-wide before sending mail; change form to use state; send notifications on Prod environments only.
- DP-7872: Adds steps for how to check production's optimized backup status prior to a release.

### Changed

- DP-7469: Enable php warnings and assertions for the CI and the VM
- DP-7636: Updates config to the three Decision Tree related content types in order to make them more consistent with the config standards for other content types on the project.
- DP-7640: Change the help text and links for the following content types on node/add page news, guides, and events. Also made changes to help text and link in the overview section within the add/node for alert, service detail, and service page.
- DP-7723: Move the daily backup pull, within the vm, into `/home/vagrant` to persist vm reboots.
- DP-7863: Track dynamic page cache transactions in New Relic.
- DP-7921: Updated the patch to the TFA module to fix access bypass introduced in DP-7842.
- DP-6929: When an internal link is referenced in a link field with the link title text left empty, and the content is saved, link title text field now remains empty. Latest title of the internal link is fetched when displaying the link.
- DP-7726: Optimizes admin views to reduce performance impact. Removes duplicative view of all content (/admin/ma-dash/all-content).
- DP-7937: Update TFA patch to resolve an issue where anonymous could log in using only the second factor.

### Fixed

- DP-7469: Fix PHP warnings preventing successful Behat tests.
- DP-7842: Fix TFA module causing personal contact forms to 404 when visited by other users.
- DP-7918: Restore node_id=0 parameter on feedback forms for non-node pages.
- DP-7919: Restore field (column) inadvertantly removed from "all documents" view

### Removed

- DP-7842: Removed uid enumeration prevention from TFA routes.

## [0.73.0] - February 13, 2018

### Added

- DP-7009: Enable dynamic page cache for logged in users. This speeds up regular page views. Does not apply to the node edit page.
- DP-7343: Adds a troubleshooting section to the readme for missing keys from the ssh-agent.
- DP-7817: Add revision log message to watch email.

### Changed

- DP-6585: Reconfigures My Documents to use an accurate "permalink" so authors can directly copy and paste them. The list is also slightly reconfigured to make it more consistent and easier to use.
- DP-7809: `drush uli` no longer needs `@local` in the command.
- DP-7833: Resolve cacheability issues with the feedback form so it gets cached once globally.
  Clean up potential cacheability concerns in custom workbench status block.
- DP-7834: Remove "node_list" cache tag from the alerts collection JSONAPI endpoint to improve cacheability.
  Remove custom cache lifetime override for alerts collection JSONAPI endpoint to improve cacheability.

### Fixed

- DP-6105: Enforce clean and canonical URLs to avoid cache poisoning where URLs may have index.php in their paths.
- DP-7794: Fixes a caching bug that could make Flag and Watch links show up in the incorrect state for authenticated users.

### Security

- DP-7151: Prevents site errors and disclosure of user name and user id through password reset.

### Post-Deploy

n/a

## [0.72.0] - February 08, 2018

### Added

- DP-7774: Adds "BingSiteAuth.xml" for site verification.
- DP-7681: Customize transaction names and attributes in New Relic.
- DP-7527: Adds the custom module mass_tours which extends the Tour module so we can create content-type specific add/edit tours. Enables the Tour module. Adds the Tour UI contrib module (but does not enable it). Include 3 new Tour YAML files with tour content.

### Changed

- DP-7742: Update README's authentication section (.env file setup)
- DP-7498: Removed node_list cache tags from organization news page.
- DP-6908: Upgrade codebase to use PHP7 everywhere.
- DP-7733: Bump the Mayflower version up to 5.11.0

### Fixed

- DP-7498: Fixes a bug where visits to invalid pager pages (?page=999) on the news page could cause the page to disappear.
- DP-6551: Allow uploads to formstack forms via a new form type, which redirects the user to Formstack on submission.

### Post-Deploy

- Switch your Acquia environment to PHP 7. This can be done via the Acquia UI.

## [0.71.0] - February 06, 2018

### Added

- DP-6012: Adds new Adjustment Type vocabulary. Migrates data between Adjustment Type text field and new Adjustment Type taxonomy reference field on Adjustment Type paragraph entities via a post update hook.
- DP-6175: Adds in the Overview field to the Curated List content type and renders it under the subtitle.
- DP-7335: Adds a config form, "Alert Watcher Email Recipients", which allows admins to create a list of watchers of site wide alerts and sends them an email when that alert is published.
- DP-7362: Adds a block to the node add/edit page and a notification on node insert/update. Adds a form to configure messaging at /admin/config/mass-feedback.
- DP-7431: Adds the email address of the author to the watch report email. When an author publishes changes to a watched node, the watchers receive the email with the address of the author so they can contact them with any questions.
- DP-7735: Adds a script create an optimized backup of the production database prior to a release.

### Changed

- DP-6826: Update to Drupal 8.4.4
- DP-7336: Added validation to `field_liststaticdoc_item` to prevent multiple documents from being added to a single list item on curated list nodes.
- DP-7336: Added post_update hook to move multi document list items on curated lists so that each list item may only have one document at a time.
- DP-7336: Added the ability for the manual description field to hide until the Manual Description type is selected for document list items.
- DP-7654: Replace `node_list` cache tag on legacy redirects API with `handy_cache_tags` equivalent.
- DP-7672: Raise cache lifetime to 24 hours
- DP-7738: Removed `node_list` cache tags from `*/need-to-know`, `*/related` and `*/tasks views`.
- DP-6496: Update build process to CircleCI 2.0 for to enable faster, more flexible builds.
- DP-6952: Changes Official Version field on Regulation nodes to be optional when the Regulation Status is set to Proposed.
- DP-7062: Adds a short description to curated list item groups. The description can be manually created or automatically pulled from a new "listing description" field present on those content types which have a full display view. Upon deployment, plain text content from "overview" fields will be migrated into this new field, stripped of html, and truncated to 155 characters.
- DP-7062: For the Curated List content type, multiple links on list items for manual sections now have one link per item.
- DP-7062: Hides Manual Description field from display unless the manual description type is chosen on Curated Lists.
  - Run drush updb and ensure the `mass_utility_post_update_curated_list_manual_sections()` and `mass_utility_post_update_mass_listing_description_migrate_update()` post update completes.

### Deprecated

- DP-6012: Removes `field_adjustment_type` from the display of Adjustment Type
  paragraphs. TODO created to remove the code altogether once migration has been
  completed and the field has been removed.

### Fixed:

- DP-7487: Resolved major performance issues affecting editors using the all content and admin/content views.
  Removed:
- DP-7487: Removed Entity Access User Reference filter from all content dashboard and admin/content views.
- DP-7487: Removed result count from all content dashboard view.
- DP-7487: Switch to mini pager on admin/content view to avoid expensive count query.
- DP-7732: Corrects warnings on post update functions related to curated lists.
- DP-7732: Put back `file_upload_submit` module files that were removed with a revert.

## [0.70.0] - February 01, 2018

### Fixed

- DP-7360: Make the search page cacheable by the Dynamic Page Cache. `X-Drupal-Dynamic-Cache` will now return `HIT` or `MISS` as values.
- DP-7103: Prevents multiple calls to `metatag_get_tags_from_route` on every non-cached page load.

### Changed

- DP-7474: Changed the 'Courts' field in the Rules of Court to allow Location, Service Page, Service Detail, Topics Page, Organization, and How-To pages only to be searched in the auto-complete for internal links.
- DP-7652: Reduce Behat execution time by 50% with optimized tests.

### Removed

- DP-7567: Removes Composer's orphaned and unused dependencies.

### Added

- DP-7626: Adds more non-portal sites e.g. "envir", "agr" to list of valid legacy site names.
- DP-7567: Adds explicit requirements for Symfony components to reduce composer memory usage.

## [0.69.0] - January 30, 2018

### Added

- DP-6405: Adds a view and 2 exports of information about users. For use updating the Mailchimp list of all users and for managing users (customer success / technical support). Available only to site Admins.
- DP-6548: Adds ability for the users to upload documents with the following file extension: dwg, prx, and mpp.
- DP-7328: Prevent submission of node add/edit forms while a file upload ajax action is in progress.

### Changed

- DP-7411: Update the new user welcome email with new friendly urls and content changes.
- DP-7446: Adds ID's to lists of content (nodes, files, media entities)

### Fixed

- DP-7488: Hide unpublished media referenced in a curated list.
- DP-7517: Allows non-restricted content to appear in the "All Content" view for non-administrators who are not the author.

## [0.68.0] - January 25, 2018

### Added

- DP-7344: Adds sort date fields for Decision, Executive Order, and Regulation content types.
- DP-7514: Behat tests for documents.
- DP-7532: Install ImageMagick on CircleCI.
- DP-7513: Adds and documents an additional step to the end of the release process to check New Relic for a spike in errors.

### Changed

- DP-3197: Make service page component headings more contextual for better accessibility:
  First time? -> First time? Start here.
  Featured: -> Top tasks
  All tasks: -> All other tasks
- DP-6547: Changed sort field for automatic lists on Curated List content type. Advisories will now sort by field_advisory_date and documents will sort by field_start_date.
- DP-6547: Adjusted sort field to compare with timestamps for automatic lists.
- DP-6733: Reorder the sections under the optional details in Location page edit page.
- DP-7515: Raise cache lifetime to 6 hours
- DP-7548: Remove list cache tags from publicly visible content types that were not changed in DP-7337

### Fixed

- DP-7209: Hide relationships to unpublished nodes
- DP-7309: Fixes 500 on org page for organizations referencing unpublished news items.
- DP-7439: Fixes fields on which form validation checks are performed.
- DP-7560: Fixes error on viewing revisions of the homepage node.

## [0.67.2] - January 23, 2018

## Fixed

- DP-7560: Fixes error on viewing revisions of the homepage node.

## [0.67.1] - January 19, 2018

## Fixed

- DP-7502: Fixes 500 thrown on all document pages.

## [0.67.0] - January 18, 2018

## Added

- DP-7337: Behat tests for cache tags on Curated List, How to Page, News, Org Page, Service Detail Page, Service Page, and Topic Page content types

## Changed

- DP-6932: Adds additional content that can be referenced on Advisory and Regulation pages.
- DP-7121: Tabledrag and hide/show rows for decision tree authoring.
- DP-7337: Remove node_list cache tag from Curated List, How to Page, News, Org Page, Service Detail Page, Service Page, and Topic Page content types. Replaces relationship views used from theme with a `RelationshipHelper` class.
- DP-7367: Page based alerts are changed to allow alerts to be added to all page types that can be rendered as a page. Previously, they were allowed only on organizations, locations and services. This also changes the order of items shown to be sorted by title.
- DP-7367: Page based alerts are changed to allow alerts to be added to all page types that can be rendered as a page. Previously, they were allowed only on organizations, locations and services. This also changes the order of items shown to be sorted by title.
- DP-7439: Authors can create an advisory, decision, or rule of court with only an overview but not a section or a download, and have the page publish. Previously, the advisory, decision, or rule of court had to have either the section or download completed.
- DP-7491: Increase the timeout duration for database pull and deployments to 30 minutes.
- DP-6234: Do not limit the number of items that can be validated by an entity reference field

## Fixed

- DP-7364: Fix 500 error on org pages with dangling contact reference.

## [0.66.0] - January 11, 2018

## Changed

- DP-6791: Increases max upload size for all file fields and WYSIWYG file embeds to 100Mb.
- DP-6826: Update to latest Drush9. Updates commands and aliases accordingly.
- DP-7125: Early push to Acquia Git from CircleCI.
- DP-7232: Change the help text and add link to external published article for the following content types Service, Service Detail, How-To, Contact info, Person, Legacy redirects, Documents, Curated List, & Alert.

## [0.65.0] - January 9, 2018

## Added

- DP-5027: Implemented Schema.org structured data for "News" content type.
- DP-6175: Adds in the Overview field to the Curated List content type, renders it under the subtitle.

## Changed

- DP-6229: Changes alphabetical sorting on curated lists to use natural sorting.
- DP-7063: Improves theming for Decision Tree disclaimer section.
- DP-7189: Adds rules of court to curated list so authors can select rules in manual lists.

## [0.64.1] - January 5, 2018

### Fixed

- DP-7252: - We’ve made the ajax pattern respect cache (i.e. the get request no longer appends a cache busting querystring “\_=<timestamp> parameter).

## [0.64.0] - January 2, 2018

## Added

- DP-5800: Adds sitewide alerts and page alerts that show up withing a minute of getting published.

## Fixed

- DP-6818: Allows admins to see Decision Tree Branches and Decision Tree Conclusions themed and in context before they're navigable in Decision Trees.
- DP-7089: Allows administrators to unblock users who have not set up TFA.

## Changed

- DP-4801: In Edit.mass.gov, changes the button text for creating a temporary link to a page from "Generate Token" to "Get link".

### Post Deploy

- Run `MASS_FLAGGING_BYPASS=1 vendor/bin/drush mdtpp` to populate field_decision_root_ref for existing Decision Tree Branches and Conclusions.

## [0.63.0] - December 27, 2017

## Fixed

- DP-6228: Fixes incorrectly linked titles for contact info nodes in sidebars on Guide Page nodes
- DP-7129: Fixes a bug causing a 500 error when news items are viewed in teaser mode.

## Added

- DP-7034: Adds content-type blacklists for autocompleted internal links in default Link field widgets

## Changed

- DP-4803: Adds the entity access form to new nodes.

## [0.62.0] - December 21, 2017

## Fixed

- DP-5916: Fix image ratio on for location teasers on location listings.
- DP-7184: Fixes key actions for Decision Tree Conclusions.

## Added

- DP-7072: Adds validation to ensure that Legacy URLs in Legacy Redirect nodes contain a valid legacy site name, and begin with `http://www.mass.gov`.
- DP-7185: Add url to the allowed domain under the iframe widget.

## Changed

- DP-7186: Configuration backport to make permanent Rule of court permissions for authors and editors.

## [0.61.0] - December 20, 2017

## Added

- DP-6538: Add a download path / route with media entity download and pathauto / subpathauto.
- DP-6466: Adds a ckeditor / wysiwyg plugin for download links
- DP-7024: Adds check for variable to prevent 500 error in views-view-field—image-promos--entity-reference-label.html.twig
- DP-7172: Add 4 new urls that were approved for the iframes whitelist.
- DP-4803: Authors can now restrict access to unpublished pages to users they select.
- DP-6067/DP-6897: Configuration and theming for new content type: Rules of Court

## Changed

- DP-6363: Improves Decision Tree page styling
- DP-7024: Adds check for variable to prevent 500 error in views-view-field—image-promos--entity-reference-label.html.twig
- DP-7133: Updates Mayflower artifacts to [5.10.0](https://github.com/massgov/mayflower/releases/tag/5.10.0)

## Fixed

- DP-6115: Updates RewriteRule in .htaccess to prevent incorrect 403 Forbidden status on non-admin paths starting with `admin`
- DP-6240: Allows external links in the "Related" sidebar section on Advisory, Decision, Regulation, Executive Order, and News pages.
- DP-7068: Decision Trees now track your responses in the URL, resolving several issues with incorrect responses.

## [0.60.0] - December 14, 2017

## Added

- DP-3249: Update link field to show content type during selection.
- DP-7088: Adds a section of the docs to explain performance. This will be referenced from a batch of performance tickets to be created shortly. There are no code changes here!
- NO-TICKET: Add a setting to the drushrc.php file to increase memory.

## Changed

- DP-6347: Ensures decision tree content is always in view when progressing through steps.
- DP-7124: Update Drupal caching from 1hr to 3 hrs.
- DP-7145: Remove `ssh-add` command from the provisioning process, which was a blocker for users with a private key that protected with a passphrase; and/or ssh keys named other than `id_rsa*`.

## [0.59.1] - December 12, 2017

## Removed

- Removed DP-4803: Allow authors/editors to restrict access to draft content

## [0.59.0] - December 12, 2017

## Added

- DP-5385: Adds "See all" links for related Events and Locations when viewing Event or Location pages. Enables listing pages for related Events and Locations. Hides unpublished Related Events and Locations.
- DP-6515: Enable memcache for cache_bootstrap and the lock service.
- DP-4803: Allow authors/editors to restrict access to draft content to the page's author or additionally a list of users.
- DEV: Add a setting to the drushrc.php file to increase memory.

## Changed

- DP-5167: Applies patch to better field descriptions to allow use with paragraphs.
- DP-7102: Generate 403/404 page one time, use for every 403/404 thereafter.

## Removed

- DP-7101: Remove dependency on page_cache from Acquia Purge and disable page_cache module.

## [0.58.1] - December 8, 2017

## Security

- MASSGOV-1259: Tightens two factor authentication security.

## [0.58.0] - December 7, 2017

## Added:

- DP-3659: Adds missing metadata for How-To, Service, Service Detail and Topic content types for better representation of pages on social media; and to get better search results.
- DP-7084: Added a field and filter to the "/admin/people" view using the "organization" taxonomy. This can only be accessed by the Administrator role.

## Changed:

- DP-7081: Converts Legacy Redirect URL schemes from HTTPS to HTTP on save.
- DP-7085: Disables temporary file deletion on cron.

## Fixed:

- DP-7017: Fixes fatal error on locations page for nonexistent nodes.

## [0.57.0] - December 5, 2017

## Fixes

- DP-6112: Updates theme to use past event more link and display help text in new variables from mayflower.
- DP-6961: Upgrade mayflower to 5.9.1.
- DP-7019: Fix events referencing a deleted node in the "Associated Pages" field throw a fatal error when viewed.
- DP-7023: Fix fatal error on viewing video paragraphs that reference a nonexistent video.
- DP-7047: Refactors Legacy Redirect validation to stop dropping trailing slashes, but to still check for them during validation
- DP-6094: Alters the news listing to include the date, type of news and the other useful information.
- DP-6519: Manually provides cache tags for referenced entities in order to allow cache invalidation and timely content updates
- DP-5458: Displays the dateline at the top before the main text of the release.

## Removed

- DP-6719: Remove file entities that exist in the db but not on the server.

## [0.56.1] - December 4, 2017

### Changed

- Revert `MASSGOV-1260` to fix the 403s appearing when the user uses "Forget your Password" link and to allow user to enable the TFA after logging twice into the CMS.

## [0.56.0] - November 30, 2017

### Added

- DP-2015: Drupal purges Acquia environment Varnish cache when it has invalidated the cache tags for edited configuration and content.
- DP-4757: Adds 2 new buttons to the rich text editor, "remove formatting" and "insert special character."
- DP-6630: Adds a migration "slot" for DPH documents. Upon DPH's approval, migration is then initiated to import data from "dph_perc_20171127.csv".
- DP-6658: Adds image url metadata to content types where featured images exist to provide twitter share cards with imagery.
- DP-7025: For developers, in order to improve the build speed; we now now skip these tables: 'migrate\*', 'config_log' and 'key_value_expire'.

### Changed

- DP-6088: Fixes `array_unique()` error message when loading Service Page nodes
- DP-6196: Fix XSS vulnerabilities in fields that used `Atoms::prepareRawHtml()`.
- DP-6831: Fixes validation error with duplicate values in Legacy Redirect URLs

### Removed

### Post Deploy Steps

## [0.55.0] - November 28, 2017

### Added

- DP-3158: Installed "Password Reset Landing Page (PRLP)" contrib module, which includes the ability to redirect users to homepage after a password reset.

### Changed

- DP-5755: Clears cache for referenced associated page nodes to allow changed/new events to appear on the referenced nodes. Cache is cleared just before save of the event node.
- DP-5969: Change Adjustment Type option from 'Superceding' to 'Superseding' to match AP recommendations.
- DP-5851: Developers only - ma_deploy now uses `sql-sync` rather than AcquiaCloud.
- DP-6915: Updates "Related Parks" images on Location pages from Legacy image field to Banner image field.
- DP-6863: Prevent XML Sitemap from clearing entity_types cache tag on every view of the node form.
- DP-5715: Adds form_page, fee, person and regulation to both "My Work" and "All content" views under "My Content". For developers, made it so we can now bypass the mass watching notifications for node changes via the command line.
  - run `MASS_FLAGGING_BYPASS=1 vendor/bin/drush mass-save-node-bundle-update --set_moderation_state=TRUE "fee form_page person regulation"`
  - Slack developers in massgov channel, so they are aware of the change.

## [0.54.0] - November 21, 2017

### Security:

MASSGOV-1260: Fixes a uid/username enumeration vulnerability in the Drupal’s core password reset form.

### Fixed:

DP-5463: Fixes a bug where non-external URLs reported having no routes, which caused 500 errors.

### Changed:

DP-6711: Updates "trusted_host_patterns" to include "www.mass.gov", changes schema.org data to render with "www.mass.gov" URLs (instead of pilot’s) and does miscellaneous renaming from "pilot." to "www."
DP-6717: Restores Quick Actions field on How-To pages without affecting Decision; and migrate field data with "treesmass_decision_tree_update_8001()".

### Added:

DP-4595: Adds new help text for social media-related link fields in node creation forms.
DP-5634: Adds docs migration record for Alcoholic Beverages Control Commission (ABCC).
DP-6667: Adds a "Media ID" exposed filter to documents' views "all-documents" and "add existing" media.
DP-6892: Pushes the decision tree branch / conclusion data to data layer for analytics.

### Removed:

DP-5098: Uninstalls "simple_oauth" and "simple_oauth_extras" modules (Cookie based authentication is already being used instead).

## [0.53.0] - November 16, 2017

### Added

- DP-6638: Use Pathologic module to make in-content links to pilot.mass.gov, edit.mass.gov, www.mass.gov relative.

### Changed

- DP-2953: Moves fieldset descriptions on node edit forms to be above fieldset content.
- DP-6654: Fixing the quick action links on the location page. This fix will allow the user to view quick action links on both desktop and mobile view.
- DP-6713: Fix 500 thrown on accessing entity properties for non-entity URLs

### Removed

- DP-6089: Removes warning about invalid argument for foreach loop when there is no downloads for service pages.

## [0.52.0] - November 14, 2017

### Added

- DP-6067: Adds description text for twitter card metadata so twitter social media cards provide a better user experience.

### Changed

- DP-4908: Updates target URLs on the legacy redirects JSON REST export to `www.` instead of `pilot.`.

### Fixed

- DP-6576: Updates max number of items able to be displayed on all_documents view from 'All' to 200
- DP-6056: Updates range limit from NULL to 100 on queries tagged as `entity_reference`
- DP-6086: Fixes warnings reported when viewing decision nodes due to uninitialized array variables.
- DP-6044: Checks for empty Organizations references in Regulation Page and Decision nodes before rendering
- DP-6186: Writes a log to the database when all site caches have been cleared.
- DP-6716: Fixes validation to avoid mention of edit.mass.gov domain.
- DP-6696: Adds extra validation to legacy redirect URLs to ensure they are in valid URL format before submission.

## [0.51.0] - November 9, 2017

### Added

- DP-6636 - Allow the tester role have permissions to the analytics tab in the CMS.
- DP-4430 - Adds validation, when saving nodes, to check for edit.mass.gov links in text areas and text fields.
- MPRR-311, MPRR-497 - added Pager (query strings: items_per_page, offset, page) to Rest Export views that return thousands of records
- DP-5704 - added drush commands to cache data.json document endpoints: mass-serializer-cache-all, mass-serializer-cache, mass-serializer-render-partial
- DP-6189 - Added 'File Name' and reordered fields in the document media view - displayed when content authors click 'add existing file' while creating a page.
- DP-6290 - Adds descriptions to the decision tree, decision tree branch, and decision tree conclusion content types.

### Changed

- DP-5900 - Authenticated users will notice that they are now logged out of the CMS automatically after a 24 hour period of inactivity.
- DP-6396 - Updates descendant manager relationships so organization pages define child services instead of parent services and key info on services defines children of the service.

### Removed

### Post Deploy

- DP-6396:

1.  drush queue-run mass_content_api_descendant_queue
    (should take about a minute to process)
2.  drush queue-run mass_content_api_relationship_queue
    (could take quite a while, maybe 20 to 30 minutes to process)

## [0.50.0] - November 7, 2017

### Added

- DP-5672: As a security update, we added 3 HTTP response header fields: X-Content-Type-Options, X-Frame-Options and X-Xss-Protection.
- DP-6410: Adds the person content type to the list of bundles with e-mail fields within the Mayflower module.
- DP-6444: Refactors decision tree responses to work with drop in inference.
- DP-6328: Makes decision tree content types revisionable. Adds new post update function to add initial revision to decision tree content types, allowing them to show up in content listings.

### Changed

- DP-5568: Adds a link field to reference internal and external organizations on regulation pages. Replaces the existing reference field.
- DP-6412: Fixes error on regulation pages related to empty required Organization field.
- DP-6516: Fix 500 error viewing media entities with no organization set.
- DP-6109: Rearrange My Content menu items to remove items authors shouldn't see and put content administrator items onto the Shortcut menu.
- DP-6076: Fixes bug in legacy image fallback for location pages so older banner images are shown as a fallback if no current banner image exists.
- DP-5487: Avoid duplication of contact section IDs to fix sidebar section scrolling.

### Post deploy

- DP-5568: Run `drush mass-regulation-org-ref-update` to migrate data from field_regulation_ref_org to field_regulation_link_org.
- DP-6328: Run `drush update-decision-trees`

## [0.49.0] - November 2, 2017

### Changed

- DP-5295: Replaces the "see all" link in the "More Services" section on Organization Pages with an auto generated link to `[org:url]/services`. This takes effect when there are more than 6 services added to the "More services" field. The link goes to a listing page of all services listed for that organization.

### Fixed

- DP-6411: Fixes logic in `mass_theme_preprocess_node_news` for displaying signees.

## [0.48.1] - November 1, 2017

### Changed

- DP-6338: Updates Legacy Redirects CSV view to use a paginated JSON list. Also adds a new views style plugin serializer_with_pager.

# [0.48.0] - October 31, 2017

### Added

- DP-6226: Add permalink for media entity and update "All Documents" view.
- DP-6403: Add hash-route destinations to edit / create links in the decision tree admin block.
- DP-5335: Adds publishing status, "Draft", "Proposed", and "Working Draft", to regulation content type.

### Changed

- DP-6404: Changes edit access operation to update access operation for DescendantManager's getSettingsForTree method. This affects the display of edit links on the root link.
- DP-6402: Updates relationships/descendants for decision tree, decision tree branch, decision tree conclusion nodes instantly without the use of queues and cron.
- DP-6401: Fixes cached rendering on the DescendantNodes block.
- DP-5855: Change the heading title text for 'Key Agencies' to 'Key Organizations' in Guide page.
- DP-4556: Review and adjust all (regular, non-utility) content types so they all: have a metatag field and don't show a URL alias, Authored on, Rabbit hole settings, Promote to Front page, or Make sticky on the front end.

### Post deploy

DP-5335: Set up the following terms for the Regulation Status vocabulary in /admin/structure/taxonomy/manage/regulation_status/overview.

- Working Draft
- Draft
- Proposed

## [0.47.0] - October 26, 2017

### Added

- DP-6201: Adds descendants for decision tree, decision tree branch, decision tree conclusion content types.
- DP-6205: Adds template for 'Decision Tree' content type.
- DP-6206: Adds drupalSettings for decision tree display from decision tree children.
- DP-6207: Adds template for 'Decision Tree Branch' content type.
- DP-6208: Adds template for 'Decision Tree Conclusion' content type.
- DP-6210: Adds JS to initialize Decision Tree when viewing Decision Tree nodes.
- DP-6211: Adds JS to navigate through Decision Tree branches.
- DP-6212: Adds JS for Decision Tree back buttons.
- DP-6213: Adds JS for restarting Decision Tree browsing.
- DP-6215: Adds edit links for authorized users on Decision Trees.
- DP-6216: Adds a new block that displays decision tree branches and decision tree conclusions belonging to a decision tree.
- Percussion documents delta migration (metadata from percussion 10.23.2017).
  **_POST DEPLOY STEPS_**

  - Download the compressed csv file for this delta run from: https://jira.state.ma.us/browse/DP-6289
  - Uncompress the csv file by running `percussion_metadata_05_25_2017.csv`
  - Copy the csv file to production by running the following command:
    ```
    scp -p ~/Downloads/percussion_metadata_05_25_2017.csv massgov.prod@web-21429.prod.hosting.acquia.com:/mnt/files/massgov.prod/files/migration/percussion_metadata_05_25_2017.csv
    ```
  - `drush migrate-import --group=massdocs_content`
  - Run `drush cc drush` if the command above throws an error; then run the command again.
  - `drush migrate-status` to check the migration status.

  Much more detailed instructions in Jira: https://jira.state.ma.us/browse/DP-6289

- DP-6290: Adds descriptions to the decision tree, decision tree branch, and decision tree conclusion content types.
- DP-6322: Adds create links for decision tree branch, decision tree conclusion nodes. Creates destination query parameters to return user back to parent decision tree.
- DP-6346: Adds URL hash that controls routes for decision trees.
- DP-6365: Adds accordion functionality to Decision Tree Branch nodes.
- DP-6366: Adds video functionality to Decision Tree Conclusion nodes.

### Changed

- DP-3505: Updating the body of the new user email to appear without html tags.
- DP-4962: Fixes spacing of markup around the hours section on locations.
- DP-5758: Replaces existing "Agency CMR number" and "CMR chapter number" with fields that are type string to allow letters in the Chapter number and decimals in the Agency CMR number.
  **_POST DEPLOY STEPS_**
  1. In order to migrate the data from the field_regulation_agency_cmr_num to field_regulation_agency_cmr and field_regulation_cmr_chapter_num to field_regulation_cmr_chapter run the following: `drush mass-regulation-cmr-agency-chapter-fields-update`
- DP-6316: Fix caching bug with formstack feedback submissions containing the current node ID.
- DP-6340: Fixes bug caused by missing true or false referenced entity from decision tree branch nodes referenced by a decision tree.
- DP-6385: Updates permissions for content administrator role for decision tree, decision tree branch, and decision tree conclusion content types.
- DP-6390: Switches access control rules for the Descendant Nodes block from role based to permission based check.
- We will be updating the SSL certificate in Acquia from an expired pilot.mass.gov cert to a current www.mass.gov cert.

### Removed

- DP-6377: Removes permissions from editor and author roles for the decision tree, decision tree branch, and decision tree conclusion content types. Adds permissions for the those content types to the tester role.

## [0.46.0] - October 24, 2017

### Added

- DP-6185 - Creates a new Featured services field that allows external links as well as links to internal content on organization pages. Copies data from existing field to the new field.
- DP-6214 - Adds permissions for decision tree, decision tree branch, decision tree conclusion content types.

### Changed

- DP-3505 - Revives the Welcome email to new users to include information about account set-up, including 2-factor authentication.
- DP-5639 - Improves the help text and description for the Issuer paragraph type, which appears on the Executive Order and Advisory content types.
- DP-5893 - Enhance authoring experience for News content type by setting specific field display based on selected options.
- DP-6037 - Always show content of the Content field in the page.
- DP-6168 - Updates entity bundles in Watch Content flag config to allow flagging of any content.
- DP-5163 - Configures the feedback, actions, and descendants dashboard.

### Post deploy

- DP-6185 - Run the following drush command to copy data from field_ref_actions_3 to field_links_actions_3: 'drush mass-org-featured-service-update'

## [0.45.1] - October 20, 2017

### Removed

- Remove password protection from https://www.mass.gov/hq2

## [0.45.0] - October 19, 2017

### Added

- DP-6130: Adds node id as hidden field to formstack feedback form
- DP-6135: Adds 'Decision Tree' content type.
- DP-6138: Adds 'Decision Tree Branch' content type.
- DP-6141: Adds 'Decision Tree Conclusion' content type.

### Updated

- DP-5515 - Updated the title "What would you like to do?" to a sentence case on the Service pages
- DP-5583: Fixes error in service page preprocess for iframe and adds configuration for iframe allowed URLs.
- DP-5909: Updates feedback from bottom margin and text area width.
- DP-6145: Fixes time callout icon layout bug

## [0.44.11] - October 18, 2017

### Added

- Add Amazon headquarters 2 RFQ site.

## [0.44.1] - October 14, 2017

### Removed

- DP-5585: Remove blurred image styles on homepage for smaller viewport widths.

## [0.44.0] - October 12, 2017

### Added

- DP-6136: Percussion documents delta migration (metadata from percussion 10.06.2017)

### Changed

- DP-5456: Style Service recommended pages like Action pages, blue action cards.

### Removed

- DP-6068: Remove bad links causing 404 errors from the title and added a filename/link to the file on the All Documents view in Drupal.

## [0.43.0] - October 10, 2017

### Added

- DP-5588 - Allow anonymous users to be able to view the unpublished links for the news and events content types.

### Changed

- DP-6098 - Adds Curated List as an allowed page type in a Legacy Redirect. This is a config-backport ticket: the change was done already on the live site, and this ticket is just to make the change permanent.
- DP-5224 - Replace the marker icon, which was originally applied, with the generic doc icon for files don't have their own icons for file links.
- DP-5760 - Fixes duplicate fax numbers displayed on map pages.

### Removed

### Post deploy

## [0.42.1] - October 6, 2017

### Added

### Changed

- DP-5876 - Prevent nodes from being saved if signees have no state organization.

### Removed

### Post deploy

## [0.42.0] - October 5, 2017

### Added

- DP-5190 - Authors can now filter by (aka search for) ALL content types that exist in the CMS. This fixes the issue that the “Person” and other content types were not appearing in the dropdown list.
- DP-5830 - Anonymous users (i.e. constituents) will notice that the feedback form has been added to service pages.
- DP-5898 - The Legacy Redirect content type now allows authors to point to additional type of Drupal page types: Forms plus the Law Library types.
- DP-6003 - Adds a "more news" listing to org pages at orgs/[org-name]/news.
- DP-6014 - Adds past event listing page for all page types which have upcoming event listing pages (org landing, service, location, regulation, advisory, executive order, and decision). Anonymous users (i.e. constituents) will notice links to the past event listing page appear just below the page title on the upcoming listing page as well as on the event parent's event listing component if there are no upcoming events scheduled.
- DP-5561 - Creates a new view of orphaned service detail pages. If a service detail has not been added to either the "Eligibility information" or "Key information links" fields on the service page then it will display in this list. Once logged in, Content admins and developers will see the "Orphaned service details" link under "My Content".

### Changed

- DP-5956 - Fixes permissions on the Decision and Advisory content types to allow the Author role to edit any content of that type, in line with all other content type permissions.
- DP-6018 - Updated text for when there are no results returned after a search is performed.
- DP-6022 - Styles search results count for mobile.
- DP-6059 - Enable the 'Label(s)' field for Documents so they can be added to Curated List dynamic lists.
- GH-1382 - (For devs) Redirects docs have been updated, see: https://github.com/massgov/mass/blob/develop/docs/redirects.md
- GH-1480 - Anonymous users (i.e. constituents) will notice: Single day event pages now show the event start and end date. Event teasers now show hours and minutes for event times (except for :00). Multi-day event teasers now show the event day and time (instead of just the time, which made them appear as recurring events).
- GH-1407 - Users will notice that when creating a Curate List > dynamic list, the label is now required field.
- DP-6015 - Cleaned up search filters code for mobile.

### Removed

### Post deploy

## [0.41.0] - October 3, 2017

### Added

- DP-4421: Add validation to make sure locations chosen for service or org map field have addresses.
- DP-4573: Add formatting and parentheses around organization page acronym.
- DP-4767: Create a custom module to handle displaying all Additional Resources associated with Service Page and Service Detail Page nodes, via the path `/path-to-node/resources`.
- DP-4880: Add mass custom token group and contact name with optional title token and updated Person Autolabel to use token. Content authors are now able to remove the stray comma that appears in the Display title of a Media contact (on a News item) when that contact has no title.
- DP-5677: Authors can add a 'see all' link for news items (which should point to a "News" search filtered by their org, when this functionality is available) to their Org page. This link will render (if it is populated) when they populate either the featured or automated news listings on their Org page.
- DP-5698: Add curated list node that allows editors to create manual and dynamic lists of content within multiple sections of a page.
- DP-5959: Add "no-results" content for new custom search module to be displayed when a user query comes up with nothing.

### Changed

- DP-3763: Change the default zoom value for Google Map in the page header to 15 from 12.
- DP-5614: Update Right-rail layout, basic page, and stacked layout content type labels to have prototype wording.
- DP-5703: When exporting documents to json the Licenses should return the
  right licenses urls. Also the field specify license should be hidden since
  it is not necessary.
- DP-5963: Hide search results from view while a search query is in progress.
- DP-5979: Style search tabs for mobile. Created small JS behavior to add supporting css class to achieve this functionality.

### Removed

- DP-4708: Remove the "address" section type from location details pages. This section never displayed on the front end, and now it is being removed as an option on the backend.

### Post deploying

- DP-5703:
  - Import configuration and clear cache to see the change.

## [0.40.1] - September 28, 2017

### Added

### Changed

- DP-5977: For news pages with external signees, we gracefully fail by not printing the org name.
- DP-5982: Revert More about section to user original view and not descendant manager.

### Removed

### Post deploy

## [0.40.0] - September 28, 2017

### Added

- DP-5825: Adds permissions to allow Editor and Author user roles to add and edit Regulation pages using the standard set of permissions.
- DP-5710: Creates the "News Type" filter on "News" search tab.
- DP-5770: Add the `mg_sign_up_link` metatag, update `mg_associated_organization` metatag on "event" and "location" content types.
- DP-5782: Adds org tab and news tab functionality to custom search work in progress module.

### Changed

- DP-5618: Updates how the document metadata field 'language' is displayed in the mass.gov data.json from the full string (e.g. 'English') to the key value (e.g. 'en'), so that the language metadata about documents can be correctly imported into Document Repository for archiving.
- DP-5619: Updates how the document metadata field 'frequency' value of 'once' is displayed in the mass.gov data.json from 'once' to 'irregular', so that the frequency metadata about documents can be correctly imported into the Document Repository for archiving.
- DP-5811: Makes recently updated organizations available to search filters.
- DP-5882: Updates order of search tabs and renames State Organizations.
- DP-5824: Updates logic to connect promoted results for "News" and "Organization" search tabs.
- DP-5873: Themed promoted search results for mobile.
- DP-5937: Updates markup around filter box to make form behavior more natural.

### Removed

### Post deploy

- DP-5847
  - `Run drush queue-run mass_content_api_descendant_queue.`
  - `Run drush queue-run mass_content_api_relationship_queue.`

## [0.39.0] - September 26, 2017

### Added

- DP-5148: Creates three page displays for lists of Service Page referenced entities: "How-To's", Key "Info Links", and "Related Services". Page displays are available respectively at: `/SERVICE_PAGE_PATH/tasks`, `/SERVICE_PAGE_PATH/need-to-know` and `/SERVICE_PAGE_PATH/related`.
- DP-5801: Makes "Topics", "Subtopics", "Organizations", and "Recent News" items available for search filters.
- DP-5847: Adds the 'Offered by' field on Service Pages to the descendant manager service.

### Changed

- DP-3504: Applies patch to `ga_login` contributed module to resolve issue _"Links to authentication apps need updates"._
- DP-5086: Migrates data from the `field_video_id` on the Video Paragraph into new video media entities. Links the new video entities to the existing video paragraphs. Alters the theming layer to use and render the new video media entity.
- DP-5413: Move Simple Oauth keypair outside the mass git repo; and apply appropriate file permissions on the key files (clears a logged error in drupal-watchdog.log file).
- DP-5110: Update search metadata for the Advisory content type.
- DP-5598: Updates search metadata for the Regulation content type.
- DP-5709: Update search metadata for the Org Page content type.

### Removed

- DP-5413: Removes `./credentials/` from the `mass` repo. The keypair that previously lived in this folder were moved to a private filesystem prior to this release.

### Post deploy

(DP-5847 - Optional: Cron will take care of these eventually, but it might be good to run them during release.)

1. Run "drush queue-run mass_content_api_descendant_queue"
2. Run "drush queue-run mass_content_api_relationship_queue"

## [0.38.2] - September 25, 2017

### Added

- DP-5148 - Developers will notice that there is a style plugin which can be selected to format a view row as an Mayflower image promo pattern.

## [0.38.1] - September 22, 2017

### Changed

- DP-5834 - To correct the search bar size and to display the 'Search' button on pages except for the homepage and search result page.

## [0.38.0] - September 21, 2017

### Added

- DP-5199 - Users will now be able to reference an Organization (or a Person) from Advisory and Executive Order "issuers".
- DP-5197 - Change the help text and the field order for Advisory content type. Change the reference title from "referenced legislation" to referenced sources.”
- DP-5117 - Adds a more accessible search suggestions for users.
- DP-5109 - Adds custom metatags for location, date, time, and body preview on 'event' pages.
- DP-4935 - Add generic address field to event content type in addition to reference to contact.
- DP-4554 - If a contact referenced from a How-To page has values for "More info link" populated, a link to that page will appear with that contact information with the link text: "Learn more about this organization”.
- DP-5108: Add custom metatags for body preview and short description on "Location Detail Page" nodes.

### Changed

- DP-5657 - Cleanup obsolete htaccess file rules, introduce Fast 404 pages and serve files (e.g. images, docs...etc) from prod while in local development environment (no need to sync files down to the local development environment).
- DP-5420 - Extends the descendant manager service for use with populating search metadata.
- DP-5277 - Change the field order, the field type and the help text in Decision content type. Change the reference title text to "Referenced Sources:" in the page.
- DP-5123 - Simplify our formstack compatibility code, update select box for custom search v2 (work in progress).
- DP-4343 - Move the more info field on the Location page to the correct location lower on the edit form
- DP-4937 - Users will notice that only News items of type Press Release and Press Statement require a "Media Contact".
- DP-4938 - Users will notice that the "Listing Description" field on the News content type is now optional.
- DP-4878 - Users will notice that on the Person content type, we now only require either a phone number or an email but not both.
- DP-5121: Themes custom search form after updating form's markup to match latest mockups.

### Removed

- DP-4438 - Removes the default link text for the "ONLINE SIGN UP / REGISTER LINK (OPTIONAL)" field and updates help text.

### Post-Deploy

- for DP-5420 - Extends the descendant manager service for use with populating search metadata.

## [0.37.0] - September 19, 2017

### Added

- DP-3414: Add Haitian Creole and Polish to Google Translator Dropdown
- DP-5107: Add custom metatags for address, body preview, directions url, and phone number on 'location' pages.
- DP-5112: Add custom metatags for overview, body preview, organization, and date on "Executive Order" pages.
- DP-5599: Add custom metatag for organization on service pages.
- DP-4211: Add iframe functionality to location detail page.

### Changed

- DP-5468: Updates xmlsitemap to display links to generated site map pages.

### Removed

- DP-4301: Remove '.00' from the fee display when a fee value ends with '.00' to match Mayflower.
- DP-5657: Remove 404 for images on Akamai.

## [0.36.0] - September 14, 2017

### Added

- DP-5111 - Add custom metatags for overview, organization, type, and date issued on "Decision" pages.
- DP-5389 - Adds permissions to release forms and guides for authors, editors and content administrators similar to other released content types.
- DP-5453 - Add version 2 custom search behind a custom module that needs to be enabled, to allow ongoign shipping of search features to staging environments without affecting production.
- DP-4714 - When search engines and other robots crawl our site, they will find helpful structured metadata (schema.org) for the “Event” content type. There is no impact to users or authors.
- DP-5526 - Allows nodes of type How-to Page to be added into the Featured and More Services sections on Organization Landing pages.
- DP-5423 - For Devs - See [docs/Change Log Instructions](https://github.com/massgov/mass/blob/develop/docs/Change%20Log%20Instructions.md) for an updated workflow to communicate your work's changes with fewer merge conflicts.
- DP-4422 - Only locations with addresses will appear in location listing pages.
- DP-5114 - Add custom metatags for title and URL on location listing pages.
- DP-5554 - Add random background images to search banner that alternate on each refresh.

### Changed

- DP-5629/DP-5526: Updates "Featured Services" field to support new node types, restricts "More Services" field to Service Page node type only.

### Removed

### Post-Deploy

- for DP-5453, ensure that the stable Search custom module is enabled, and the WIP Search module is disabled.
- for DP-5554, add the ten images and captain text per PR instructions.

## [0.35.0] - September 12, 2017

### Added

- DP-5503 - Adds permissions (add, delete, edit and revision) to the Author and Editor roles for the "Person" content type.
- Added URL validation to the iFrame paragraph type.
- Added iFrame feature to Location Detail and Service Detail Pages.
- MPRR-311 - Adds a new view: http://edit.mass.gov/admin/content/media/migrated to see a preview of files that will be in the csv.
- MPRR-311 - Get a csv file export of migrated files and classic mass.gov URLs at https://edit.mass.gov/admin/content/media/migrate-export.csv
- The Content API has been updated to list descendant nodes if they exist.
- DP-5288 - Authors, Editors and Content Admins now have permissions to work with the "News" and "Events" content types.
- DP-4723 - Adds bulk unwatch view link to watch email.
- DP-5100 - Adds custom metatags for "parent services", "body preview", "short description" and "parent topics" on Service Detail Pages. It also adds new child relationship between Service Page and Service Detail Pages.
- DP-5104 - Adds custom metatags for "URL", "Title" and "Site Slogan" on mass.gov homepage.
- DP-5101 - Adds custom metatags for parent services, body preview, short description, parent topics and key actions on How-To Pages.
- DP-5554 - Add random background images to search banner that alternate on each refresh.
- DP-5503 - Add permissions (add, delete, edit, and revision) to authors and editors roles for Person content type.
- Added URL validation to iframe paragraph
- Added iframe feature to location detail and service detail
- MPRR-311 - Add new view /admin/content/media/migrated to see a preview of files that will be in the CSV
- MPRR-311 - get a CSV export of migrated files and Classic Mass.gov URLs at /admin/content/media/migrate-export.csv
- The content api has been updated to list descendant nodes if they exist.
- DP-5288 - Authors, editors, and content admins now have permissions to work with News and Events.
- The content api has been updated to list descendant nodes if they exist.
- DP-5288 - Authors, editors, and content admins now have permissions to work with News and Events.
- The content api has been updated to list descendant nodes if they exist.
- DP-4723 - Add bulk unwatch view link to watch email.
- DP-5100 - Adds custom metatags for parent services, body preview, short description and parent topics on service detail pages. - Adds new child relationship between service page and service detail pages.
- DP-5520 - Adds a symlink to the favicon to remove 404 error.

### Changed

- DP-5494 - Allows the role "emergency alert publisher" to access the site when maintenance mode is enabled.
- DP-5520 - Updates themes favicon and sets them to use them.
- DP-5100 - Adds custom metatags for parent services, body preview, short description and parent topics on service detail pages. It also adds new child relationship between service page and service detail pages.
- DP-5104 - Adds custom metatags for URL, title and site slogan on homepage.
- DP-5101 - Adds custom metatags for parent services, body preview, short description, parent topics and key actions on how-to pages.

### Changed

- Hotfix - Removes the validation of only 12 items on the all tasks field on the Service Page.
- DP-5101 - Changed `mg_parent_service and mg_parent_topics` metatags to include JSON-encoded links to nodes rather than comma-separated string of node titles.
- DP-5494 - Allows the role Emergency Alert Publisher to access the site when maintenance mode is enabled.
- Change `.htaccess` to allow `test-503-response.php`.

### Removed

- DP-5294 - Removes the limit on the number of related services that can be added to an "Organization Page".
- DP-4487 - Removed the `Home` breadcrumb from the Topic, Organization and Location Page template.
- DP-5294 - Removes the limit on the number of related services that can be added to an Organization Page
- DP-5101 - Removes the Drupal view: `service_pages_associate_how_to_pages` in favor of using descendant manager.

## [0.34.2] - September 7, 2017

### Changed

- DP-5466 - Users can now enter in a zip or town in the town/zip location listing filter and press enter (i.e. without selecting an item from the autocomplete dropdown) to sort locations.

### Removed

## [0.34.1] - September 7, 2017

### Changed

- Add vary header for Akamai to cache our Drupal pages.
- Introduce a a test 503 error page that we can access: "test-503-response.php"

## [0.34.0] - September 6, 2017

### Added

- DP-4723 - Add bulk unwatch view link to watch email.
- DP-5160 - Add the traffic dashboard to the analytics tab provided by the route iframes module.
- DP-5381 - Adds footer script to enable google translate to display on search pages.
- Add Composer Autoloader Optimization to improve PHP classnames' resolution. There is no filesystem check needed once the class map is in place. All Acquia environments will now benefit from this optimization. Local development environment does not have this optimization; and should not (according to Composer documentation).
- DP-5105 - Adds custom metatags for body preview and short description to guide pages.
- DP-5102 - Add metatag tokens for "Organization Landing Page" content type.
- DP-5103 - Add metatag tokens for "Topic Page" content type.
- DP-4805 - Users can now see links for location detail, location, guides, topic, and service pages that will be reference in page header with relationship indicator.
- DP-4603 - (For Developers) the Mass.gov Drupal theme has updated the variable which points to Mayflower javascript assets. This path is used in any component JS which requires handlebars templating.

### Changed

- DP-5288 - Authors, editors, and content admins now have permissions to work with News and Events.
- The content api has been updated to list descendant nodes if they exist.
- DP-5235 - Change the Featured Topics field on Org Pages to allow adding pages of the type 'Topic Page' rather than the old, no longer used 'Topic' content type.
- DP-3660 - Remove link for contact us title on the location content type.
- DP-5457 - Mass.gov is now on Mayflower version 5.7.0. (See Release Notes)
- DP-5429 - Removes the limit set on entity reference fields from 10 to unlimited.

### Removed

- DP-5100 - Removes service_pages_associate_service_detail view in favor of using descendant manager.

## [0.33.3] - September 5, 2017

### Added

### Changed

- We've updated our google maps (i.e. on location and location listings pages) to use our premium account and avoid usage limits which would break map functionality.

## [0.33.2] - September 5, 2017

### Added

### Changed

- DP-5394 - Users visiting our site with the IE11 browser can use functionality on location listing pages.
- DP-5434 - Users will no longer see unpublished news items displaying in news listings (i.e. on Org pages).
- DP-5435 - Users visiting our site with a legacy version of the Internet Explorer browser will be informed that their browser is out of date and should be updated for the best experience on our site.

## [0.33.1] - September 1, 2017

### Added

### Changed

- DP-5229, DP-5339, DP-5415, DP-5410 - Users will notice the non-pilot logo added and the "back to classic" button removed on: the site header (which affects the homepage and content types) and on special pages like the search results, interstitial (i.e entering/leaving "pilot"), and error (i.e. 404 page not found) pages.

## [0.33.0] - August 31, 2017

### Added

- MPRR-483 - Add an admin screen to show all data.json feeds per organization /admin/content/data-json-summary.
- MPRR-311 - Add new view /admin/content/media/migrated to see a preview of files that will be in the CSV. - Get a CSV export of migrated files and Classic Mass.gov URLs at /admin/content/media/migrate-export.csv. - The content api has been updated to list descendant or ancestor nodes if they exist.
- DP-4895 - Create process to import redirects in bulk into Drupal.
- DP-5087 - Add `Watched Content` view that allows users to see all content they are currently watching.
- DP-5099 - Add custom metatags for URL and title to all content types, and adds custom metatags body preview, short description and key actions to service pages.
- DP-5030 - Add new moderation states and transitions for prepublished editorial states of all content types currently under moderation. Updates mass_flagging module to now trigger Watch email notifications for all non-prepublished nodes, viz., for all nodes once they are published for the first time.

### Changed

- MPRR-483 - Change primary /data.json feed to list all data.json feeds per organization, instead of all site-wide files.
- MPRR-496 - Update link formats in organization-specific data.json to handle internal and external links for systemOfRecord and conformsTo.
- MPRR-493 - Re-save the Media Browser view to prevent AJAX errors, re-enabling search and filtering while attaching document entities to Content.

### Removed

- DP-5176 - Remove limits in back end on number of items that can be entered on service page for "all tasks", "related services", and on service details page "additional resources" field

## [0.33.0] - August 31, 2017

### Added

- MPRR-483 - Add an admin screen to show all data.json feeds per organization /admin/content/data-json-summary.
- MPRR-311 - Add new view /admin/content/media/migrated to see a preview of files that will be in the CSV. - Get a CSV export of migrated files and Classic Mass.gov URLs at /admin/content/media/migrate-export.csv. - The content api has been updated to list descendant or ancestor nodes if they exist.
- DP-4895 - Create process to import redirects in bulk into Drupal.
- DP-5087 - Add `Watched Content` view that allows users to see all content they are currently watching.
- DP-5099 - Add custom metatags for URL and title to all content types, and adds custom metatags body preview, short description and key actions to service pages.
- DP-5030 - Add new moderation states and transitions for prepublished editorial states of all content types currently under moderation. Updates mass_flagging module to now trigger Watch email notifications for all non-prepublished nodes, viz., for all nodes once they are published for the first time.

### Changed

- MPRR-483 - Change primary /data.json feed to list all data.json feeds per organization, instead of all site-wide files.
- MPRR-496 - Update link formats in organization-specific data.json to handle internal and external links for systemOfRecord and conformsTo.
- MPRR-493 - Re-save the Media Browser view to prevent AJAX errors, re-enabling search and filtering while attaching document entities to Content.

### Removed

- DP-5176 - Remove limits in back end on number of items that can be entered on service page for "all tasks", "related services", and on service details page "additional resources" field

## [0.32.1] - August 31, 2017

### Added

### Changed

- DP-5137 - Users will notice that the video size (height and width proportions) has been fixed on Service pages.

### Removed

## [0.32.0] - August 30, 2017

### Added

- DP-5030: Adds new moderation states and transitions to allow for 'prepublished' node states and transitions.
- DP-4828 - Adds new entity reference to contact information on service detail content type, updated node--service-details.html.twig template, and configure Contact sidebar for Service Detail page.
- DP-5268 - Display short description on front end of News pages.
- DP-5286 - Add permissions to allow users with the Tester role to add and edit Regulation pages.
- DP-3872 - Display overview field on front end of Service Detail pages.
- DP-5154 - Add a view that allows the Content team to see a list of Legacy Redirects and related info about them.
- DP-5020 - Updated Event Associated pages (formerly Parent) to allow Decision, News, Advisory, Regulation and Executive Order entities.
- DP-3631 - Added upcoming events to Locations.
- DP-4895 - Add `path_redirect_import` Drupal module to facilitate legacy redirects import in bulk from a csv file.

### Changed

- DP-5030: Updates mass_flagging logic to exclude any 'prepublished' nodes from Watch notification emails.

- DP-5154 - Adds a view that gives our content team access to information about legacy redirects and the pages that are being redirected to. There are 2 displays, one a page with exposed filters, one a CSV export.
- DP-5268 - Add display for short description to front end of News content type
- DP-4820 - Add Contact field and theme on front end to Service detail page.
- DP-5020, 5022, 3631 - Add new entity reference to contact information on service detail content type, updated node--service-details.html.twig template, and configure Contact sidebar for Service Detail page.

### Changed

- DP-4948, 5287 - The Content API has been updated to provide descendant IDs with any results that are requested if the node has descendants defined. This is supported by a service, a queue, and a custom table to prevent the collection of descendants from negatively impacting site performance. A test interface has been created for this to make it easier to view the current relationships without needing to access them at the endpoint.
- DP-5235 - Change the allowed entity reference type from "topic" to "topic page" in the featured topics field on the Org landing page. The "topic" content type is no longer in use and this content type just needed to be updated to reflect the new content type.
- DP-5286 - Change permissions for the Tester role on the Regulation node type.
- DP-4420 - Updates markers in maps on Service Pages and Organizational Landing Pages, to show linked node titles of corresponding Location pages.
- DP-3872 – Fix the display issue for the overview field on service details
- Fixes sql-sync transferring cache tables.

### Removed

(Nothing)

## [0.31.0] - August 24, 2017

### Added

- DP-4590 - Add new field_video ('Video') entity reference to Video Paragraph (Inline Entity Form widget). This references a Video bundle. Add new field_video_description ('Transcript and Video Description') Text field to Video bundle. Add new, themed, media--media-video.html.twig template.
- DP-4719 - Add short description to service page front-end.
- DP-4584 - Adds custom metatags for parent topic on service pages, news type and date on news, advisory type on advisory pages, decision type on decision pages and date on regulation pages.

### Changed

- DP-4518 - Updates node--event.html.twig. Adds sideContent.contactList blocks so Contact information displays on narrow screens. Updates variable name from $sidebar to $sideContent.
- DP-4764 - Change service page to allow unlimited key information links and additional resources links
- DP-4441 - Users will notice various updates to field labels and help text throughout the Event content type.
- DP-4558 - Users will notice updated help text for various fields of the News content type (used for Speeches, Announcements, Press Releases, and Press statements).
- DP-5004 - Enables moderation for Advisory, Decision, Executive order, Legacy redirects.
- DP-4936 - Adjust ### to only show once and only if News type Press Release is selected.
- DP-4879 - In a News item, if an author removes the phone number for a media contact, no header for the phone number appepars in the front end.
- DP-4314 - Updates to Service Page sidebar, logo, offered by
- MPRR-409 - Users will notice that within 24 hours from this release, there will be around 155K documents migrated from legacy Mass.gov servers which can now be added as downloads, additional resources, etc. to new Mass.gov content. Developers will notice that these migrated files and any newly attached files are now organized by year and month.
- MPRR-489 - On Documents, the "License" field now has an additional field for a License URL, so licenses that use "Other" can specify a URL to make a complete data feed to the Document Repository.
- MPRR-457 - Added all new fields from Document to the data.json output like /api/v1/3111/data.json, so all metadata about documents can be imported to Document Repository for archiving.

## [0.30.1] - August 21, 2017

### Added

- DP-4507 - Adds options to organization page to allow editors and authors to feature 2 news items on an org page and display a list of up to the next 6 most recent news items related to the given organization.
- DP-4224 - Add permissions for content administrators and developers to create url redirects
- Added a new field, More Info Link (field_contact_more_info_link) to Contact Information content type, back end only will not display on the front end yet.

### Changed

- DP-4883 - Update signees field on news content type to pull stored image URI for ('Url To Image') from field_bg_wide to field_sub_brand on the organization content type.
- DP-4443 - Updates location details content type name to "Location detail". Changes cardinality on "field_location_details_links_5" and adds custom validation to limit the number of related items to 5.
- DP-4345 - Allows multiple contacts to location content types. Changes cardinality on field_ref_contact_info on location ct.
- Content authors and editors can no longer see "Regulation Page" content type as an option to create new content.
- DP-4533 - Resolves error on event pages, when referenced contact information node in address field does not have an address.
- DP-4518 - Updates node--event.html.twig. Adds sideContent.contactList blocks so Contact information displays on narrow screens. Updates variable name from $sidebar to $sideContent.
- DP-4557 Changed signees field on news content type to show both external and state org buttons so that users can better find the choice to pick internal state organization.
- DP-4557 - Changed signees field on news content type to show both external and state org buttons so that users can better find the choice to pick internal state organization.
- DP-4781 - Update flag form to use clearer text on what is actually occurring when submitting a form.
- DP-4592 - Add the more info link field to the Contact information content type, which enables authors to add an organization, location or service page.

### Removed

- DP-5020 - Removed upcoming events field from decisions, advisory and executive orders.

## [0.30.0] - August 17, 2017

### Added

- DP-4561 – Users can now choose a “type” of location. The default is a general location page. A user can select ‘park’ if they are making a park location page. Users select a value from a dropdown as they create/edit a “location page,” which will expose/hide certain additional fields. Note: a “type” must be selected the next time any existing or new location page is edited or created.
- DP-4342 - Users with permissions will now see an "all activities" field for "Location" pages with type: "Park"

### Changed

- Content authors and editors can no longer see "Regulation Page" content type as an option to create new content.
- DP-4533 - On the front end, event pages now load without error when they include a contact which has no address.
- Developers will notice that their Circle builds just got a little faster because we no longer pull files and documents down.
- Everyone can sleep a little easier tonight knowing that we are running the latest, greatest, and safest version of Drupal; with no visible changes to the back end (for authors, etc.) or frontend (constituents).

## [0.29.0] - August 16, 2017

### Added

- DP-4561, DP-4342 - Adds all activities field and add conditional fields to Location page.
- MPRR-224, MPRR-445 - Added data.json formatting for document endpoint, which exposes a feed of d\Documents as an API.
- MPRR-366 - Added confirmation message on Media Document insert and update.
- MPRR-367 - Added Patch for Core - Link Module help text, which improves authoring experience of link fields.
- MPRR-409 - Added Auto-populate Media Document Form Fields from User profile, which allows authors to fill in a default value for 4 Document fields from their user profile.
- MPRR-456, MPRR-482, MPRR-367 - Added fields to Media Document for MassDocs compatibility. All new fields are in an "Advanced" tab. Documents now uses user_organization taxonomy for compatibility with existing user profiles.
- MPRR-456 - Added default taxonomy terms update hook for Media, which adds select lists for Language, License and Document "Content Type".
- MPRR-466, MPRR-486 - Added Migrate class to import files from Percussion via CSV source. 155,000 Documents will be ported from Percussion, and future updates and additions can be imported with this migration.
- MPRR-466 - Added Patch for media_entity_document module to avoid errors during migration. Document entities can be migrated even if a Percussion file that returns a 404.
- MPRR-471 - Added "All Documents" admin screen at admin/ma-dash/documents - Authors and Editors can now view and filter Documents from a central location.
- MPRR-475 - Added link to create Document to node/add screen, so that Authors and Editors can more easily create standalone Documents.
- MPRR-484 - robots.txt - hide Media entities (Documents, Video) from search engines, as they currently have no Mayflower styling.
- MPRR-487 - Updated "Add Existing File" Media Browser - old browser showed only Title. New browser shows, Organization, Updated Date, and User, and can be filtered and sorted accordingly.
- MPRR-487 - Authors and Editors have permissions "create media, update media, access media overview" and editor has "edit any media", to bring Document workflow inline with content.
- DP-2373 - Adds regulation content type and theming.
- DP-4960 - Allow users with the role Tester to use the content type "Form page"

### Changed

- Update Flag Content form to make it more clear to users on what it does.
- Updates timestamp used within the body of Watch emails.
- DP-4938 - Make Listing Description an optional field in News content type.
- DP-4879 - Remove contact icon and label is contact value does not exist.

### Removed

- DP-5080 - Disable Watch notifications in lower environments.
- DP-4967 - Updates timestamp used within the body of Watch emails to correctly reflect when the action occurred.
- DP-4571 - Fixes the authoring dashboard views under My Content (My Work, Needs Review, All Content) all now work correctly, have minor usability tweaks, and include a functioning Content Type filter.
- DP-4303 - Updates text of headers and subheaders on Organizational Landing Pages.

### Post Deploy

Follow post deploy step listed in the PR (https://github.com/massgov/mass/pull/925) to add the migration source data to the files directory.

## [0.28.1] - August 11, 2017

### Changed

- Revert to legacy iFrame solution to fix home + other pages.
- Fix with redeployment of DP-4285: Adds end date to field_event_date on events. Front end does not render end date yet.
- Makes the Audience field optional. Limits the 'Primary Audience' field to administrator users only.

### Post Deploy

Follow post deploy steps listed in the PR (https://github.com/massgov/mass/pull/1089) to re-add the "announcing pilot.mass.gov" youtube video to the home page.

## [0.28.0] - August 10, 2017

### Added

- Added the route_iframes module to support dashboards as a tab / local task on nodes / pages.
- DP-4211 - Add iframe paragraph to service details and location details.
- DP-4179 - (for devs) Add docs for updating dependency packages to repo readme + mayflower docs
- Adds a "category" metatag when viewing most nodes. The category is dynamically determined based on the content type and will allow future filtered searches using Google CSE.
- Adds a "Primary audience" field to Guide Page, How-to Page, Service Page, and Service Detail Page content types. The value of this field is used to populate an "audience" metatag for those pages, allowing Google CSE to filter by audience.
- Added notification message for users automatically added as content watchers.
- Add a rich text field before the fees field on the How-to page

### Changed

- Updated release docs to reflect deploying tag command run from the VM.
- DP-4416 - Changed label on "Related Parks" to "Related Locations"
- DP-5004 - Enables moderation for Advisory, Decision, Executive order, Legacy redirects.
- DP-4305 - Removes Inline Form Errors error message from the username field to replace the default invalid login message at top of the user login page.
- Updated capitalization of "Next Steps" and "More Info" in header/jump menu on How-to pages
- Fix bug where pages with no table data (i.e. How-To's with no fees) were not loading.
- Fixed email headers being used to send out Watch notifications from mass_flagging module
- DP-4285 - Adds end date to field_event_date on events. Front end does not render end date yet.
- Updated mass_flagging module to send Watch emails on local or Prod environments only.
- DP-5075 Allow authors to see help text for Watching feature.

### Removed

- DP-4211 - Add iframe paragraph to service details and location details.

## [0.27.0] - August 8, 2017

### Added

- Added notification message for users automatically added as content watchers.
- Added iframe paragraph to service details and location details.
- Added basic documentation for "Watching" for users
- Added Theming / Validation for Decisions
- Added Theming / Validation for Policy Advisory
- Added Theming / Validation for Executive Orders

### Changed

- Changed label on "Related Parks" to "Related Locations"
- Removed a bar showing on pilot.mass.gov header
- Removed a bar showing on pilot.mass.gov header
- Fixed Location pages show page level alert even with no alert content
- Fixed Pilot.mass.gov design is changing when choosing a different language
- Fixed Topic page title in the cards are not wrapping with IE11

### Removed

None.

## [0.26.0] - August 3, 2017

### Added

- DP-4565 - Implement structured data (schema.org) for "Topic Page". When you view the source code of a Topic page, you will now see the JSON-LD object that maps to this page type.
- DP-4521 - Added documentation on how to map content types to schema.org.
- DP-3882 - (For devs) `composer.json` and `composer.lock` are now validated on CircleCI under the "test" section of the `circle.yml` file (See "Troubleshooting" in README.md).
- DP-4809 - Changed permissions to allow authors and editors to use new content type location details.
- DP-3882 - `composer.json` and `composer.lock` are now validated on CircleCI under the "test" section of the `circle.yml` file (See "Troubleshooting" in README.md).
- Added notification message for users automatically added as content watchers.
- Change merge driver to union for changelog so we don't always have conflicts.

### Changed

- DP-4589 - Added custom template suggestion for Flag Content contact form to ensure proper textarea rendering.
- DP-4721 - Update Mass Watching module help page so users understand how the module works.
- DP-3882 - (For devs) Halt `composer install` operation on CircleCI when a referenced patch fails to install (See "Troubleshooting" in README.md).
- DP-4589 - Added custom template suggestion for Flag Content contact form to ensure proper textarea rendering.
- DP-4773 - Hides flagging link container if user is not authenticated.

### Removed

None.

## [0.25.0] - Aug 1, 2017

### Added

- Added dashboard admin/ma-dash/service-content to see content related to a service.
- "Organization Pages" and "Service Pages" now have event listing.
- New "Tester" role for select users to try new features.
- New oauth-secured content metadata API at `/api/v1/content-metadata`

### Changed

- Fix bug where pages with no table data (i.e. How-To's with no fees) were not loading.

## [0.24.1] - July 27, 2017

### Added

- Adds form_page content type.
- Adds custom field type for form embed.
- Modifies topic page to allow it to display as a section landing. Requires post update of.

### Changed

- Removes "publish" and "unpublish" actions from admin/content and adds proper Workbench Moderation states instead.
- Add logic around link fields to resolve error where node is deleted but link still exists.
- Fixed url encoding for the 'target' property value on 'How-To Page' content type.
- Refactor contact links for contact_information pages
- Update mayflower to 5.5.0
- Watch notification emails include revision author

## [0.24.0] - July 25, 2017

### Added

- Implemented structured data (schema.org) for the following three content types: Fee, Guide and Location. When you visit these page types, they now render JSON-LD (viewable in source page source code).
- Introduced "Content Flagging" capability. As a mass.gov internal user, I can flag a piece of content that appears inappropriate or incorrect.

## [0.23.1] - July 20, 2017

### Added

- DP-0000 - Add "stamp-and-deploy" script. Under "deployment" in "circle.yml", the "commands" section for branches that CircleCI acts upon, are now in a bash script "./scripts/stamp-and-deploy". (Sorry, no Jira ticket - Youssef & Moshe)
- DP-4179 For devs: update project documentation with steps to update a dependency, including some Mayflower-specific docs.

### Changed

- DP-0000 - Fine-tune branch name regex for CircleCI; i.e. act on any branch name that is not "develop". Only push to Acquia if it is not "develop". (Sorry, no Jira ticket - Youssef & Moshe)
- Adds "stamp-and-deploy" script. Under "deployment" in "circle.yml", the "commands" section for branches that CircleCI acts upon, are now in a bash script "./scripts/stamp-and-deploy".
- Adds email notifications for watchers of content
- Adds preliminary configuration for Advisory content type
- Adds preliminary configuration for Decision content type

### Changed

Fine-tune branch name regex for CircleCI; i.e. act on any branch name that is not "develop". Only push to Acquia if it is not "develop".

## [0.23.0] - July 18, 2017

### Added

- When editing a piece of content, you are automatically subscribed to get email notifications when that content changes in the future.
- Executive Orders are now a content type! The Governor will have a field day.
- Devs: The relationship between Mayflower and OpenMass is now documented.
- Devs: Config mistmatches are checked at build time.
- Added content fields for assigning labels such as top content, sticky content, and secretariat ownership.

### Changed

- Content cards on topic pages are strictly only able to link to other topic pages, organizations, and services. This helps keep topic pages clean and structured.
- Legacy redirects cannot be used more than once.
- Devs: Composer state is fixed. Composer install works again.

## [0.22.3] - July 13, 2017

### Added

- On edit.mass.gov, you can manually watch and unwatch content. P.S. No watch notifications are sent yet, but you can sign up to watch something.
- Metadata for Service Details is published via a Schema.org mapping (good for search engines)
- Photo credits added to images in hardened content types.

### Changed

- In the edit experience, when a required piece of content hasn't been added yet, it says "No <part> added yet." For example, on a How-to page, if no next steps are added, it'll say "No next step added yet".
- Accessibility of the directions link on Locations pages is improved.
- An unlimited number of tasks can be added to Service pages
- Press releases changed to news, with some new fields too!

## [0.22.2] - July 11, 2017

### Changed

- Location pages no longer render blank location-specific page level alert by default (i.e. when there is no location alert content).

## [0.22.1] - July 10, 2017

### Changed

- Mayflower module `Organisms::preparePageBanner` method now supports multiple image styles for the background image. This fixes the rendering issues for banner images on Topic pages.

## [0.22.0] - July 05, 2017

### Added

- List service details parent pages on edit page.
- Add static release notes content type
- Add the location listings page with functional proximity search and checkbox filters
- Know what's happening? Now you do thanks to the Events content type
- Press release content type added, including adding an associated state organization.
- Hardened content types can be added to related content in stacked rows.
- Metadata for Service pages now exposed in the skin of Schema.org classes
- An organization (gov agency) can be associated with a user account. Information can be exported to CSV that shows all content a created by a given user and an org.

### Changed

- Updated Core Drupal to 8.3.4 https://www.drupal.org/project/drupal/releases?api_version%5B%5D=7234
- Add schema_metatag module to improve Drupal functionality with Schema.org
- Redirect page auto generates title confirms link start with mass.gov
- Add flag module that will allow us to create Flagging and watching feature
- Make searches more accessible
- Make service page links only link to content types. E.g. The All tasks field should only return how-to pages
- Make language selector box show in IE10-11.
- Add Fees to the sticky nav when the field is populated.
- Resolves error messsage on creating on location pages
- Content cards can now be organized in groups.
- XML sitemap is configurable post-deploy
- Embed images from rich text editors save the alt and title values
- It's movie time! YouTube links appear on Service pages w/ video links.
- Devs only: Git hub tag is no longer needed for release
- Devs only: Remove limitation on local builds making work more efficient
- Devs only: Local environment no longer requires importing own aliases
- Devs only: Make debug available via the command line
- Devs only: CIMY removed from deployment flow
- On Guide pages, there can now be as many key actions as you want and the editing experience has flatter navigation (has fewer tabs)
- Help text has been improved on Services content type.

### Removed

- Remove error message when better description is deleted
- Remove obsolete shortlinks
- Devs only: Make configuration changes easier to export / import.
- Remove extra decorative line when contact group does not have a title

## [0.21.2] - June 20, 2017

### Changed

- Users can only make edits to mass.gov from approved networks, which should make it less likely for intruders to modify the site.
- Patched a security vulnerability with two factor authentication

### Removed

- How-to pages no longer show contacts in too many places and the sidebar alignment and headings are fixed.
