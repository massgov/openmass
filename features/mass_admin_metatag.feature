@api
Feature: Mass Admin Metadata Test
  As an admin user,
  I want to see the mg_backend_user_org metatag.

  Scenario: Evaluating Admin page metatags
    Given I am logged in as a user with the "administrator" role
    When I am on "admin/content"
    Then I should see a "mg_backend_user_org" meta tag of "user-org-not-set"
