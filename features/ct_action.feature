@api
Feature: Action Content type
  As a MassGov alpha content editor,
  I want to be able to add content for actions (a bedrock of the alpha release) for pre-determined journeys,
  so that I can help Bay Staters get the best information they need to fulfill basic tasks.

  Scenario: Verify that the action content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "action" has the fields:
    | field                        | tag        | type                       | multivalue |
    | field-lede                   | textarea   |                            | false      |
    | field-external-url           | input      | url                        | false      |
    | field-alert-dropdown         | select     |                            | false      |
    | field-alert-text             | textarea   |                            | false      |
    | field-alert-link             | input      | text                       | false      |
    | field-search                 | textarea   |                            | false      |
    | field-action-details         | paragraphs | action-step                | false      |
    | field-action-details         | paragraphs | action-step-numbered-list  | false      |
    | field-action-details         | paragraphs | callout-link               | false      |
    | field-action-details         | paragraphs | callout-button             | false      |
    | field-action-details         | paragraphs | callout-alert              | false      |
    | field-action-details         | paragraphs | file-download              | false      |
    | field-action-details         | paragraphs | iframe                     | false      |
    | field-action-details         | paragraphs | rich-text                  | false      |
    | field-action-details         | paragraphs | stat                       | false      |
    | field-action-details         | paragraphs | subhead                    | false      |
    | field-action-details         | paragraphs | map                        | false      |
    | field-action-details         | paragraphs | hours                      | false      |
    | field-action-details         | paragraphs | pull-quote                 | false      |
    | field-action-header          | paragraphs | contact-group              | false      |
    | field-action-banner          | paragraphs | full-bleed                 | false      |
    | field-action-sidebar         | paragraphs | contact-group              | false      |
    | field-action-sidebar         | paragraphs | quick-action               | false      |

  Scenario: Verify that pathauto patterns are applied to action nodes.
    Given I am viewing an "action" content with the title "Run the Test Suite"
    Then I am on "run-test-suite"
