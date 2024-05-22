@api @events
Feature: Organization Events Page
  As an anonymous user,
  I want to see all the events for an organization to learn more about them.

  @caching
  Scenario: Verify that the events pages display past and upcoming events.
    # Create an org and verify that it doesn't show events at all
    Given I am viewing a published "org_page" with the title "Events Test Org"
    Given I am on "/orgs/events-test-org/events"
    Then the response status code should be 200
    Given I am on "/orgs/events-test-org/events/past"
    Then the response status code should be 404

    # Create a past event and verify that it appears correctly.
    Given an event "Org Past Event" referencing org_page "Events Test Org" happening at "now -1 day"
    Given I am on "/orgs/events-test-org/events"
    Then the response status code should be 200
    Given I am on "/orgs/events-test-org/events/past"
    Then I should see "Org Past Event"
    And I should not see "See upcoming events"

    # Create an upcoming event and verify that it appears correctly.
    Given an event "Org Upcoming Event" referencing org_page "Events Test Org" happening at "now +1 day"
    Given I am on "/orgs/events-test-org/events"
    Then I should see "Org Upcoming Event"
    And I should see "See past events"
    And the "node_list" cache tag should not be used
