@api
Feature:
  As a MassGov editor,
  I want to be able to contact other editors
  so we can collaborate effectively

  Scenario: Anonymous users cannot see the contact form
    Given I am an anonymous user
    Then I should not have access to "/user/936/contact"

  Scenario: Editors can see and use the contact form
    Given I am logged in as a user with the "editor" role
    When I visit "/user/936/contact"
    And I fill in the following:
      | Subject | Test            |
      | Message | This is a test. |
    # @todo: Check submission once we have a way to test mail.

  Scenario: Editors can see the contact link on nodes
    Given I am logged in as a user with the "editor" role
    And I am viewing an event:
      | title            | Test      |
      | uid              | 936   |
      | moderation_state | published |
    And I click "EDIT"
    Then I should see the link "Contact the author"



