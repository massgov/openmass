@api
@document
Feature: Topic Content type
  As a MassGov content editor,
  I want to be able to add metadata content.

  Scenario: Verify Document link is on node/add screen
    Given I am logged in as a user with the "author" role
    When I visit "/node/add"
    Then I should see the text "Document"
    And I should not see the text "MassDocs"

  Scenario: Documents Admin Screen
    Given I am logged in as a user with the "administrator" role
    When I visit "admin/ma-dash/documents"
    Then I should see "Title"
    Then I should see "Filename Contains"
    Then I should see "ID"
    Then I should see "Publication status"
    Then I should see "Author"
    Then I should see "Organization"
    Then I should see "Add document"
