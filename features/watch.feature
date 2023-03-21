@api
Feature: Mass Flagging
  As an authenticated user I want to be able to watch content that is important to me
  so I can collaborate successfully.

  Scenario: Verify Anonymous user cannot see Watch link
    When I go to "/"
    Then I should not see the link "Watch"

  Scenario: Verify Watch flag config values
    Then the config "flag.flag.watch_content" "flag_short" should equal "Watch"
    Then the config "flag.flag.watch_content" "flag_long" should equal "Watching content will give you email notifications when future revisions have been published."
    Then the config "flag.flag.watch_content" "flag_message" should equal "Successfully added to watchers."
    Then the config "flag.flag.watch_content" "unflag_short" should equal "Unwatch"
    Then the config "flag.flag.watch_content" "unflag_long" should equal "Unwatch will remove you from any email notification for future revisions."
    Then the config "flag.flag.watch_content" "unflag_message" should equal "Successfully removed from watchers."

  Scenario: Verify Authenticated user can see watch link and watch a node
    Given I am logged in as a user with the "author" role
    When I go to "/"
    Then I click "Watch"
    Then I should see the text "Unwatch"

  Scenario: Verify Authenticated use sees confirmation message after creating node
    Given I am logged in as a user with the "administrator" role
    When I go to "/node/add/page"
    Given for "Title" I enter "Test Flag"
    When I press the "Save" button
    Then I should see the text "You are now watching Basic page (Prototype) Test Flag."
    Then I should see the text "You will be notified of any future changes made to this content."
    Then I should see the link "Learn more about this functionality"
    Then I should see the link "stop watching this content"
