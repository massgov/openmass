@api @dash

Feature: Mass Dashboard
  As a content creator,
  I want a dashboard that makes it easier for me to find the content that I want
  to review or edit,
  so that I can perform my job easily and efficiently.

  Scenario: Verify that authorized users see the tray items
    Given I am logged in as a user with the "administrator" role
    When I go to "/admin"
#    Then I should see the link "Content" in the toolbar
    And I should see the link "My content" in the toolbar
    And I should see the link "Needs review" in the toolbar
    And I should see the link "All content" in the toolbar

  Scenario: Verify all dashboard pages load
    Given I am logged in as a user with the "administrator" role
    When I go to "/admin/ma-dash/my-content"
    Then the response status code should be 200
    When I go to "/admin/ma-dash/needs-review"
    Then the response status code should be 200
    When I go to "/admin/ma-dash/all-content"
    Then the response status code should be 200

  Scenario: Verify that anonymous users cannot access any dashboard pages
    Given I am an anonymous user
    Then I should not have access to "/admin/ma-dash/my-content"
    And I should not have access to "/admin/ma-dash/needs-review"
    And I should not have access to "/admin/ma-dash/all-content"

