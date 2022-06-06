@api
Feature: Decision Tree Content type
  As a MassGov alpha content editor,
  I want to be able to add decision tree pages.

  Scenario: Verify that the decision tree content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "decision_tree" has the fields:
      | field                        | tag        | type          | multivalue |
      | field-description            | textarea   | text          | false      |
      | field-disclaimer             | input      | text          | false      |
      | field-service-ref-services-6 | input      | text          | false      |
      | field-start-button           | paragraphs | start-button  | false      |
