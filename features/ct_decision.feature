@api
Feature: Decision Content type
  As a MassGov alpha content editor,
  I want to be able to add decision pages,
  so that I can inform people about board decisions.

  Scenario: Verify that the decision content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "decision" content has the correct fields
