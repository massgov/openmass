@api
Feature: editor Role
  As an editor,
  I want a role to be able to create / edit / delete / publish certain targeted types of content
  so I can create the best content experience for the constituents of Massachusetts.

  Scenario: Verify that content team member can only see content menu item
    Given I am logged in as a user with the "editor" role
    And I am on "admin"
    Then I should not see the link "Manage" in the "toolbar" region
    Then I should see the link "Content" in the "toolbar" region

  Scenario: Verify that editor does not have permission to change site code or administer users
    Given I am logged in as a user with the "editor" role
    Then I should not have the "administer modules, administer software updates, administer themes, administer users" permissions

  #http response 200 is a successful response
  Scenario: Verify editor can create content
    Given I am logged in as a user with the "editor" role
    And I am on "/user"
    Then I should see the dashboard tabs
    # editor can see 'Add content' button
    Then I should have access to "/node/add"
    And I should have access to "/node/add/contact_information"
    # Then I should have access to "/node/add/guide_page"
    # Then I should have access to "/node/add/how_to_page"
    # Then I should have access to "/node/add/location"
    And I should have access to "/node/add/org_page"
    And I should have access to "/node/add/service_page"
    And I should have access to "/admin/ma-dash/needs-review"

    And I should not have access to "/admin/people"

  Scenario: An editor should be able to use draggable views
    Then the "editor" role should have the permissions:
      | Permission            |
      | access draggableviews |

  # Unpublished meaning in the context is for creating a new org page content
  Scenario: Verify that editor can edit and publish unpublished content
    Given I am logged in as a user with the "editor" role
    And I am editing an unpublished "org_page" with the title "Behat Org Page"
    Then the select list "#edit-moderation-state-0-state" should contain the option "prepublished_draft"
    And the select list "#edit-moderation-state-0-state" should contain the option "prepublished_needs_review"
    And the select list "#edit-moderation-state-0-state" should contain the option "published"
    And the select list "#edit-moderation-state-0-state" should contain the option "trash"

  Scenario: An editor should be able to add new or existing Documents to content and edit any Documents
    Then the "editor" role should have the permission:
      | Permission            |
      | create media |
      | update media |
      | update any media      |

  Scenario: Ensure Editors can create, edit and otherwise manage News, Event, Form and Guide nodes.
    Then the "editor" role should have the permissions:
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
    Then the "editor" role should not have the permissions:
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

  Scenario: Ensure Editors can create, edit and otherwise manage Person nodes.
    Then the "editor" role should have the permissions:
      | Permission               |
      | create person content    |
      | edit any person content  |
      | edit own person content  |
      | view person revisions    |
    Then the "editor" role should not have the permissions:
      | Permission               |
      | delete person revisions  |
      | delete any person content|
      | delete own person content|

Scenario: Ensure Editors can create, edit and otherwise manage Regulation nodes.
    Then the "editor" role should have the permissions:
      | Permission                   |
      | create regulation content    |
      | edit any regulation content  |
      | edit own regulation content  |
      | view regulation revisions    |
    Then the "editor" role should not have the permissions:
      | Permission                   |
      | delete regulation revisions  |
      | delete any regulation content|
      | delete own regulation content|

  Scenario: Ensure Editors can create, edit and otherwise manage Advisory nodes.
    Then the "editor" role should have the permissions:
      | Permission                 |
      | create advisory content    |
      | edit any advisory content  |
      | edit own advisory content  |
      | view advisory revisions    |
    Then the "editor" role should not have the permissions:
      | Permission                 |
      | delete advisory revisions  |
      | delete any advisory content|
      | delete own advisory content|

  Scenario: Ensure Editors can create, edit and otherwise manage Decision nodes.
    Then the "editor" role should have the permissions:
      | Permission               |
      | create decision content    |
      | edit any decision content  |
      | edit own decision content  |
      | view decision revisions    |
    Then the "editor" role should not have the permissions:
      | Permission                 |
      | delete decision revisions  |
      | delete any decision content|
      | delete own decision content|

  Scenario: Ensure Editors can create, edit and otherwise manage Curated List nodes.
    Then the "editor" role should have the permissions:
      | Permission               |
      | create curated_list content    |
      | edit any curated_list content  |
      | edit own curated_list content  |
      | view curated_list revisions    |
    Then the "editor" role should not have the permissions:
      | Permission                   |
      | delete curated_list revisions  |
      | delete any curated_list content|
      | delete own curated_list content|

  Scenario: Ensure Editors can create, edit and otherwise manage Promotional Page (campaign_landing) nodes.
    Then the "editor" role should have the permissions:
      | Permission                        |
      | create campaign_landing content   |
      | edit any campaign_landing content |
      | edit own campaign_landing content |
      | view campaign_landing revisions   |
      | use campaign_landing_page transition needs_review              |
      | use campaign_landing_page transition prepublished_draft        |
      | use campaign_landing_page transition prepublished_needs_review |
      | use campaign_landing_page transition to_draft                  |
    Then the "editor" role should not have the permissions:
      | Permission                          |
      | delete campaign_landing revisions   |
      | delete any campaign_landing content |
      | delete own campaign_landing content |
