@api @matt
Feature: Organization Landing Page Content type
  As an anonymous user,
  I want to visit an org page in order to learn more information about what
  the agency or organization does, and how I might contact them.

  Scenario: Verify that the org-page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "org_page" has the fields:
      | field                        | tag        | type                       | multivalue |
      | field-title-sub-text         | input      | text                       | false      |
      | field-ref-actions-6          | input      | text                       | true      |
      | field-ref-card-view-6        | input      | text                       | false      |
      | field-bg-wide                | input      | submit                     | false      |
      | field-bg-narrow              | input      | submit                     | false      |
      | field-sub-title              | textarea   |                            | false      |
      | field-sub-brand              | input      | submit                     | false      |
      | field-ref-orgs               | input      | text                       | true       |
      | field-link                   | input      | text                       | false      |
      | field-social-links           | input      | text                       | true       |
      | body                         | textarea   |                            | false      |
      | field-subtype                | select     | submit                     | false      |

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
      | field-links-actions-3    | Some Featured Service  |
    And I follow "Edit"
    And I fill in "edit-field-links-actions-3-0-uri" with "Some Featured Service"
    And I fill in "moderation_state[0][state]" with "prepublished_draft"
    And I press "Save"
    Then I should see the text "field is required"

  Scenario: Verify validation for social links.
    Given I am logged in as a user with the "administrator" role
    When I am viewing an "org_page" content:
      | title                    | Some Nice Org Page 3   |
      | field-action-set-bg-wide | A header image         |
      | field-sub-title          | Some lede text.        |
    And I follow "Edit"
    And I fill in "edit-field-social-links-0-uri" with "http://www.some-incorrect-value.com"
    And I fill in "edit-field-social-links-0-title" with "Incorrect link text"
    And I fill in "moderation_state[0][state]" with "prepublished_draft"
    And I press "Save"
    Then I should see the text "is an invalid link value"

  Scenario: Verify that the org page content type displays it's content correctly
    Given I am logged in as a user with the "administrator" role
    And I create a "service_page" content:
      | title                  | Service Title 1 |
      | status                 | 1               |
    And I create a "service_page" content:
      | title                  | Service Title 2 |
      | status                 | 1               |
    And I create a "service_page" content:
      | title                  | Service Title 3 |
      | status                 | 1               |
    And I create a "service_page" content:
      | title                  | Service Title 4 |
      | status                 | 1               |
    And I create a "service_page" content:
      | title                  | Service Title 5 |
      | status                 | 1               |
    And I create a "service_page" content:
      | title                  | Service Title 6 |
      | status                 | 1               |
    And I create a "service_page" content:
      | title                  | Service Title 7 |
      | status                 | 1               |
    And I am viewing an "org_page" content:
      | title                  | Related organization |
      | status                 | 1                    |
    And "icons" terms:
      | name                 | field_sprite_name |
      | Behat Test: Building | building          |
    And I am viewing a "topic_page" content:
      | title               | Run the Test Suite   |
      | field_topic_type    | topic                |
    When I am viewing an "org_page" content:
      | title                 | Executive Office of Health and Human Services                                                                         |
      | field_title_sub_text  | (EOHHS)                                                                                                               |
      | field_sub_title       | EOHHS oversees health and general support services to help people                                                     |
      | field_links_actions_3 | Google Link - http://www.google.com                                                                                   |
      | field_ref_actions_6   | Service Title 1, Service Title 2, Service Title 3, Service Title 4, Service Title 5, Service Title 6, Service Title 7 |
      | field_ref_card_view_6 | Run the Test Suite                                                                                                    |
      | field_link            | See all EOHHS’s programs and services on classic Mass.gov - http://www.google.com                                     |
      | field_ref_orgs        | Related organization                                                                                                  |
      | body                  | The Executive Office of Health and Human Services is responsible through                                              |
      | field_social_links    | Facebook - http://www.facebook.com/masshhs, Twitter - http://www.twitter.com/masshhs                                  |
      | field_subtype         | General Organization                                                                                                  |
    Then I should see the text "Executive Office of Health and Human Services" in the "page_banner" region
    And I should see the text "(EOHHS)" in the "page_banner" region
    And I should see the text "OHHS oversees health and general support services to help people" in the "stacked_sections" region
    And I should see the text "Google Link"
    And I should see the text "Service Title 1"
    And I should see the link "See all actions & services" in the "action_finder" region
    And I should see the link "Related organization"
    And I should see the text "The Executive Office of Health and Human Services is responsible through" in the "stacked_sections" region
    And I should see the link "Facebook" in the "stacked_sections" region
    And I should see the link "Twitter" in the "stacked_sections" region
    And I should see the link "Run the Test Suite" in the "sections_3up"
    And I should see the link "Related organization" in the "section_related_organizations"

  Scenario: Verify that the category metatag exists and has the correct value.
    Given I am viewing a published "org_page" with the title "test org page"
    Then I should see a "category" meta tag of "state-organizations"

  @caching
  Scenario: Verify that the org page content type shows events immediately
    Given I am viewing a published "org_page" with the title "Events Org Page"
    Then I should not see "Upcoming Events"
    And an event "MyUpcomingEvent" referencing org_page "Events Org Page" happening at "now +1 day"
    When I reload the page
    Then I should see "MyUpcomingEvent"
    Then the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    And the "handy_cache_tags:node:news" cache tag should not be used
    And the "handy_cache_tags:node:event" cache tag should not be used

  @caching
  Scenario: Verify that the org page content type shows news immediately
    Given I am viewing a published org_page with the title "News Org Page"
    Then I should not see "Recent news & announcements"
    And a news item "Extra Extra" referencing "News Org Page"
    When I reload the page
    Then I should see "Extra Extra"
