@api @binder
Feature: Binder functionality tests.

  Scenario: Test binder table of contents and navigation links on child nodes.
    Given I am logged in as a user with the "administrator" role
    And I create a "how_to_page" content:
      | title            | BT How to page |
      | moderation_state | published                 |
    And I create a "form_page" content:
      | title            | BT Form Page   |
      | moderation_state | published                 |
    And I create a "binder" content:
      | title            | BT Binder test page               |
      | moderation_state | published                 |
    And I add a page paragraph that links to "BT How to page"
    And I add a page paragraph that links to "BT Form Page"
    When I visit the newest page
    Then I should see the text "Table of Contents"
    And I should see the link "BT How to page"
    And I should see the link "BT Form Page"
    When I visit "/how-to/bt-how-to-page"
    Then I should see the text "BT How to page"
    And I should see the text "This is part of"
    And I should see the link "BT Binder test page"
    When I visit "/forms/bt-form-page"
    Then I should see the text "BT Form Page"
    And I should see the text "This is part of"
    And I should see the link "BT Binder test page"
