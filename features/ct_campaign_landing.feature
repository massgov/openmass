@api
Feature: Promotional page (campaign_landing) Content type
  As a MassGov administrator,
  I want to be able to add campaign_landing content,
  so that I can create promotional pages.

  Scenario: Verify that the campaign_landing content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "campaign_landing" content has the correct fields

  Scenario: Verify authors can access the campaign_landing add page
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

  Scenario: Verify the Custom HTML (field_campaign_custom_html) field is available for admins
    Given I am logged in as a user with the "administrator" role
    When I am viewing an "campaign_landing" content with the title "campaign landing edit"
    And I click "Edit"
    Then I press the "field_sections_custom_html_add_more" button
    Then I should see "Custom HTML"
    Then the "custom_html" paragraph has the fields:
      | field                      | widget                    |
      | field-campaign-custom-html | Text area (multiple rows) |

  Scenario: Verify the Custom HTML (field_campaign_custom_html) field is hidden for Editors
    Given I am logged in as a user with the "editor" role
    When I am viewing an "campaign_landing" content with the title "campaign landing edit"
    And I click "Edit"
    Then I press the "field_sections_custom_html_add_more" button
    Then I should not see "Custom HTML"

  Scenario: Verify the Custom HTML (field_campaign_custom_html) field is hidden for Authors
    Given I am logged in as a user with the "author" role
    When I am viewing an "campaign_landing" content with the title "campaign landing edit"
    And I click "Edit"
    Then I press the "field_sections_custom_html_add_more" button
    Then I should not see "Custom HTML"

  @caching @dynamic_cache
  Scenario: Verify that the campaign_landing content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "campaign_landing" content with the title "test campaign landing"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
