@api
Feature: Decision Tree Conclusion Content type
  As a MassGov alpha content editor,
  I want to be able to add decision tree conclusion pages.

  Scenario: Verify that the decision tree content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "decision_tree_conclusion" has the fields:
      | field                        | tag        | type          | multivalue |
      | field-add-video              | paragraphs | video         | false      |
      | field-description            | textarea   | text          | false      |
      | field-decision-actions       | input      | text          | true       |

