@api
Feature: Location Landing Page Content type
  As an anonymous user,
  I want to visit an location page in order to learn more information about the
  many locations, and how I might contact them.

  Scenario: Verify that the location content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    And "location_icon" terms:
      | name                    | field_sprite_name |
      | Behat: Transit Friendly | transit           |
    Then the content type "location" has the fields:
      | field                           | tag        | type      | multivalue |
      | field-accessibility             | textarea   |           | false      |
      | field-ref-contact-info          | input      | text      | true      |
      | field-location-facilities       | textarea   |           | false      |
      | field-bg-narrow                 | input      | submit    | false      |
      | field-iframe                    | paragraphs | iframe    | false      |
      | field-location-icons            | input      | checkbox  | false      |
      | field-location-more-information | textarea   |           | false      |
      | field-overview                  | textarea   |           | false      |
      | field-parking                   | textarea   |           | false      |
      | field-ref-contact-info-1        | input      | text      | false      |
      | field-links                     | input      | text      | false      |
      | field-location-activity-detail  | input      | submit    | false      |
      | field-related-locations         | input      | text      | true       |
      | field-restrictions              | textarea   |           | false      |
      | field-services                  | textarea   |           | false      |

  Scenario: Verify that pathauto patterns are applied to location nodes.
    Given I am viewing an "location" content with the title "Run the Test Suite"
    Then I am on "run-test-suite"

  Scenario: Verify that the location content type displays it's content correctly
    Given I am logged in as a user with the "administrator" role
    And I am viewing an "contact_information" content:
      | title                  | Behat: Mt Greylock State Park |
      | field_display_title    | Behat: Mt Greylock State Park |
      | status                 | 1                             |
    And "location_icon" terms:
      | name                    | field_sprite_name |
      | Behat: Transit Friendly | transit           |
    When I am viewing an "location" content:
      | title                           | Behat: Mt Greylock State Park  |
      | field_location_icons            | Behat: Transit Friendly        |
      | field_accessibility             | Accessibility text             |
      | field_location_facilities       | Facilities text                |
      | field_location_more_information | More info text                 |
      | field_overview                  | Overview text                  |
      | field_parking                   | Parking text                   |
      | field_restrictions              | Restrictions text              |
      | field_services                  | Services text                  |
      | field_links                     | Reserve a Campsite - http://www.google.com, Download a Park Map - http://www.google.com, Download a Trail Map  - http://www.google.com |
    Then I should see the text "Behat: Mt Greylock State Park" in the "page_header" region
    And I should see the text "Accessibility text" in the "details_content" region
    And I should see the text "Facilities text" in the "details_content" region
    And I should see the text "More info text" in the "details_content" region
    And I should see the text "Overview text" in the "details_content" region
    And I should see the text "Parking text" in the "details_content" region
    And I should see the text "Restrictions text" in the "details_content" region
    And I should see the text "Services text" in the "details_content" region
    And I should see the link "Reserve a Campsite" in the "key_actions" region
    And I should see the link "Download a Park Map" in the "key_actions" region
    And I should see the link "Download a Trail Map" in the "key_actions" region

  Scenario: Verify that the location content type displays it's content correctly
    Given I am logged in as a user with the "administrator" role
    And I am viewing an "contact_information" content:
      | title                  | Behat: Mt Greylock contact |
      | field_display_title    | Behat: Mt Greylock display |
      | status                 | 1                             |
    And "location_icon" terms:
      | name                    | field_sprite_name |
      | Behat: Transit Friendly | transit           |
    When I am viewing an "location" content:
      | title                           | Behat: Mt Greylock State Park  |
      | field_location_icons            | Behat: Transit Friendly        |
      | field_accessibility             | Accessibility text             |
      | field_location_facilities       | Facilities text                |
      | field_location_more_information | More info text                 |
      | field_overview                  | Overview text                  |
      | field_parking                   | Parking text                   |
      | field_restrictions              | Restrictions text              |
      | field_services                  | Services text                  |
      | field_ref_contact_info          | Behat: Mt Greylock contact     |
      | field_links                     | Reserve a Campsite - http://www.google.com, Download a Park Map - http://www.google.com, Download a Trail Map  - http://www.google.com |
    Then I should see the text "Behat: Mt Greylock display" in the "sidebar_contactus" region
    And I should not see the link "Behat: Mt Greylock display" in the "sidebar_contactus" region

  @caching @dynamic_cache
  Scenario: Verify that the location content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing a "location" content with the title "test location"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
