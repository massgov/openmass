@api @feedback_form

Feature: Mass Feedback Form
  As a content creator,
  I want anynomous users to be able to leave feedback on all pages,
  so that I can determine if the current content, layout, and design is optimal.

  # Disabling until we can add the org-based feedback form to this page.
  # Scenario: Verify that Mass Feedback Form does show on action nodes.
  #  Given I am viewing an "action" with the title "Run the Test Suite"
  #  Then I should see a feedback form

  # Disabling this to adjust the test to set an org value to see the feedback form.
  # @TODO Adjust the test to populate the organization so that we can demonstrate the feedback form.
  # Scenario: Verify that Mass Feedback form shows on service pages.
  #  Given I am viewing a "service_page" with the title "Test Service Page"
  #  Then I should see a feedback form with the latest node_id

  # Disabling - this is not on the new search app. Re-enable once added back.
  #Scenario: Verify that Mass Feedback Form shows on the search page.
  #  Given I am on "/search?q=foo"
  #  Then I should see a feedback form with the node_id 0

#  This is waiting on a decision of what pattern lab regions will be available for blocks
#  @TODO remove the above scenario when the below scenario is used
#  Scenario: Verify Mass Feedback Form shows on pages other than the home.
#    Given default test content exists
#    When I visit the test "subtopic" "Behat Test: Nature & Outdoor Activities"
#    Then I should see text matching "Online Form - Feedback - Multi Page"
#    And I visit the test "topic" "Behat Test: State Parks & Recreation"
#    Then I should see text matching "Online Form - Feedback - Multi Page"
#    And I visit the test "section_landing" "Behat Test: Visiting & Exploring"
#    Then I should see text matching "Online Form - Feedback - Multi Page"

  Scenario: Verify Mass Feedback form does not show on alerts, user paths, or the homepage
    Given I am an anonymous user
    When I am on "/user/login"
    Then I should not see a feedback form
    When I am on "/"
    Then I should not see a feedback form
    When I am on "/alerts"
    Then I should not see a feedback form

