@api
Feature: Promotional page (campaign_landing) Content type
  As a MassGov administrator,
  I want to be able to add campaign_landing content,
  so that I can create promotional pages.

  Scenario: Verify that the promotional page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "campaign_landing" content has the correct fields

  Scenario: Verify authors can access info_details
    Given I am logged in as a user with the "author" role
    When I go to "/node/add/campaign_landing"
    Then I should get a "200" HTTP response

  Scenario: Verify editors can access campaign_landing
    Given I am logged in as a user with the "editor" role
    When I go to "/node/add/campaign_landing"
    Then I should get a "200" HTTP response

  Scenario: Verify administrators can access campaign_landing
    Given I am logged in as a user with the "administrator" role
    When I go to "/node/add/campaign_landing"
    Then I should get a "200" HTTP response

  @caching @dynamic_cache
  Scenario: Verify that the campaign_landing content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "campaign_landing" content with the title "test info details"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
