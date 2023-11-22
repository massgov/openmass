@api
Feature:
  As a mass.gov constituent, I want to see pretty error pages
  So I don't get the impression the website is broken.

  Scenario: Visit a page that doesn't exist
    Given I am on "/wild-blue-elephants-roam"
    Then I should see the 404 error page
    And I am on "/wild-blue-elephants-roam-again"
    Then I should see the 404 error page
    And the page should be dynamically cached

  Scenario: Visit a page I should not be allowed to see
    Given I am on "/user/1/edit"
#    Not a 403 because we have username_enumeration_prevention.
    Then I am on "/user/login"
    Given I am on "/admin"
    Then I am on "/user/login"
    When I am on "/admin/reports"
    Then I am on "/user/login"
    And the page should be dynamically cached
