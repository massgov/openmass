@api
Feature: Advisory Content type
  As a MassGov alpha content editor,
  I want to be able to add advisory pages,
  so that I can inform people about advisories.

  Scenario: Verify that the advisory content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "advisory" content has the correct fields

  @caching
  Scenario: Verify that the advisory content type is efficiently cacheable.
    When I am viewing an "advisory" content with the title "test advisory"
    Then the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
