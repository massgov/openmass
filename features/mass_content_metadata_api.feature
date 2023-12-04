@api

Feature: Mass Content Metadata API
  As a developer building useful dashboards,
  I want an API exposing all node metadata so
  that I can build effective dashboards.

  Scenario: Verify the content metadata API route exists
    Given I am logged in as a user with the "developer" role
    When I go to "/api/v1/content-metadata?_format=json&limit=1&offset=0"
    Then the response status code should be 200

  Scenario: Verify the content metadata API route is not accessible to anonymous users
    Given I am an anonymous user
    When I go to "/api/v1/content-metadata?_format=json&limit=1&offset=0"
    Then the response status code should be 403

  Scenario: Verify only developers can access the API
    Given I am logged in as a user with the "author" role
    When I go to "/api/v1/content-metadata?_format=json&limit=1&offset=0"
    Then the response status code should be 403

  Scenario: Verify only developers can access the API
    Given I am an anonymous user
    When I go to "/api/v1/content-metadata?_format=json&limit=1&offset=0"
    Then the response status code should be 403
