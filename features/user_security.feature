@api
Feature: User Security
  As a MassGov manager,
  I want to ensure that the website has secure login,
  so that the website is less likely to be hacked or brought down.

  Scenario: Verify that users can't request a new password
    Given I am an anonymous user
    And I am on "/user/password"
    Then I should see the 403 error page

  Scenario: Verify anonymous_user cannot create content of these types
    And I should not have access to "/node/add/action"
    And I should not have access to "/node/add/org_page"
    And I should not have access to "/node/add/alert"
    And I should not have access to "/node/add/error_page"
    And I should not have access to "/node/add/interstitial"
    And I should not have access to "/node/add/page"
    And I should not have access to "/node/add/stacked_layout"

  Scenario: Verify anonymous user cannot discover users through password reset
    When I go to "user/reset/1/1510860000/xxxx"
    Then the response status code should be 403
    When I go to "user/reset/999/1510860000/xxxx"
    Then the response status code should be 403
    When I go to "user/reset/1/1510860000/xxxx/login"
    Then the response status code should be 403
    When I go to "user/reset/999/1510860000/xxxx/login"
    Then the response status code should be 403
