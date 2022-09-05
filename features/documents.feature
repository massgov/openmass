@api @documents
Feature: Media Documents
  As a MassGov content editor,
  I want to be able to upload documents,
  so that I can inform people about non-web content.

  Scenario: Create and view a new document
    Given I am logged in as a user with the "editor" role
    And the purge queue is empty
    When I visit "/media/add/document"
    And I fill in the following:
      | Title                             | Test document Behat                |
      | Organization(s)                   | Massachusetts Court System (67286) |
      | Publishing Frequency              | 99                                 |
      | Geographic Place (value 1)        | Massachusetts                      |
      | field_start_date[0][value][date]  | 2018-01-19                         |
      | Save as                           | published                          |
    And I attach the file "upload.txt" to "files[field_upload_file_0]"
    And I press "Save"
    Then I should see "Document upload.txt has been created."
    Given I am on "/admin/ma-dash/documents"
    When I follow "upload.txt"
    Then I should see "Test document Behat"
    And I should see "(TXT 24 BYTES)"
