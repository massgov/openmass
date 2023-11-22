@api
Feature: Author Role
  As an author whose work needs approval before publication,
  I want a role to be able to create / edit certain kinds of content without the ability to publish
  so I can create the best content experience for the constituents of Massachusetts.

  Scenario: Verify that authors can only see content menu item
    Given I am logged in as a user with the "author" role
    And I am on "admin"
    Then I should not see the link "Manage" in the "toolbar" region
    Then I should see the link "Content" in the "toolbar" region

  Scenario: Verify that author does not have permission to change site code or administer users
    Then the "author" role should not have the permissions:
      | Permission                  |
      | administer modules          |
      | administer software updates |
      | administer themes           |
      | administer users            |

  Scenario: Verify that the author can access key pages
    Given I am logged in as a user with the "author" role
    And I am on "/user"
    Then I should see the dashboard tabs
    Then I should have access to "/admin/content"
    And I should have access to "/node/add"
    And I should have access to "/node/add/contact_information"
#    Then I should have access to "/node/add/guide_page"
#    Then I should have access to "/node/add/how_to_page"
#    Then I should have access to "/node/add/location"
    And I should have access to "/node/add/org_page"
    And I should have access to "/node/add/service_page"
    And I should not have access to "/admin/people"
    And I should not have access to "/admin/ma-dash/needs-review"
    And I should have access to "/admin/ma-dash/all-content"


  Scenario: Verify that author can use draggable views
    Then the "author" role should have the permissions:
      | Permission            |
      | access draggableviews |

  # Unpublished meaning in the context is for creating a new org page content
  Scenario: Verify that author can edit unpublished content, request review, but not publish
    Given I am logged in as a user with the "author" role
    And I am editing an unpublished "org_page" with the title "Behat Org Page"
    Then the select list "#edit-moderation-state-0-state" should contain the option "prepublished_draft"
    And the select list "#edit-moderation-state-0-state" should contain the option "prepublished_needs_review"
    And the select list "#edit-moderation-state-0-state" should not contain the option "published"

  Scenario: Verify that author can add new or existing Documents to content, and it is okay for them to edit any document
    Then the "author" role should have the permission:
      | Permission            |
      | create media |
      | update media |
      | update any media |


  Scenario: Ensure authors can create, edit and otherwise manage News, Forms, Guides and Event nodes.
    Then the "author" role should have the permissions:
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
      | view event revisions            |
      | view news revisions             |
      | view form_page revisions        |
      | view guide_page revisions       |
      | view rules revisions            |
    Then the "author" role should not have the permissions:
      | Permission                      |
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
      | revert event revisions          |
      | revert news revisions           |
      | revert form_page revisions      |
      | revert guide_page revisions     |
      | revert rules revisions          |

  Scenario: Ensure authors can create, edit and otherwise manage Person nodes.
    Then the "author" role should have the permissions:
      | Permission               |
      | create person content    |
      | edit any person content  |
      | edit own person content  |
      | view person revisions    |
    Then the "author" role should not have the permissions:
      | Permission               |
      | delete person revisions  |
      | delete any person content|
      | delete own person content|

      Scenario: Ensure authors can create, edit and otherwise manage Regulation nodes.
    Then the "author" role should have the permissions:
      | Permission                   |
      | create regulation content    |
      | edit any regulation content  |
      | edit own regulation content  |
      | view regulation revisions    |
    Then the "author" role should not have the permissions:
      | Permission                   |
      | delete regulation revisions  |
      | delete any regulation content|
      | delete own regulation content|

  Scenario: Ensure authors can create, edit and otherwise manage Advisory nodes.
    Then the "author" role should have the permissions:
      | Permission                 |
      | create advisory content    |
      | edit any advisory content  |
      | edit own advisory content  |
      | view advisory revisions    |
    Then the "author" role should not have the permissions:
      | Permission                 |
      | delete advisory revisions  |
      | delete any advisory content|
      | delete own advisory content|

  Scenario: Ensure authors can create, edit and otherwise manage Decision nodes.
    Then the "author" role should have the permissions:
      | Permission                 |
      | create decision content    |
      | edit any decision content  |
      | edit own decision content  |
      | view decision revisions    |
    Then the "author" role should not have the permissions:
      | Permission                 |
      | delete decision revisions  |
      | delete any decision content|
      | delete own decision content|

  Scenario: Ensure authors can create, edit and otherwise manage Curated list nodes.
    Then the "author" role should have the permissions:
      | Permission                     |
      | create curated_list content    |
      | edit any curated_list content  |
      | edit own curated_list content  |
      | view curated_list revisions    |
    Then the "author" role should not have the permissions:
      | Permission                     |
      | delete curated_list revisions  |
      | delete any curated_list content|
      | delete own curated_list content|

  Scenario: Ensure authors can create, edit and otherwise manage Promotional Page (campaign_landing) nodes.
    Then the "author" role should have the permissions:
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

  Scenario: Verify that author's org field gets populated on new content
    Given "user_organization" terms:
      | name              | parent | description_field |
      | behatOrgTestTerm  |        | Test description  |

    Given users:
      | name        | mail             | roles  | field_user_org   |
      | userWithOrg | testorg@mass.gov | author | behatOrgTestTerm |

    Given I am logged in as "userWithOrg"
    And I am on "node/add/info_details"
    And the response should contain "behatOrgTestTerm"
