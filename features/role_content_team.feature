@api @contentteam
Feature: Content Management
  As a content team member,
  I want a role to be able to create / edit / delete all content
  so I can create the best content experience for the constituents of Massachusetts.

  Scenario: Verify that content team member can only see content menu item
    Given I am logged in as a user with the "content_team" role
    And I am on "admin"
    Then I should see the link "Content" in the admin_menu
    And I should see the link "Edit blocks"
    And I should see the link "Help and support"
    And I should see the link "Node feedback"
    And I should not see the link "Structure" in the admin_menu
    And I should not see the link "Appearance" in the admin_menu
    And I should not see the link "Extend" in the admin_menu
    And I should not see the link "Configuration" in the admin_menu
    And I should see the link "People" in the admin_menu
    # The test is to verify "Reports Author" feature menu is visible.
    And I should see the link "Reports" in the admin_menu
    #ToDo Find a way to add that content admins should not see the help link again.
    # The test is too crude for the current menu.

  #http response 200 is a successful response
  Scenario: Verify content team can perform necessary actions
    Given I am logged in as a user with the "content_team" role
    And I am on "/user"
    Then I should see the dashboard tabs
    Then I should have access to "/node/add"
    Then I should have access to "/node/add/action"
    And I should have access to "/node/add/page"
    And I should have access to "/node/add/stacked_layout"
    And I should have access to "/node/add/topic_page"
    And I should have access to "/node/add/contact_information"
    And I should have access to "/node/add/guide_page"
    And I should have access to "/node/add/how_to_page"
    And I should have access to "/node/add/location"
    And I should have access to "/node/add/org_page"
    And I should have access to "/node/add/service_page"
    And I should have access to "/node/add/service_details"
    And I should have access to "/admin/structure/menu"
    And I should have access to "/admin/structure/taxonomy/manage/icons/overview"
    And I should not have access to "/admin/reports"
    And I should not have access to "/node/add/error_page"
    And I should not have access to "/node/add/interstitial"
    And I should not have access to "/node/add/alert"

    # Can create / edit / delete users
    And I should have access to "/admin/people"

  Scenario: Verify that content team users have appropriate permissions
    # Has broad permission to deal with all content and can use workbench
    Then the "content_team" role should have the permissions:
      | Permission           |
      | administer nodes     |
      | revert all revisions |
      | view any unpublished content |
      | view latest version          |

    # Does not have permission to change site code or administer users
    Then the "content_team" role should not have the permissions:
      | Permission                  |
      | administer modules          |
      | administer software updates |
      | administer themes           |

    # Access draggableviews
    Then the "content_team" role should have the permissions:
      | Permission            |
      | access draggableviews |

  Scenario: Ensure Content Administrators can create, edit and otherwise manage News, Event, Form, Rules and Guide nodes.
    Then the "content_team" role should have the permissions:
      | Permission                      |
      | create event content            |
      | create news content             |
      | create form_page content        |
      | create guide_page content       |
      | create rules content            |
      | edit any event content          |
      | edit any news content           |
      | edit any guide_page content     |
      | edit any form_page content      |
      | edit any rules content          |
      | edit own event content          |
      | edit own news content           |
      | edit own form_page content      |
      | edit own guide_page content     |
      | edit own rules content          |
      | revert event revisions          |
      | revert news revisions           |
      | revert form_page revisions      |
      | revert guide_page revisions     |
      | revert rules revisions          |
      | view event revisions            |
      | view news revisions             |
      | view form_page revisions        |
      | view guide_page revisions       |
      | view rules revisions            |
    Then the "content_team" role should not have the permissions:
      | Permission               |
      | delete any event content        |
      | delete any news content         |
      | delete any form_page content    |
      | delete any guide_page content   |
      | delete any rules content        |
      | delete own event content        |
      | delete own news content         |
      | delete own form_page content    |
      | delete own guide_page content   |
      | delete own rules content        |

  Scenario: Ensure Content Administrators can create, edit and otherwise manage Person nodes.
    Then the "content_team" role should have the permissions:
      | Permission               |
      | create person content    |
      | edit any person content  |
      | edit own person content  |
      | view person revisions    |
    Then the "content_team" role should not have the permissions:
      | Permission               |
      | delete person revisions  |
      | delete any person content|
      | delete own person content|

  Scenario: Ensure Content Administrators can create, edit and otherwise manage Regulation nodes.
    Then the "content_team" role should have the permissions:
      | Permission                   |
      | create regulation content    |
      | edit any regulation content  |
      | edit own regulation content  |
      | view regulation revisions    |
    Then the "content_team" role should not have the permissions:
      | Permission                   |
      | delete regulation revisions  |
      | delete any regulation content|
      | delete own regulation content|

  Scenario: Ensure Content Administrators can create, edit and otherwise manage Advisory nodes.
    Then the "content_team" role should have the permissions:
      | Permission                 |
      | create advisory content    |
      | edit any advisory content  |
      | edit own advisory content  |
      | view advisory revisions    |
    Then the "content_team" role should not have the permissions:
      | Permission                 |
      | delete advisory revisions  |
      | delete any advisory content|
      | delete own advisory content|

  Scenario: Ensure Content Administrators can create, edit and otherwise manage Decision nodes.
    Then the "content_team" role should have the permissions:
      | Permission               |
      | create decision content    |
      | edit any decision content  |
      | edit own decision content  |
      | view decision revisions    |
    Then the "content_team" role should not have the permissions:
      | Permission                 |
      | delete any decision content|
      | delete own decision content|
      | delete decision revisions  |
