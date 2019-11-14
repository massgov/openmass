@api
Feature: Admin
  As a admin,
  I want to have the most powerful permission so that there is clear separation of duties in roles.

  #http response 200 is success
  Scenario: Verify admin user can perform admin tasks
    Given I am logged in as a user with the "administrator" role
    # Can add modules
    Then I should have access to "/admin/modules"
    # Can change theme settings
    And I should have access to "/admin/appearance"
    # Can manage users
    And I should have access to "/admin/people"
    # Can run updates
    And I should have access to "/update.php"
    # Can create error pages
    And I should have access to "/node/add/error_page"
    # Can create interstitials
    And I should have access to "/node/add/interstitial"

  Scenario: Verify that developer user does not have permission to change site code or administer users
    Then the "administrator" role should have the permissions:
      | Permission                  |
      | administer modules          |
      | administer software updates |
      | administer themes           |
      | administer users            |

  Scenario: Verify that the configuration is correct
    Then dblog should not be installed
    And dynamic_page_cache should be installed
    And the config "system.performance" "cache.page.max_age" should equal "604800"
