@api
Feature: Restricted Access
  As an editor,
  I want to restrict access to content to unpublished content to certain users

  Scenario: Restrict access to content
    Given users:
      | name            | mail                    | roles  |
      | testGranted    | testgranted@mass.gov    | author |
      | testRestricted | testrestricted@mass.gov | author |

    # Users whose access has been restricted should not see the node.
    Given I am logged in as "testRestricted"
    # Action:
    When I visit the node with restricted access to "testGranted" on "Test action" "action" content
    Then I should get a 403 HTTP response
    # Advisory
    When I visit the node with restricted access to "testGranted" on "Test advisory" "advisory" content
    Then I should get a 403 HTTP response
    # Decision
    When I visit the node with restricted access to "testGranted" on "Test decision" "decision" content
    Then I should get a 403 HTTP response
    # Event
    When I visit the node with restricted access to "testGranted" on "Test Event" "event" content
    Then I should get a 403 HTTP response
    # Executive Order
    When I visit the node with restricted access to "testGranted" on "Test Executive Order" "executive_order" content
    Then I should get a 403 HTTP response
    # Form Page
    When I visit the node with restricted access to "testGranted" on "Test Form Page" "form_page" content
    Then I should get a 403 HTTP response
    # Guide Page
    When I visit the node with restricted access to "testGranted" on "Test Guide Page" "guide_page" content
    Then I should get a 403 HTTP response
    # How To Page
    When I visit the node with restricted access to "testGranted" on "Test How To Page" "how_to_page" content
    Then I should get a 403 HTTP response
    # Info Details Page
    When I visit the node with restricted access to "testGranted" on "Test info details" "info_details" content
    Then I should get a 403 HTTP response
    # Location
    When I visit the node with restricted access to "testGranted" on "Test Location" "location" content
    Then I should get a 403 HTTP response
    # Location Details
    When I visit the node with restricted access to "testGranted" on "Test Location details" "location_details" content
    Then I should get a 403 HTTP response
    # News
    When I visit the node with restricted access to "testGranted" on "Test News" "news" content
    Then I should get a 403 HTTP response
    # Org page
    When I visit the node with restricted access to "testGranted" on "Test org page" "org_page" content
    Then I should get a 403 HTTP response
    # Promotional Page
    When I visit the node with restricted access to "testGranted" on "Test campaign landing" "campaign_landing" content
    Then I should get a 403 HTTP response
    # Regulation
    When I visit the node with restricted access to "testGranted" on "Test Regulation" "regulation" content
    Then I should get a 403 HTTP response
    # Service Page
    When I visit the node with restricted access to "testGranted" on "Test Service Page" "service_page" content
    Then I should get a 403 HTTP response
    # Stacked Layout
    When I visit the node with restricted access to "testGranted" on "Test Stacked Layout" "stacked_layout" content
    Then I should get a 403 HTTP response
    # Topic Page
    When I visit the node with restricted access to "testGranted" on "Test Topic Page" "topic_page" content
    Then I should get a 403 HTTP response


    # Users who are explicitly granted access should get it.
    Given I am logged in as "testGranted"
    # Action
    When I visit the node with restricted access to "testGranted" on "Test action" "action" content
    Then I should get a 200 HTTP response
    # Advisory
    When I visit the node with restricted access to "testGranted" on "Test advisory" "advisory" content
    Then I should get a 200 HTTP response
    # Decision
    When I visit the node with restricted access to "testGranted" on "Test decision" "decision" content
    Then I should get a 200 HTTP response
    # Event
    When I visit the node with restricted access to "testGranted" on "Test Event" "event" content
    Then I should get a 200 HTTP response
    # Executive Order
    When I visit the node with restricted access to "testGranted" on "Test Executive Order" "executive_order" content
    Then I should get a 200 HTTP response
    # Form Page
    When I visit the node with restricted access to "testGranted" on "Test Form Page" "form_page" content
    Then I should get a 200 HTTP response
    # Guide Page
    When I visit the node with restricted access to "testGranted" on "Test Guide Page" "guide_page" content
    Then I should get a 200 HTTP response
    # How To Page
    When I visit the node with restricted access to "testGranted" on "Test How To Page" "how_to_page" content
    Then I should get a 200 HTTP response
    # Info Details Page
    When I visit the node with restricted access to "testGranted" on "Test info details" "info_details" content
    Then I should get a 200 HTTP response
    # Location
    When I visit the node with restricted access to "testGranted" on "Test Location" "location" content
    Then I should get a 200 HTTP response
    # Location Details
    When I visit the node with restricted access to "testGranted" on "Test Location details" "location_details" content
    Then I should get a 200 HTTP response
    # News
    When I visit the node with restricted access to "testGranted" on "Test News" "news" content
    Then I should get a 200 HTTP response
    # Org page:
    When I visit the node with restricted access to "testGranted" on "Test org page 2" "org_page" content
    Then I should get a 200 HTTP response
    # Promotional Page
    When I visit the node with restricted access to "testGranted" on "Test campaign landing" "campaign_landing" content
    Then I should get a 200 HTTP response
    # Regulation
    When I visit the node with restricted access to "testGranted" on "Test Regulation" "regulation" content
    Then I should get a 200 HTTP response
    # Service Page
    When I visit the node with restricted access to "testGranted" on "Test Service Page" "service_page" content
    Then I should get a 200 HTTP response
    # Stacked Layout
    When I visit the node with restricted access to "testGranted" on "Test Stacked Layout" "stacked_layout" content
    Then I should get a 200 HTTP response
    # Topic Page
    When I visit the node with restricted access to "testGranted" on "Test Topic Page" "topic_page" content
    Then I should get a 200 HTTP response





