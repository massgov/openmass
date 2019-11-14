@api
Feature: Legacy Redirects Content type
  As a MassGov alpha content editor,
  I want to be able to add Legacy Redirects,
  so that I can redirect people from old urls to the new pages.

  Scenario: Verify that the Legacy Redirects content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "legacy_redirects" content has the correct fields
