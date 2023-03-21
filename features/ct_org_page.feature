@api
Feature: Organization Landing Page Content type
  As a MassGov administrator,
  I want to be able to add org_page content,
  so that I can create long form pages.

  Scenario: Verify that the org page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "org_page" content has the correct fields

  Scenario: Verify authors can access org_page
    Given I am logged in as a user with the "author" role
    When I go to "/node/add/org_page"
    Then I should get a "200" HTTP response

  Scenario: Verify editors can access org_page
    Given I am logged in as a user with the "editor" role
    When I go to "/node/add/org_page"
    Then I should get a "200" HTTP response

  Scenario: Verify administrators can access org_page
    Given I am logged in as a user with the "administrator" role
    When I go to "/node/add/org_page"
    Then I should get a "200" HTTP response

  Scenario: Verify that pathauto patterns are applied to org_page nodes.
    Given I am viewing a published "org_page" content with the title "Run the Test Suite"
    Then I am on "run-test-suite"

  Scenario: Verify validation for background image.
    Given I am viewing an "service_page" content:
      | title            | Some Featured Service |
      | status           | 1                     |
      | moderation_state | published             |
    And I am logged in as a user with the "administrator" role
    When I am viewing an "org_page" content:
      | title                    | Some Nice Org Page 2   |
      | field-action-set-bg-wide | A header image         |
      | field-sub-title          | Some lede text.        |
    And I follow "EDIT"
    And I fill in "moderation_state[0][state]" with "prepublished_draft"
    And I press "Save"
    Then I should see the text "field is required"

  Scenario: Verify validation for social links.
    Given I am logged in as a user with the "administrator" role
    When I am viewing an "org_page" content:
      | title                    | Some Nice Org Page 3   |
      | field-action-set-bg-wide | A header image         |
      | field-sub-title          | Some lede text.        |
    And I follow "EDIT"
    And I fill in "edit-field-social-links-0-uri" with "http://www.some-incorrect-value.com"
    And I fill in "edit-field-social-links-0-title" with "Incorrect link text"
    And I fill in "moderation_state[0][state]" with "prepublished_draft"
    And I press "Save"
    Then I should see the text "is an invalid link value"

  Scenario: Verify that the category metatag exists and has the correct value.
    Given I am viewing a published "org_page" with the title "test org page"
    Then I should see a "category" meta tag of "state-organizations"

  @caching @dynamic_cache
  Scenario: Verify that the org_page content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "org_page" content with the title "test org page"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    And the "handy_cache_tags:node:news" cache tag should not be used
    And the "handy_cache_tags:node:event" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
