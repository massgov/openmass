@api
Feature: Icons taxonomy
  As a MassGov content editor,
  I want to be able to select an appropriate "icon" to use in the header of my content,
  so that I can add pleasing and informative design elements to the content.

  Scenario: Verify that the icons taxonomy has the right fields
    Given I am logged in as a user with the "administrator" role
    Then the taxonomy vocabulary "icons" has the fields:
    | field               | tag       | type      | multivalue  | required |
    | field-sprite-name   | input     | text      | false       | true     |
    | field-sprite-type   | select    |           | false       | false    |
