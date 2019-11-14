@api
Feature: Fee Content type
  As a MassGov alpha content editor,
  I want to be able to add reusable fee content,
  so that fee info for an organization can be consistent across the site.

  Scenario: Verify that the fee content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "fee" content has the correct fields

  Scenario: Verify that the fee content type can't be viewed by anonymous visitors.
    Given I am viewing a published "fee" with the title "Test Fee"
    Then the response status code should be 404
