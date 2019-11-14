@api
Feature: Admin
  As an anonymous user,
  I want to see only one message informing me as to why I can not log in.

  Scenario: Verify only one error message appears when invalid credentials are entered
    Given I am an anonymous user
    When I go to "/user/login"
    And I fill in "name" with "test_name"
    And I fill in "pass" with "invalid_password"
    And I press "Log in"
    Then I should see text matching "Unrecognized username or password."
    And I should not see text matching "error has been found"