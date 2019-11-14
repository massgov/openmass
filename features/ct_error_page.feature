@api
Feature: Error Page Content type
  As a MassGov alpha content editor,
  I want to be able to add content for error page.

  Scenario: Verify that the error page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "error_page" has the fields:
    | field                   | tag        | type          | multivalue | required |
    | field-error-code        | input      | text          | false      | false    |
    | field-error-label       | input      | text          | false      | false     |
    | field-error-title       | input      | text          | false      | true     |
    | field-error-message     | textarea   | text          | false      | true     |
    | field-include-search    | input      | checkbox      | false      | false    |
    | field-helpful-links     | input      | text          | true       | false    |

  Scenario: Verify that pathauto patterns are applied to error page nodes.
    Given I am viewing an "error_page" content with the title "Run the Test Suite"
    Then I am on "run-test-suite"

