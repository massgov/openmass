@api @binder
Feature: Binder functionality tests.

  Scenario: Test 1
    Given I am logged in as a user with the "administrator" role
    And I create a "how_to_page" content:
      | title            | BT How to page |
      | moderation_state | published                 |
    And I create a "form_page" content:
      | title            | BT Form Page   |
      | moderation_state | published                 |
    Given I create a "binder" content:
      | title            | BT Binder test page               |
      | moderation_state | published                 |
    And I add a page paragraph that references "BT How to page"
    And I add a page paragraph that references "BT Form Page"
    When I visit the newest page
    Then I should see the text "Table of Contents"
    And I should see the text "BT How to page"
    And I should see the text "BT Form Page"
    
