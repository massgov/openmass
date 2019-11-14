@api @event_ct
Feature: Event Page Content type
  As a MassGov alpha content editor,
  I want to be able to add event pages,
  so that I can inform people about event information and enable them to take action for it.

  Scenario: Verify that the event content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then the content type "event" has the fields:
      | field                       | tag        | type    | multivalue |
      | field-event-ref-contact     | input      | text    | false      |
      | field-event-capacity        | input      | text    | false      |
      | field-event-date            | input      | date    | false      |
      | field-event-description     | textarea   | text    | false      |
      | field-event-image           | input      | submit  | false      |
      | field-event-logo            | input      | submit  | false      |
      | field-event-fees            | input      | text    | false      |
      | field-event-contact-general | input      | text    | true       |
      | field-event-links           | input      | text    | true       |
      | field-event-lede            | input      | text    | false      |
      | field-event-link-sign-up    | input      | text    | false      |
      | field-event-ref-parents     | input      | text    | true       |
      | field-event-rain-date       | input      | text    | false      |
      | field-event-ref-event-2     | input      | text    | false      |
      | field-event-time            | input      | text    | false      |
      | field-event-you-will-need   | textarea   | text    | false      |
      | field-event-type-list       | select     | text    | false      |

  Scenario: Verify that the event content type
    Given users:
      | name           | mail                    | roles  |
      | testGranted    | testgranted@mass.gov    | author |
      | testRestricted | testrestricted@mass.gov | author |
    Given I am logged in as "testRestricted"
    When I visit the node with restricted access to "testGranted" on "Test event" "event" content
    Then I should see "This page is forbidden"
    And I should not see "Test decision"
    Given I am logged in as "testGranted"
    When I visit the node with restricted access to "testGranted" on "Test event 2" "event" content
    Then I should not see "This page is forbidden"
    And I should see "Test event 2"

  Scenario: Verify that the event type field has two values "General Event" and "Public Meeting"
    Given I am logged in as a user with the "author" role
    When I go to "node/add/event"
    # Confirm that General Event is Default Value
    Then I should see text matching "General Event"
    And I should see text matching "general_event" in field "#edit-field-event-type-list"
    Then I select "Public Meeting" from "field_event_type_list"
    And I should see text matching "public_meeting" in field "#edit-field-event-type-list"

  Scenario: Verify that Agenda and Minute fields only show when event type is "Public Meeting"
    Given I am logged in as a user with the "author" role
    When I go to "node/add/event"
    Then I should see the text "Meeting agenda"
    Then I should see the text "AGENDA DOWNLOAD"
    Then I should see the text "Meeting minutes"
    Then I should see the text "MINUTES DOWNLOAD"

  Scenario: Verify that only one minute section is shown when several are submitted.
    Given I am logged in as a user with the "administrator" role
    When I create a "event" content:
    | title                        |  Event Agenda 1                                             |
    | field_event_description      |  This is a test of events.                                  |
    | field_event_description      |  This is a test of events.                                  |
    | event_type                   |  General Event                                              |

  @caching @dynamic_cache
  Scenario: Verify that the event content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "event" content with the title "test event"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached

