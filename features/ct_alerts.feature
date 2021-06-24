@api
Feature: Alert Content type
  As an alert manager,
  I want to be able to author citizen alerts for the public
  So the public can be warned about current or pending emergencies.

  Scenario: Verify Alert Landing Page can render
    When I go to "alerts"
    Then the response status code should be 200

  Scenario: Verify Alert JSON API
    When I go to "/jsonapi/node/alert"
    Then the response status code should be 200
