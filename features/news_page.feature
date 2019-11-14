@api
Feature: Organization Landing Page Content type
  As an anonymous user,
  I want to see all the news for an organization to learn more about them.

  # Validates the lack of a caching bug fixed in DP-7498 that caused
  # the news page to disappear when an invalid page was visited,
  # the a valid page was visited 2x.
  @caching
  Scenario: Verify that the news page does not display caching bugs
    Given I am viewing a published "org_page" with the title "News Test Org"
    And a news item "foo news item" referencing "News Test Org"
    Given I am on "<currentpath>/news?page=2"
    Then the response status code should be 404
    And I am on "<currentpath>"
    When I am on "<currentpath:noquery>"
    Then the response status code should be 200
    And I should see "foo news item"

  @caching
  Scenario: Verify that the news page does not have the node_list cache tag.
    Given I am viewing a published "org_page" with the title "News Test Org"
    And a news item "foo news item" referencing "News Test Org"
    Given I am on "<currentpath>/news"
    Then the "node_list" cache tag should not be used

  # This is a test for subpathauto.
  Scenario: Verify that the subpathauto is working by chcking that a news page is displayed at an aliased url
    Given I am viewing a published "org_page" with the title "News Test Org 2"
    Then I should be on "/orgs/news-test-org-2"
    And a news item "foo news item" referencing "News Test Org 2"
    And I am on "/orgs/news-test-org-2/news"
    Then the response status code should be 200
    And I should be on "/orgs/news-test-org-2/news"
