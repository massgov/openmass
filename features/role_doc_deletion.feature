@api
Feature: Doc deletion
  As a Doc deletion,
  I want a role to be able delete only document file types
  so I can manage the document files on the site.

  Scenario: Verify editors have access to document files and viewing pdfs not jpegs in the view
    Given I am logged in as a user with the "Doc deletion" role
    And I should have access to "/admin/content/document-files"
    When I go to "/admin/content/document-files"
    And I fill in "edit-uri" with "pdf"
    And I press "Filter"
    Then I should see the link "Delete"
    And I press "Reset"
    And I fill in "edit-uri" with "jpeg"
    And I press "Filter"
    Then I should not see the link "Delete"
