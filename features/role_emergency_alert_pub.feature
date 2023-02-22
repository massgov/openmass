@api
Feature: Emergency Alert Publisher
  As a emergency alert publisher member,
  I want a role to be able to create / edit / delete only alert content types
  so I can manage the alerts on the site.

  Scenario: Verify that emergency alert pub member can only see content menu item
    Given I am logged in as a user with the "emergency_alert_publisher" role
    And I am on "admin"
    Then I should not see the link "Manage" in the "toolbar" region
#    Then I should see the link "Content" in the "toolbar" region

  Scenario: Verify that the emergency alert pub team user can access key pages:
    Given I am logged in as a user with the "emergency_alert_publisher" role
    Then I should have access to "/admin/content"
    Then I should have access to "/node/add"
    Then I should have access to "/node/add/alert"
    Then I should not have access to "/admin/people"
    Then I should not have access to "/node/add/action"
    Then I should not have access to "/node/add/page"
    Then I should not have access to "/node/add/stacked_layout"






