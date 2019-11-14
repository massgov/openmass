@api
Feature: Curated List Content Type

  Scenario: Verify that the advisory content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "curated_list" content has the correct fields

  @caching
  Scenario: Curated list automatic sections should be updated in real time.
    Given a label term with the name MyTestLabel
    And I am viewing a decision:
      | title                | Labelled decision |
      | field_reusable_label | MyTestLabel       |
      | moderation_state     | published         |
    And a curated list "MyTest" with an automatic section "MyTestLabel"
    Then I should see "Labelled decision"
    Then push the current path onto the stack
    And I am viewing a decision:
      | title                | Other decision    |
      | field_reusable_label | MyTestLabel       |
      | moderation_state     | published         |
    Then pop the path off the stack
    And I should see "Labelled decision"
    And I should see "Other decision"

  @caching @dynamic_cache
  Scenario: Verify that the curated list content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "curated_list" content with the title "test curated list"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached


