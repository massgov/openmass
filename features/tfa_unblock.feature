@api
Feature: TFA Unblock
  As an administrator, I want to be able to unblock users who have
  failed to set up two factor authentication in a timely manner.

  Scenario: Admins have access to the listing.
    Given I am logged in as a user with the "administrator" role
    And I am on "admin/config/people/tfa-unblock"
    Then I should get a 200 HTTP response
    And I should see text matching "The TFA module has been configured to deny access"

  Scenario: Non-admins do not have access to the listing.
    Given I am logged in as a user with the "editor" role
    And I am on "admin/config/people/tfa-unblock"
    Then I should get a 403 HTTP response
