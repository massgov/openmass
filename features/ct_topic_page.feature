@api
Feature: Topic Page Content type
  As a MassGov alpha content editor,
  I want to be able to add topic pages,
  so that I can collect information about topics.

  Scenario: Verify that the topic page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "topic_page" content has the correct fields
    And "content_card_group" paragraph has the correct fields

  Scenario: Verify that pathauto patterns are applied to Topic Page nodes.
    Given I am viewing an "topic_page" content with the title "Test Topic Page"
    Then I am on "topics/test-topic-page"

  @caching @dynamic_cache
  Scenario: Verify that the topic page content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    And I am viewing a "topic_page" content with the title "test topic page"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
