@api
Feature: Contact Info Content type
  As a MassGov alpha content editor,
  I want to be able to add reusable contact info content,
  so that contact info for an organization can be consistent across the site.

  Scenario: Verify that the contact info content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "contact_information" content has the correct fields
    And "address" paragraph has the correct fields
    And "fax_number" paragraph has the correct fields
    And "phone_number" paragraph has the correct fields
    And "links" paragraph has the correct fields
    And "hours" paragraph has the correct fields

  Scenario: Verify that the contact info content type can't be viewed by anonymous visitors.
    Given I am viewing a published "contact_information" with the title "Test Contact Info"
    Then the response status code should be 404
