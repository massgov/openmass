@api
Feature: Developer
  As a security minded individual,
  I want a developer role to do everything an admin can do except: manage users, themes, modules, and update the site
  so I can have granular roles and permissions.

  #http response 403 is access denied
  Scenario: Verify developer user cannot add modules
    Given I am logged in as a user with the "developer" role
    And I am on "/user"
    Then I click "Edit"
    Then I should see the dashboard tabs
    # Developer cannot enable/disable modules.
    Then I should not have access to "/admin/modules"
    # Developer cannot change the theme.
    And I should not have access to "/admin/appearance"
    # Developer cannot manage users.
    And I should not have access to "/admin/people"
    # Developer cannot run update.php
    And I should not have access to "/update.php"
    # Developer can create interstitials
    And I should have access to "/node/add/interstitial"
    # Developer can create error pages.
    And I should have access to "/node/add/error_page"
    # Developer can create emergency alerts.
    And I should have access to "/node/add/alert"
    # Developer can create stacked layouts.
    And I should have access to "/node/add/stacked_layout"

    # Developer can administer security settings and checks
    Then I should not have the "administer modules, administer software updates, administer themes, administer users" permissions
    # Developer user does not have permission to change site code or administer users
    Then I should have the "access security review list, run security checks, administer seckit" permissions
