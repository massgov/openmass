@xss @api
Feature: As a Massachussetts constituent,
  I want to visit a site that is secure

  Scenario: The homepage should not have vulnerabilities
    Given I am on "/"
    Then I should not see any vulnerabilities

  Scenario: Advisory pages should not have any vulnerabilities
    Given I am viewing any advisory node
    Then I should not see any vulnerabilities

  Scenario: Alert pages should not have any vulnerabilities
    Given I am viewing any alert node
    Then I should not see any vulnerabilities
    Given I am on "/alerts"
    Then I should not see any vulnerabilities

  Scenario: Curated List pages should not have any vulnerabilities
    Given I am viewing any curated_list node
    Then I should not see any vulnerabilities

  Scenario: Decision pages should not have any vulnerabilities
    Given I am viewing any decision node
    Then I should not see any vulnerabilities

  Scenario: Decision trees should not have any vulnerabilities
    Given I am viewing any decision_tree node
    Then I should not see any vulnerabilities

  Scenario: Error pages should not have any vulnerabilities
    Given I am viewing any error_page node
    Then I should not see any vulnerabilities

  Scenario: Events should not have any vulnerabilities
    Given I am viewing any event node
    Then I should not see any vulnerabilities

  Scenario: Executive Orders should not have any vulnerabilities
    Given I am viewing any executive_order node
    Then I should not see any vulnerabilities

  Scenario: Form pages should not have any vulnerabilities
    Given I am viewing any form_page node
    Then I should not see any vulnerabilities

  Scenario: Guide Pages should not have any vulnerabilities
    Given I am viewing any guide_page node
    Then I should not see any vulnerabilities

  Scenario: How-to Pages should not have any vulnerabilities
    Given I am viewing any how_to_page node
    Then I should not see any vulnerabilities

  Scenario: Locations should not have any vulnerabilities
    Given I am viewing any location node
    Then I should not see any vulnerabilities

  Scenario: Location details should not have any vulnerabilities
    Given I am viewing any location_details node
    Then I should not see any vulnerabilities

  Scenario: News should not have any vulnerabilities
    Given I am viewing any news node
    Then I should not see any vulnerabilities

  Scenario: Org pages should not have any vulnerabilities
    Given I am viewing any org_page node
    Then I should not see any vulnerabilities

  Scenario: Pages should not have any vulnerabilities
    Given I am viewing any page node
    Then I should not see any vulnerabilities

  Scenario: Promotional Page should not have any vulnerabilities
    Given I am viewing any campaign_landing node
    Then I should not see any vulnerabilities

  Scenario: Regulations should not have any vulnerabilities
    Given I am viewing any regulation node
    Then I should not see any vulnerabilities

  Scenario: Rules should not have any vulnerabilities
    Given I am viewing any rules node
    Then I should not see any vulnerabilities

  Scenario: Service pages should not have any vulnerabilities
    Given I am viewing any service_page node
    Then I should not see any vulnerabilities

  Scenario: Stacked layouts should not have any vulnerabilities
    Given I am viewing any stacked_layout node
    Then I should not see any vulnerabilities

  Scenario: Locations should not have any vulnerabilities
    Given I am viewing any topic_page node
    Then I should not see any vulnerabilities

  Scenario: The homepage should not have any vulnerabilities
    Given I am on "/"
    Then I should not see any vulnerabilities

  Scenario: Documents should not have any vulnerabilities
    Given I am viewing any document media
    Then I should not see any vulnerabilities
