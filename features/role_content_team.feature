@api @contentadmin
Feature: Content Administrator
  As a content administrator (content_team + editor role),
  I want a role to be able to create / edit / delete all content
  so I can create the best content experience for the constituents of Massachusetts.

  Scenario: Verify that content administrator can only see content menu item
    Given I am logged in as a user with the "content_team,editor" role
    And I am on "admin"
    Then I should see the link "Content" in the admin_menu
    And I should see the link "Edit blocks"
    And I should see the link "Right Sidebar"
    And I should see the link "Node feedback"
    And I should not see the link "Structure" in the admin_menu
    And I should not see the link "Appearance" in the admin_menu
    And I should not see the link "Extend" in the admin_menu
    And I should not see the link "Configuration" in the admin_menu
    And I should see the link "People" in the admin_menu
    # The test is to verify "Reports Author" feature menu is visible.
    And I should see the link "Reports" in the admin_menu

  #http response 200 is a successful response
  Scenario: Verify content administrator can perform necessary actions
    Given I am logged in as a user with the "content_team,editor" role
    And I am on "/user"
    Then I should see the dashboard tabs
    Then I should have access to "/node/add"
    And I should have access to "/node/add/page"
    And I should have access to "/node/add/stacked_layout"
    And I should have access to "/node/add/topic_page"
    And I should have access to "/node/add/contact_information"
    And I should have access to "/node/add/how_to_page"
    And I should have access to "/node/add/location"
    And I should have access to "/node/add/org_page"
    And I should have access to "/node/add/service_page"
    And I should have access to "/admin/structure/menu"
    And I should have access to "/admin/structure/taxonomy/manage/icons/overview"
    And I should not have access to "/admin/reports"
    And I should not have access to "/node/add/error_page"
    And I should not have access to "/node/add/interstitial"

    # Can create / edit / delete users
    And I should have access to "/admin/people"
