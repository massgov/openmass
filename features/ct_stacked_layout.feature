@api
Feature: Stacked Layout Content type
  As a MassIT editor,
  I want to create a page with banded content in a prototyping tool,
  so I can test how banded content can and should be used. This can used to start prototyping guides, for example.

  Scenario: Verify that the Stacked Layout content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "stacked_layout" has the fields:
    | field                 | tag        | type         | multivalue |
    | field-lede            | textarea   |              | false      |
    | field-label           | input      | text         | false      |
    | field-photo           | input      | submit       | false      |
    | field-bands           | paragraphs |              | false      |
    | field-related-content | input      | text         | true       |

  Scenario: The stacked layout has correct markup.
    Given "stacked_layout" content:
      | title                              |
      | Behat Test: Related stacked layout |
    And I am viewing a "stacked_layout" content:
      | title                 | Behat Test: Stacked layout         |
      | field_lede            | Lede: lorem ipsum                  |
      | field_related_content | Behat Test: Related stacked layout |
      | moderation_state      | published                          |
    Then I should see the correct markup for the illustrated header
    And I should see the correct markup for the related guides

  Scenario: Verify that pathauto patterns are applied to Stacked Layout nodes.
    Given I am viewing an "stacked_layout" content with the title "Run the Test Suite"
    Then I am on "run-test-suite"

  @caching @dynamic_cache
  Scenario: Verify that the stacked layout content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing a "stacked_layout" content with the title "test stacked_layout"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
