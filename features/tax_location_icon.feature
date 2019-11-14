@api
Feature: Location Icon taxonomy
  As a MassGov content editor,
  I want to be able to select an appropriate "location_icon" to use in location content,
  so that I can add pleasing and informative design elements to the content.

  Scenario: Verify that the location icons taxonomy has the right fields
    Given I am logged in as a user with the "administrator" role
    Then the taxonomy vocabulary "location_icon" has the fields:
    | field               | tag       | type      | multivalue  | required |
    | field-sprite-name   | input     | text      | false       | true     |

  Scenario: Verify anonymous users cannot view location icon pages
    Given I am viewing a "location_icon" term with the name "Test Location Icon"
    Then the response status code should be 404