@api
Feature: Executive Order Page Content type
  As a MassGov alpha content editor,
  I want to be able to add executive order pages,
  so that I can inform people about event information and enable them to take action for it.

  Scenario: Verify that the event content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "executive_order" content has the correct fields

  Scenario: Verify that pathauto patterns are applied to Executive order+nodes.
    Given I am viewing an "executive_order" content with the title "Test Executive Order"
    Then I am on "executive-orders/test-executive-order"

  Scenario: Verify that the category metatag exists and has the correct value.
    Given I am viewing a published "executive_order" with the title "test executive order"
    Then I should see a "category" meta tag of "laws-regulations"

  @caching @dynamic_cache
  Scenario: Verify that the executive order content is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "executive_order" content with the title "test executive order"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
