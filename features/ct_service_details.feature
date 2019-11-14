@api
Feature: Service Details Page Content type
  As a MassGov alpha content editor,
  I want to be able to add service detail pages,
  so that I can inform people about additional information regarding services.

  Scenario: Verify that the service details content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "service_details" content has the correct fields
    And "section_with_heading" paragraph has the correct fields

  Scenario: Verify that the category metatag exists and has the correct value.
    Given I am viewing a published "service_details" content with the title "test service details"
    Then I should see a "category" meta tag of "services"

  @caching @dynamic_cache
  Scenario: Verify that the service detail content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    And I am viewing a published "service_details" content with the title "test service details"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached

  @caching
  Scenario: Verify that a service detail page immediately reflects the pages it is "Related to"
    Given I am viewing a published "service_details" content with the title "My Service Details"
    Then I should not see "My related service page"
    When I create a "service_page" content:
      | title                          | My related service page |
      | field_service_eligibility_info | My Service Details      |
      | moderation_state               | published               |
    And I reload the page
    Then I should see "My related service page"

