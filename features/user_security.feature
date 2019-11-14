@api
Feature: User Security
  As a MassGov manager,
  I want to ensure that the website has secure login,
  so that the website is less likely to be hacked or brought down.

  Scenario: Verify that user names cannot be discovered with password reset messages.
    Given I am an anonymous user
    And I am on "/user/password"
    When I fill in "name" with "foobar test xyz"
    And I press "Submit"
    Then I should see "Further instructions have been sent to your e-mail address"

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

  Scenario: Verify anonymous users cannot access tfa login page without a token
    Then I should not have access to "/tfa/1/xxx"

  Scenario: Verify authenticated users cannot see the TFA pages for other users
    Given I am logged in as a user with the "authenticated" role
    Then I should not have access to "/tfa/1/xxx"
    And I should not have access to "/user/1/security/tfa"

  Scenario: Verify anonymous user cannot discover users through tfa settings
    When I go to "tfa/1/xxxx"
    Then the response status code should be 403
    When I go to "tfa/999/xxxx"
    Then the response status code should be 403
