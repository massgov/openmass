@api
Feature: Binder Content type
  As a MassGov administrator,
  I want to be able to add binder pages,
  so that I can create long form pages.

  @rptest
  Scenario: Verify that the binder content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "binder" content has the correct fields

  Scenario: Verify authors can access binder
    Given I am logged in as a user with the "author" role
    When I go to "/node/add/binder"
    Then I should get a "200" HTTP response

  Scenario: Verify editors can access binder
    Given I am logged in as a user with the "editor" role
    When I go to "/node/add/binder"
    Then I should get a "200" HTTP response

  Scenario: Verify content administrators can access binder
    Given I am logged in as a user with the "content_team,editor" role
    When I go to "/node/add/binder"
    Then I should get a "200" HTTP response

  Scenario: Verify administrators can access binder
    Given I am logged in as a user with the "administrator" role
    When I go to "/node/add/binder"
    Then I should get a "200" HTTP response

  @caching @dynamic_cache
  Scenario: Verify that the binder content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "binder" content with the title "test binder"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
