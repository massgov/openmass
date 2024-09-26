# Peer review checklist

## General

- [ ] Does the PR include a changelog.yml file ([see instructions](changelog_instructions.md))?
- [ ] Does the code actually work? Does it perform its intended function, is the logic correct, etc.
- [ ] Does the code break other stuff? (Acknowledge that you can't test everything, but think about it). A common offense: custom rendering of a field that is used in more than one place or in more than one context.
- [ ] Is all the code easily understood? Does anything confuse you or give you pause?
- [ ] Does the code make assumptions about the state of the database? E.g. that content exists and has not been deleted; that the number of items in a particular field falls within a certain range; that nodes exist at a particular address or number.
- [ ] Is the approach used to solve a given problem (check a permission, check for node existence, etc) consistent with the methods used to solve the same problem elsewhere in the codebase?
- [ ] Does the code pass all automated tests & code convention checks?
- [ ] Is there any redundant or duplicate code?
- [ ] Is there any commented out code?
- [ ] Do loops have a set length and correct termination conditions?
- [ ] Can any of the custom code be replaced with library functions?
- [ ] Can any logging or debugging code be removed?
- [ ] Are database queries reasonably efficient? (Watch out for queries executed within loops.)
- [ ] Does the code do more than what is called for by the ticket? If so, why? And update the ticket so we have a record of why.

## Security

- [ ] Are all data inputs checked ( & handled for varying type, length, format, and range) and encoded?
- [ ] Where third-party utilities are used, are returning errors being caught?
- [ ] Are output values checked and encoded?
- [ ] Are invalid parameter values handled?
- [ ] Are secrets being committed into a public source code repository?

## Documentation

- [ ] PRs that only change documentation may skip peer review if desired.
- [ ] Do comments exist and describe the intent of the code?
- [ ] Are all functions commented?
- [ ] Is any unusual behavior or edge-case handling described?
- [ ] Is the use and function of third-party libraries documented?
- [ ] Are data structures and units of measurement explained?
- [ ] Is there any incomplete code? If so, should it be removed or flagged with a suitable marker like ‘TODO’?

## Testing

- [ ] Is the code testable? i.e. don’t add too many or hide dependencies, unable to initialize objects, test frameworks can use methods etc.
- [ ] Do tests exist and are they comprehensive? i.e. has at least your agreed on code coverage.
- [ ] Do unit tests actually test that the code is performing the intended functionality?
- [ ] Are arrays checked for ‘out-of-bound’ errors?
- [ ] Could any test code be replaced with the use of an existing API?

## Drupal-Specific

- [ ] Is there any config (new or altered \*.yml files) included that doesn't seem relevant to this feature?
- [ ] Are there any changes in how dependencies of the project are managed? (i.e composer.json/composer.lock has changed)
- [ ] Does the code (particularly Views) expose private information to the public?
- [ ] Is CSS and JS being loaded using Drupal mechanisms to permit aggregation and caching?
- [ ] If a module is being added, is it widely used and well maintained? What does the issue queue look like?
- [ ] If custom code is being added, does it replicate functionality that could be provided by a module?
- [ ] If custom queries are being used, are the Drupal query methods being employed (to help with security and portability)?
- [ ] If this is a new content type, have you followed the [Content Type Checklist](content-type-checklist.md)? Have access controls been considered and implemented with tests?
- [ ] Is the logic in the right place?
- [ ] If the code implements functional behavior that should occur regardless of the theming, it should be in a module. (We have not been good about this).
- [ ] If it is purely presentational, can the logic be in the template? (unless it is Mass.gov specific logic and a Mayflower template).
- [ ] If Drupal core was updated, do all changes from the scaffold files apply to our version-controlled files as well? (e.g. .htaccess, settings.php, services.yml, development.services.yml, etc.)

## Mayflower Integration
All openmass feature development PRs that require Mayflower changes must pass all these checks before **squashing and merging** into develop:
- [ ] Both the Mayflower and Openmass PRs reviewed and approved
- [ ] **Squash and merge** the corresponding Mayflower PR into develop
- [ ] After the Mayflower develop CircleCI tasks are done, in the Openmass branch, resolve merge conflicts with the develop branch and run `composer require massgov/mayflower-artifacts:dev-develop`.
- [ ] Commit and push up the composer.lock changes. (mayflower-artifacts should be pointing to the latest `dev-develop` version)
- [ ] All circleCI tests are green on the Openmass branch

## JavaScript/CSS update
- [] If JavaScript/CSS were modified - does version of the library definition incremented?

Because we are no longer compiling Mayflower changelogs for each Openmass release, we expect you to follow the branch naming conventions, include the ticket number in the branch names so they appear in JIRA. Please also link to the corresponding Mayflower PR in the Openmass PR description. Doing so will help others review the PRs together and find the related PRs quickly in case where a hotfix is needed.
