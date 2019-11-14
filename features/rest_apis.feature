@api
Feature: REST APIs
  As a MassIT developer or anonymous user,
  I want to be able to reach the available REST exports.

  Scenario: Verify anonymous user can get to the content REST export
    When I go to "/api/v1/content"
    Then the response status code should be 200
