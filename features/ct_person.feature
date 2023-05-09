@api
Feature: Person Content type
  As a MassGov alpha content editor,
  I want to be able to add reusable contact info for people used as media contacts in press releases,
  so that contact info for media contacts is reusable and can be updated in all places used at once.

  Scenario: Verify that the person content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "person" content has the correct fields

  Scenario: Verify that the person content type can't be viewed by anonymous visitors.
    Given I am viewing a published "person" with the title "Test person"
    Then the response status code should be 404
