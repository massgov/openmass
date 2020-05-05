@api
Feature: Decision Tree Branch Content type
  As a MassGov alpha content editor,
  I want to be able to add decision tree branch pages.

  Scenario: Verify that the decision tree branch content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "decision_tree_branch" has the fields:
      | field                        | tag        | type             | multivalue |
      | field-multiple-answers       | paragraphs | multiple-answers | false      |
      | field-branch-disclaimer      | textarea   | text             | false      |
      | field-description            | textarea   | text             | false      |
      | field-more-info              | paragraphs | more-info        | false      |
