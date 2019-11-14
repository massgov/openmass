@api
Feature: User Profile
  As a MassGov manager,
  I want to ensure that the user profile has the right fields,
  so that the website can use those default values.

  Scenario: Verify that MassDocs fields are in user profile.
    Given I am logged in as a user with the "administrator" role
    And I am on "user/1/edit"
    And I should see "Organization"
